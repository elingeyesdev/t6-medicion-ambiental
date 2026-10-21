import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/hotspots/view_model/hotspots_view_model.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_models.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('MOV-02 contrato de focos', () {
    test('carga catálogo, consulta y límite territorial de la API', () async {
      final requests = <http.Request>[];
      final api = GeoCruzApiClient(
        baseUrl: Uri.parse('http://localhost/api/v1/'),
        client: MockClient((request) async {
          requests.add(request);
          if (request.url.path.endsWith('/territorios')) {
            return _json(_catalog());
          }
          if (request.url.path.endsWith('/focos-calor')) {
            return _json(_response());
          }
          if (request.url.path.endsWith('/limite')) {
            return _json(_boundary());
          }
          return http.Response('{}', 404);
        }),
      );
      final controller = HotspotsViewModel(HotspotRepository(api));
      controller.filters = HotspotFilters(
        fromUtc: DateTime.utc(2026, 10, 17, 4),
        untilUtc: DateTime.utc(2026, 10, 18, 4),
        provinceId: 'BO0701',
        municipalityId: 'BO070101',
      );

      await controller.initialize();

      expect(controller.territories.length, 3);
      expect(controller.response?.total, 1);
      expect(
        controller.response?.records.single.identifier,
        'firms-prueba-001',
      );
      expect(controller.response?.records.single.coordinates?.latitude, -16.5);
      expect(controller.boundary?.identifier, 'BO070101');
      expect(controller.boundary?.polygons, hasLength(1));
      expect(controller.mapError, isNull);
      final query = requests
          .singleWhere((request) => request.url.path.endsWith('/focos-calor'))
          .url
          .queryParameters;
      expect(query['modo'], 'externo');
      expect(query['desde'], '2026-10-17T04:00:00Z');
      expect(query['hasta'], '2026-10-18T04:00:00Z');
      expect(query['provincia'], 'BO0701');
      expect(query['municipio'], 'BO070101');
      expect(query['tamanioPagina'], '25');
      expect(query.containsKey('versionConjunto'), isFalse);
      controller.dispose();
      api.close();
    });

    test(
      'rechaza período mayor a 168 horas y territorio incompatible',
      () async {
        final api = GeoCruzApiClient(
          baseUrl: Uri.parse('http://localhost/api/v1/'),
          client: MockClient((_) async => _json(_catalog())),
        );
        final controller = HotspotsViewModel(HotspotRepository(api));
        controller.territories = _catalog()['datos']
            .map<TerritoryOption>(TerritoryOption.fromJson)
            .toList();

        expect(
          () => controller.apply(
            HotspotFilters(
              fromUtc: DateTime.utc(2026, 10, 1),
              untilUtc: DateTime.utc(2026, 10, 8, 0, 0, 1),
              provinceId: 'BO0701',
              municipalityId: 'BO070101',
            ),
          ),
          throwsFormatException,
        );
        expect(
          () => controller.apply(
            HotspotFilters(
              fromUtc: DateTime.utc(2026, 10, 1),
              untilUtc: DateTime.utc(2026, 10, 2),
              provinceId: 'BO0701',
              municipalityId: 'BO070201',
            ),
          ),
          throwsFormatException,
        );
        controller.dispose();
        api.close();
      },
    );

    test(
      'retiene resultados y propone reiniciar al expirar el conjunto',
      () async {
        var pageOne = true;
        final captured = <Uri>[];
        final api = GeoCruzApiClient(
          baseUrl: Uri.parse('http://localhost/api/v1/'),
          client: MockClient((request) async {
            captured.add(request.url);
            if (request.url.path.endsWith('/territorios')) {
              return _json(_catalog());
            }
            if (request.url.path.endsWith('/focos-calor')) {
              if (request.url.queryParameters['pagina'] == '1' && pageOne) {
                pageOne = false;
                return _json(_response(nextPage: 2, total: 26));
              }
              if (request.url.queryParameters['versionConjunto'] != null) {
                return _json({
                  'error': {
                    'codigo': 'CONJUNTO_CAMBIADO',
                    'mensaje': 'El conjunto expiró.',
                  },
                }, status: 409);
              }
              return _json(_response());
            }
            return _json(_boundary());
          }),
        );
        final controller = HotspotsViewModel(HotspotRepository(api));
        await controller.initialize();

        await controller.nextPage();
        expect(controller.queryExpired, isTrue);
        expect(controller.response?.page, 1);
        expect(controller.response?.total, 26);

        await controller.restartExpiredQuery();
        expect(controller.queryExpired, isFalse);
        expect(controller.response?.page, 1);
        expect(controller.queryError, isNull);
        final expiredRequest = captured.lastWhere(
          (uri) => uri.queryParameters['versionConjunto'] != null,
        );
        expect(expiredRequest.queryParameters['pagina'], '2');
        final restartedRequest = captured.lastWhere(
          (uri) => uri.path.endsWith('/focos-calor'),
        );
        expect(restartedRequest.queryParameters['pagina'], '1');
        expect(
          restartedRequest.queryParameters.containsKey('versionConjunto'),
          isFalse,
        );
        controller.dispose();
        api.close();
      },
    );

    test('mantiene cero de FRP y conserva confianza original/naturaleza', () {
      final response = HotspotResponse.fromJson(
        _response(radiantPower: 0),
        expectedPage: 1,
      );
      final record = response.records.single;
      expect(record.radiantPower, 0);
      expect(record.confidence, 'nominal');
      expect(record.originalConfidence, 'n');
      expect(record.nature, 'observacion');
      expect(response.indicator('potenciaMaxima')?['unidad'], 'MW');
    });
  });
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

Map<String, dynamic> _response({
  int? nextPage,
  int total = 1,
  num? radiantPower = 0,
}) => {
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
        'potenciaRadiativa': radiantPower,
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
        'valor': radiantPower,
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
    'pagina': nextPage == null ? 1 : 1,
    'tamanioPagina': 25,
    'total': total,
    'siguiente': nextPage,
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
