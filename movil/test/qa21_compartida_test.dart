import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_models.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_repository.dart';
import 'package:geocruz_movil/features/hotspots/view_model/hotspots_view_model.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// Replay de la captura histórica compartida con la web; nunca consulta NASA.
Map<String, dynamic> read(String path) =>
    jsonDecode(File('../$path').readAsStringSync()) as Map<String, dynamic>;
final seed = read('docs/evidencias/api01/consulta_real.json');
final catalog = read('compartido/territorios/datos/catalogo.json');
final geometry = read('compartido/territorios/datos/limites/departamentos.geojson');
final filters = HotspotFilters(
  fromUtc: DateTime.parse(seed['consulta']['periodo']['desde'] as String),
  untilUtc: DateTime.parse(seed['consulta']['periodo']['hasta'] as String),
);
final results = <String, dynamic>{
  'cliente': 'movil', 'origen': 'replay_controlado',
  'casos': <String>[], 'equivalencia': null,
};

Map<String, dynamic> page([int number = 1]) {
  final r = jsonDecode(jsonEncode(seed)) as Map<String, dynamic>;
  r['datos'] = (r['datos'] as List).skip((number - 1) * 25).take(25).toList();
  r['paginacion'] = {
    ...r['paginacion'] as Map<String, dynamic>,
    'pagina': number, 'tamanioPagina': 25,
    'siguiente': number < ((r['paginacion']['total'] as int) / 25).ceil()
        ? number + 1 : null,
  };
  return r;
}

Map<String, dynamic> empty(String coverage) {
  final r = page();
  r['datos'] = <Object>[];
  r['paginacion'] = {...r['paginacion'] as Map, 'total': 0, 'siguiente': null};
  r['resumen'] = {
    ...r['resumen'] as Map, 'totalRegistros': 0, 'registrosValidos': 0,
    'registrosConFaltantes': 0, 'indicadores': <Object>[],
  };
  r['cobertura'] = {...r['cobertura'] as Map, 'estado': coverage,
    'fundamento': 'Condición controlada de QA-01; no observación externa.'};
  return r;
}

http.Response jsonResponse(Object value, [int status = 200]) =>
    http.Response(jsonEncode(value), status,
        headers: {'content-type': 'application/json; charset=utf-8'});

HotspotsViewModel create(Future<http.Response> Function(Uri) respond) {
  final api = GeoCruzApiClient(
    baseUrl: Uri.parse('http://prueba/api/v1/'),
    client: MockClient((request) async {
      if (request.url.path.endsWith('/limite')) {
        return jsonResponse({
          'territorio': seed['consulta']['territorio'],
          'procedencia': {'version': seed['consulta']['territorio']['versionCatalogo']},
          'limite': geometry['features'][0],
        });
      }
      return respond(request.url);
    }),
  );
  final vm = HotspotsViewModel(HotspotRepository(api));
  vm.territories = (catalog['territorios'] as List)
      .map(TerritoryOption.fromJson).toList();
  addTearDown(() { vm.dispose(); api.close(); });
  return vm;
}

void runCase(String id, Future<void> Function() execute) {
  test('QA21 $id', () async {
    await execute();
    (results['casos'] as List<String>).add(id);
  });
}

