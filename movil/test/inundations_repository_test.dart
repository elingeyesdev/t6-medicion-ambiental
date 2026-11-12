import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/inundations/model/inundation_models.dart';
import 'package:geocruz_movil/features/inundations/model/inundations_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  const option = WaterProductOption(
    id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    territoryId: 'BO070101',
    territoryName: 'Santa Cruz',
    referenceDate: '2024-09-01',
    comparisonDate: '2025-03-07',
    state: 'sin_cambios',
  );

  test(
    'un catálogo vacío queda como vacío y no como comparación sin cambios',
    () {
      final catalog = WaterCatalog.fromJson({
        'versionContrato': 'agua-0.1-candidata',
        'estado': 'sin_productos_validados',
        'productos': <Object?>[],
      });

      expect(catalog.products, isEmpty);
    },
  );

  test('una respuesta sin datos conserva null y un cero sigue siendo cero', () {
    final noData = WaterResult.fromJson(
      _result(
        state: 'sin_datos',
        comparableArea: 0,
        fraction: 0,
        persistent: null,
        newWater: null,
        removed: null,
        features: const [],
      ),
    );
    final noChange = WaterResult.fromJson(
      _result(
        state: 'sin_cambios',
        comparableArea: 0.8,
        fraction: 0.8,
        persistent: 0.8,
        newWater: 0,
        removed: 0,
        features: const [],
      ),
    );

    expect(noData.persistentKm2, isNull);
    expect(noData.newKm2, isNull);
    expect(noChange.newKm2, 0);
    expect(noChange.removedKm2, 0);
  });

  test('convierte coordenadas GeoJSON sin invertir latitud y longitud', () {
    final result = WaterResult.fromJson(
      _result(
        state: 'cambio_agua_observado',
        comparableArea: 0.8,
        fraction: 0.8,
        persistent: 0.2,
        newWater: 0.1,
        removed: 0.05,
        features: [
          {
            'type': 'Feature',
            'id': 'figura-1',
            'properties': {'clase': 'agua_nueva'},
            'geometry': {
              'type': 'Polygon',
              'coordinates': [
                [
                  [-63.2, -17.8],
                  [-63.19, -17.8],
                  [-63.19, -17.79],
                  [-63.2, -17.79],
                  [-63.2, -17.8],
                ],
              ],
            },
          },
        ],
      ),
    );

    expect(result.features.single.kind, 'agua_nueva');
    expect(result.features.single.rings.single.first.latitude, -17.8);
    expect(result.features.single.rings.single.first.longitude, -63.2);
  });

  test(
    'rechaza una respuesta que no coincide con el territorio pedido',
    () async {
      final api = _api((request) async {
        expect(request.url.path, '/api/v1/inundaciones/consulta');
        expect(request.url.queryParameters, {
          'territorio': option.territoryId,
          'referencia': option.referenceDate,
          'comparacion': option.comparisonDate,
        });
        return _json(
          _result(
            territoryId: 'BO070102',
            state: 'sin_datos',
            comparableArea: 0,
            fraction: 0,
            persistent: null,
            newWater: null,
            removed: null,
            features: const [],
          ),
        );
      });
      api.bearerToken = 'token-prueba';
      final repository = InundationsRepository(api);

      await expectLater(repository.query(option), throwsFormatException);
    },
  );

  test(
    'usa sesión y rutas del API para cargar catálogo y comparación',
    () async {
      var requests = 0;
      final api = _api((request) async {
        requests++;
        expect(request.headers['authorization'], 'Bearer token-prueba');
        if (request.url.path.endsWith('/catalogo')) {
          return _json({
            'versionContrato': 'agua-0.1-candidata',
            'estado': 'disponible',
            'productos': [
              {
                'id': option.id,
                'territorio': {
                  'identificador': option.territoryId,
                  'nombre': option.territoryName,
                  'alcance': 'recorte_parcial',
                },
                'comparacion': {
                  'referencia': option.referenceDate,
                  'comparacion': option.comparisonDate,
                },
                'estado': option.state,
              },
            ],
          });
        }
        expect(request.url.queryParameters['territorio'], option.territoryId);
        return _json(
          _result(
            state: 'sin_datos',
            comparableArea: 0,
            fraction: 0,
            persistent: null,
            newWater: null,
            removed: null,
            features: const [],
          ),
        );
      });
      api.bearerToken = 'token-prueba';
      final repository = InundationsRepository(api);

      final catalog = await repository.loadCatalog();
      final result = await repository.query(catalog.products.single);

      expect(requests, 2);
      expect(result.state, 'sin_datos');
    },
  );

  test('una respuesta JSON grande se decodifica por la ruta aislada', () async {
    final content = jsonEncode({'datos': List.filled(10000, 'dato de prueba')});
    expect(utf8.encode(content).length, greaterThan(64 * 1024));
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
      client: MockClient(
        (_) async => http.Response(
          content,
          200,
          headers: const {'content-type': 'application/json'},
        ),
      ),
    );

    final payload = await api.getJsonWithProfiling(
      'inundaciones/consulta',
      profileLabel: 'prueba',
    );

    expect((payload as Map<String, dynamic>)['datos'], hasLength(10000));
    api.close();
  });
}

GeoCruzApiClient _api(Future<http.Response> Function(http.Request) handler) =>
    GeoCruzApiClient(
      baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
      client: MockClient(handler),
    );

http.Response _json(Object payload) => http.Response(
  jsonEncode(payload),
  200,
  headers: const {'content-type': 'application/json'},
);

Map<String, Object?> _result({
  String territoryId = 'BO070101',
  required String state,
  required double comparableArea,
  required double fraction,
  required double? persistent,
  required double? newWater,
  required double? removed,
  required List<Object?> features,
}) => {
  'versionContrato': 'agua-0.1-candidata',
  'tipoEvidencia': 'resultado_validado',
  'estado': state,
  'comparacion': {'referencia': '2024-09-01', 'comparacion': '2025-03-07'},
  'territorio': {
    'identificador': territoryId,
    'nombre': 'Santa Cruz',
    'alcance': 'recorte_parcial',
  },
  'fuente': {
    'nombre': 'Prueba local',
    'producto': 'Resultado controlado de test',
    'metodo': 'Solo prueba de cliente',
    'resolucionMetros': 20,
    'escenas': ['A', 'B'],
  },
  'cobertura': {
    'areaEstudiadaKm2': 1,
    'areaComparableKm2': comparableArea,
    'fraccionComparable': fraction,
  },
  'resumen': {
    'aguaPersistenteKm2': persistent,
    'aguaNuevaKm2': newWater,
    'aguaRetiradaKm2': removed,
    'inundacionConfirmada': null,
  },
  'capa': {'type': 'FeatureCollection', 'features': features},
  'advertencias': <String>[],
};
