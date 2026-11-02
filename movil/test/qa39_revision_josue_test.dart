import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/drought/model/drought_models.dart';
import 'package:geocruz_movil/features/drought/model/drought_repository.dart';
import 'package:geocruz_movil/features/drought/view/drought_screen.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_models.dart';
import 'package:geocruz_movil/features/hotspots/view/hotspots_screen.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

Future<void> _advance(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
}

final _captureKey = UniqueKey();

Future<void> _capture(WidgetTester tester, String name) async {
  await tester.pump();
  final boundary =
      tester.renderObject(find.byKey(_captureKey)) as RenderRepaintBoundary;
  final bytes = await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    return data!.buffer.asUint8List();
  });
  expect(bytes, isNotNull, reason: 'No se pudo capturar $name');
  final file = File('${Directory.current.path}/../docs/qa39/evidencias/$name');
  file.parent.createSync(recursive: true);
  file.writeAsBytesSync(bytes!);
}

Widget _app(Widget child) => RepaintBoundary(
      key: _captureKey,
      child: MaterialApp(
        theme: ThemeData(useMaterial3: true, fontFamily: 'Roboto'),
        home: Scaffold(backgroundColor: Colors.white, body: child),
      ),
    );

void _phone(WidgetTester tester) {
  tester.view.physicalSize = const Size(800, 1400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

final testerMessenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

Future<void> _loadFonts() async {
  final dir = Directory(
    r'C:\Users\USER\Documents\flutter_windows_3.38.3-stable\flutter\bin\cache\artifacts\material_fonts',
  );
  Future<void> load(String family, String file) async {
    final bytes = File('${dir.path}/$file').readAsBytesSync();
    final loader = FontLoader(family)
      ..addFont(Future.value(ByteData.view(bytes.buffer)));
    await loader.load();
  }
  await load('Roboto', 'roboto-regular.ttf');
  await load('Roboto', 'roboto-medium.ttf');
  await load('Roboto', 'roboto-bold.ttf');
  await load('MaterialIcons', 'materialicons-regular.otf');
}

void main() {
  setUpAll(_loadFonts);
  setUp(() {
    testerMessenger.setMockMethodCallHandler(
      const MethodChannel('plugins.flutter.io/path_provider'),
      (call) async => Directory.systemTemp.path,
    );
  });
  testWidgets('captura 1: cobertura parcial con detecciones', (tester) async {
    _phone(tester);
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/territorios')) return _json(_catalog());
        if (request.url.path.endsWith('/focos-calor')) {
          final payload = _response(total: 2);
          payload['cobertura'] = {
            'estado': 'parcial',
            'fundamento': 'Falta parte del período.',
          };
          return _json(payload);
        }
        return _json(_boundary());
      }),
    );
    addTearDown(api.close);
    await tester.pumpWidget(
      _app(HotspotsScreen(apiClient: api, showAppBar: false)),
    );
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
    expect(find.text('Resultados con cobertura parcial'), findsOneWidget);
    expect(find.textContaining('Falta parte del período.'), findsOneWidget);
    await _capture(tester, 'movil-focos-cobertura-parcial.png');
  });

  testWidgets('captura 2: fallo posterior con datos anteriores', (tester) async {
    _phone(tester);
    var consultas = 0;
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/territorios')) return _json(_catalog());
        if (request.url.path.endsWith('/focos-calor')) {
          consultas++;
          if (consultas == 1) return _json(_response());
          return _json({'mensaje': 'El conjunto cambió.'}, status: 409);
        }
        return _json(_boundary());
      }),
    );
    addTearDown(api.close);
    await tester.pumpWidget(
      _app(HotspotsScreen(apiClient: api, showAppBar: false)),
    );
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
    await tester.tap(find.byTooltip('Recargar'));
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
    expect(find.text('El conjunto de resultados expiró'), findsOneWidget);
    expect(find.text('Reiniciar consulta'), findsOneWidget);
    expect(find.textContaining('datos anteriores'), findsOneWidget);
    await _capture(tester, 'movil-focos-fallo-posterior.png');
  });

  testWidgets('captura 3: sequías conserva filtros tras error', (tester) async {
    _phone(tester);
    final api = GeoCruzApiClient(baseUrl: Uri.parse('http://localhost/api/v1/'));
    addTearDown(api.close);
    final repository = _Repo(api)
      ..catalog.complete(const [
        TerritoryOption(
          identifier: 'BO0701',
          name: 'Andrés Ibáñez',
          type: 'provincia',
        ),
        TerritoryOption(
          identifier: 'BO070102',
          name: 'Cotoca',
          type: 'municipio',
          parentId: 'BO0701',
        ),
      ]);
    await tester.pumpWidget(
      _app(
        DroughtScreen(
          apiClient: api,
          repository: repository,
          showAppBar: false,
        ),
      ),
    );
    await _advance(tester);
    repository.queries.single.completeError(
      const GeoCruzApiException(
        statusCode: 503,
        message: 'No hay muestra para Cotoca.',
      ),
    );
    await _advance(tester);
    expect(find.text('No hay muestra para Cotoca.'), findsOneWidget);
    expect(find.text('Filtros'), findsOneWidget);
    expect(find.text('Reintentar'), findsOneWidget);
    await _capture(tester, 'movil-sequias-error-filtros.png');
    await tester.tap(find.text('Filtros'));
    await _advance(tester);
    expect(find.text('Filtros de sequías'), findsOneWidget);
    await _capture(tester, 'movil-sequias-error-cambiar-consulta.png');
  });
}