void main() {
  runCase('equivalencia', () async {
    final vm = create((_) async => jsonResponse(page()));
    await vm.apply(filters);
    expect(vm.queryError, isNull);
    final r = vm.response!;
    expect(r.records, hasLength(25));
    results['equivalencia'] = {
      'datos': r.records.map((x) => {
        'identificador': x.identifier, 'atributos': x.attributes,
        'fechas': x.dates, 'territorio': x.territory, 'faltantes': x.missing,
        'fuentesRef': x.sourceRefs, 'naturaleza': x.nature, 'metodo': x.method,
        'coordenadas': x.coordinates == null ? null
            : [x.coordinates!.longitude, x.coordinates!.latitude],
      }).toList(),
      'consulta': r.raw['consulta'], 'fuentes': r.sources,
      'resumen': r.summary, 'cobertura': r.coverage,
      'advertencias': r.warnings, 'paginacion': r.raw['paginacion'],
    };
  });

  runCase('paginacion', () async {
    final calls = <Uri>[];
    final vm = create((u) async {
      calls.add(u);
      return jsonResponse(page(int.parse(u.queryParameters['pagina']!)));
    });
    await vm.apply(filters);
    final first = vm.response!;
    await vm.nextPage();
    expect(vm.queryError, isNull);
    expect(vm.response!.page, 2);
    expect(vm.response!.records.map((x) => x.identifier).toList(),
        (seed['datos'] as List).skip(25).take(25).map((x) => x['identificador']).toList());
    expect(vm.response!.summary, first.summary);
    expect(calls[1].queryParameters['versionConjunto'], first.setVersion);
  });

  for (final pair in [['vacio', 'desconocida'],
    ['sin_cobertura', 'sin_cobertura'], ['parcial', 'parcial']]) {
    runCase(pair[0], () async {
      final vm = create((_) async => jsonResponse(empty(pair[1])));
      await vm.apply(filters);
      expect(vm.queryError, isNull);
      expect(vm.response!.coverageState, pair[1]);
      expect(vm.response!.records, isEmpty);
      expect(vm.response!.total, 0);
    });
  }

  runCase('fallo_reintento', () async {
    var fail = false;
    final vm = create((_) async => fail
        ? jsonResponse({'error': {'codigo': 'PROVEEDOR_NO_DISPONIBLE',
            'mensaje': 'Fallo controlado'}}, 503) : jsonResponse(page()));
    await vm.apply(filters);
    final previous = vm.response;
    fail = true;
    await vm.apply(filters);
    expect(vm.queryError, isNotNull);
    expect(vm.response, same(previous));
    fail = false;
    await vm.retry();
    expect(vm.queryError, isNull);
    expect(vm.response!.records, hasLength(25));
  });

  runCase('expiracion', () async {
    final calls = <Uri>[];
    final vm = create((u) async {
      calls.add(u);
      return u.queryParameters.containsKey('versionConjunto')
          ? jsonResponse({'error': {'codigo': 'CONJUNTO_CAMBIADO', 'mensaje': 'Expirado'}}, 409)
          : jsonResponse(page());
    });
    await vm.apply(filters);
    final previous = vm.response;
    await vm.nextPage();
    expect(vm.queryExpired, isTrue);
    expect(vm.response, same(previous));
    await vm.restartExpiredQuery();
    expect(calls.last.queryParameters.containsKey('versionConjunto'), isFalse);
    expect(vm.queryError, isNull);
  });

  runCase('respuesta_antigua', () async {
    final deferred = <Completer<http.Response>>[];
    final started = Completer<void>();
    final vm = create((_) {
      final pending = Completer<http.Response>();
      deferred.add(pending);
      if (deferred.length == 2) started.complete();
      return pending.future;
    });
    final old = vm.apply(filters);
    final recent = vm.apply(filters);
    await started.future;
    final newer = page();
    newer['consulta']['identificador'] = 'consulta-control-mas-reciente';
    deferred[1].complete(jsonResponse(newer)); await recent;
    final current = vm.response;
    deferred[0].complete(jsonResponse(page())); await old;
    expect(vm.response, same(current));
    expect(vm.response!.raw['consulta']['identificador'], 'consulta-control-mas-reciente');
  });

  runCase('periodo_invalido', () async {
    var calls = 0;
    final vm = create((_) async { calls++; return jsonResponse(page()); });
    for (final until in [filters.fromUtc, filters.fromUtc.subtract(const Duration(seconds: 1)),
      filters.fromUtc.add(const Duration(hours: 168, seconds: 1))]) {
      expect(() => vm.apply(HotspotFilters(fromUtc: filters.fromUtc, untilUtc: until)), throwsFormatException);
    }
    expect(calls, 0);
  });

  runCase('territorio_incompatible', () async {
    var calls = 0;
    final vm = create((_) async { calls++; return jsonResponse(page()); });
    expect(() => vm.apply(HotspotFilters(fromUtc: filters.fromUtc,
      untilUtc: filters.untilUtc, provinceId: 'BO0702', municipalityId: 'BO070101')),
      throwsFormatException);
    expect(calls, 0);
    expect(vm.response, isNull);
  });

  runCase('cero_faltante', () async {
    final value = page();
    value['datos'][0]['atributos']['potenciaRadiativa'] = 0;
    value['datos'][1]['atributos']['potenciaRadiativa'] = null;
    value['datos'][1]['faltantes'] = [{'campo': 'potenciaRadiativa', 'motivo': 'Prueba controlada'}];
    final vm = create((_) async => jsonResponse(value));
    await vm.apply(filters);
    expect(vm.response!.records[0].radiantPower, 0);
    expect(vm.response!.records[1].radiantPower, isNull);
    expect(vm.response!.records[1].missing, hasLength(1));
  });

  tearDownAll(() {
    final file = File('../tmp/qa21/movil.json');
    file.parent.createSync(recursive: true);
    file.writeAsStringSync('${const JsonEncoder.withIndent('  ').convert(results)}\n');
  });
}