class _Repo extends DroughtRepository {
  _Repo(super.apiClient);

  final catalog = Completer<List<TerritoryOption>>();
  final queries = <Completer<DroughtResponse>>[];

  @override
  Future<List<TerritoryOption>> loadTerritories() => catalog.future;

  @override
  Future<DroughtResponse> query(DroughtFilters value) {
    final pending = Completer<DroughtResponse>();
    queries.add(pending);
    return pending.future;
  }
}

Map<String, dynamic> _catalog() => {
  'datos': [
    {'identificador': 'BO07', 'tipo': 'departamento', 'nombre': 'Santa Cruz'},
    {
      'identificador': 'BO0701',
      'tipo': 'provincia',
      'nombre': 'Andrés Ibáñez',
      'padre': 'BO07',
    },
    {
      'identificador': 'BO070101',
      'tipo': 'municipio',
      'nombre': 'Santa Cruz de la Sierra',
      'padre': 'BO0701',
    },
  ],
  'paginacion': {
    'pagina': 1,
    'tamanioPagina': 500,
    'total': 3,
    'siguiente': null,
  },
};

Map<String, dynamic> _response({int total = 1}) => {
  'versionContrato': '1.0-m2-candidata',
  'datos': [
    {
      'identificador': 'firms-prueba-001',
      'geometria': {
        'type': 'Point',
        'coordinates': [-63.18, -16.5],
      },
      'fechas': {'observadoDesde': '2026-10-17T18:30:00Z'},
      'naturaleza': 'observacion',
      'fuentesRef': ['nasa-firms-snpp-nrt'],
      'metodo': null,
      'atributos': {
        'satelite': 'N',
        'instrumento': 'VIIRS',
        'confianzaOriginal': 'n',
        'confianza': 'nominal',
        'potenciaRadiativa': 0,
        'unidadPotenciaRadiativa': 'MW',
        'versionOriginal': '2.0NRT',
      },
      'faltantes': [],
      'territorio': {
        'identificador': 'BO070101',
        'tipo': 'municipio',
        'departamento': 'BO07',
        'provincia': 'BO0701',
        'municipio': 'BO070101',
        'versionCatalogo': 'catalogo-prueba-v1',
      },
    },
  ],
  'consulta': {
    'identificador': 'consulta-prueba-001',
    'modulo': 'focos-calor',
    'modo': 'externo',
    'territorio': {
      'identificador': 'BO070101',
      'tipo': 'municipio',
      'departamento': 'BO07',
      'provincia': 'BO0701',
      'municipio': 'BO070101',
      'versionCatalogo': 'catalogo-prueba-v1',
    },
    'periodo': {
      'desde': '2026-10-17T04:00:00Z',
      'hasta': '2026-10-18T04:00:00Z',
      'regla': 'final_excluido',
    },
    'fechaConsulta': '2026-10-18T06:22:59Z',
    'obtencion': {
      'tipo': 'consulta_proveedor',
      'fechaRecuperacion': '2026-10-18T06:22:59Z',
      'fechaActualizacion': null,
      'antiguedadSegundos': 0,
      'expiraEn': '2026-10-18T06:37:59Z',
    },
  },
  'fuentes': [
    {
      'identificador': 'nasa-firms-snpp-nrt',
      'proveedor': 'NASA FIRMS',
      'producto': 'VIIRS_SNPP_NRT',
      'version': '2.0NRT',
      'naturaleza': 'observacion',
    },
  ],
  'resumen': {
    'totalRegistros': total,
    'registrosValidos': total,
    'registrosConFaltantes': 0,
    'indicadores': [
      {
        'codigo': 'totalFocos',
        'valor': total,
        'unidad': 'registros',
        'registrosValidos': total,
        'metodo': 'conteo',
        'naturaleza': 'observacion',
      },
      {
        'codigo': 'focosConfianzaAlta',
        'valor': 0,
        'unidad': 'registros',
        'registrosValidos': total,
        'metodo': 'conteo_confianza_alta',
        'naturaleza': 'observacion',
      },
      {
        'codigo': 'potenciaMaxima',
        'valor': 0,
        'unidad': 'MW',
        'registrosValidos': total,
        'metodo': 'maximo',
        'naturaleza': 'observacion',
      },
    ],
  },
  'cobertura': {'estado': 'desconocida', 'fundamento': 'Prueba controlada.'},
  'advertencias': [],
  'paginacion': {
    'pagina': 1,
    'tamanioPagina': 25,
    'total': total,
    'siguiente': null,
    'versionConjunto': 'version-conjunto-prueba-1',
  },
  'representacionMapa': {'alcance': 'pagina'},
};

Map<String, dynamic> _boundary() => {
  'territorio': {'identificador': 'BO070101'},
  'limite': {
    'type': 'Feature',
    'geometry': {
      'type': 'Polygon',
      'coordinates': [
        [
          [-63.3, -16.6],
          [-63.0, -16.6],
          [-63.0, -16.4],
          [-63.3, -16.4],
          [-63.3, -16.6],
        ],
      ],
    },
  },
  'procedencia': {'version': 'catalogo-prueba-v1'},
};

http.Response _json(Map<String, dynamic> body, {int status = 200}) =>
    http.Response(
      jsonEncode(body),
      status,
      headers: const {'content-type': 'application/json'},
    );
