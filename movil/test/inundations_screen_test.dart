import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/inundations/model/inundations_repository.dart';
import 'package:geocruz_movil/features/inundations/view/inundations_screen.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets(
    'al cambiar de lugar el filtro reemplaza las fechas por las del lugar elegido',
    (tester) async {
      const santaCruzId =
          'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
      const warnesId =
          'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
      final api = GeoCruzApiClient(
        baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/catalogo')) {
            return _json({
              'versionContrato': 'agua-0.1-candidata',
              'estado': 'disponible',
              'productos': [
                _product(
                  id: santaCruzId,
                  territoryId: 'BO070101',
                  territoryName: 'Santa Cruz',
                  referenceDate: '2025-01-01',
                  comparisonDate: '2026-02-02',
                ),
                _product(
                  id: warnesId,
                  territoryId: 'BO070102',
                  territoryName: 'Warnes',
                  referenceDate: '2023-01-02',
                  comparisonDate: '2024-03-04',
                ),
              ],
            });
          }
          final territory = request.url.queryParameters['territorio']!;
          final reference = request.url.queryParameters['referencia']!;
          final comparison = request.url.queryParameters['comparacion']!;
          return _json(
            _result(
              territoryId: territory,
              territoryName: territory == 'BO070101' ? 'Santa Cruz' : 'Warnes',
              referenceDate: reference,
              comparisonDate: comparison,
            ),
          );
        }),
      )..bearerToken = 'token-prueba';

      await tester.pumpWidget(
        MaterialApp(
          home: InundationsScreen(
            api: api,
            repository: InundationsRepository(api),
            onUnauthorized: () {},
            showAppBar: false,
          ),
        ),
      );
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pump(const Duration(milliseconds: 100));

      await tester.tap(find.byTooltip('Filtros'));
      await tester.pumpAndSettle();
      expect(
        find.descendant(
          of: find.byKey(ValueKey('water-comparison-$santaCruzId')),
          matching: find.text('01/01/2025 → 02/02/2026'),
        ),
        findsWidgets,
      );

      await tester.tap(
        find.byKey(const ValueKey('water-place-BO070101')).last,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Warnes').last);
      await tester.pumpAndSettle();

      expect(
        find.descendant(
          of: find.byKey(ValueKey('water-comparison-$warnesId')),
          matching: find.text('02/01/2023 → 04/03/2024'),
        ),
        findsWidgets,
      );
      api.close();
    },
  );
}

http.Response _json(Object value) => http.Response(
  jsonEncode(value),
  200,
  headers: const {'content-type': 'application/json'},
);

Map<String, Object?> _product({
  required String id,
  required String territoryId,
  required String territoryName,
  required String referenceDate,
  required String comparisonDate,
}) => {
  'id': id,
  'territorio': {
    'identificador': territoryId,
    'nombre': territoryName,
    'alcance': 'recorte_parcial',
  },
  'comparacion': {'referencia': referenceDate, 'comparacion': comparisonDate},
  'estado': 'sin_cambios',
};

Map<String, Object?> _result({
  required String territoryId,
  required String territoryName,
  required String referenceDate,
  required String comparisonDate,
}) => {
  'versionContrato': 'agua-0.1-candidata',
  'tipoEvidencia': 'resultado_validado',
  'estado': 'sin_cambios',
  'comparacion': {'referencia': referenceDate, 'comparacion': comparisonDate},
  'territorio': {
    'identificador': territoryId,
    'nombre': territoryName,
    'alcance': 'recorte_parcial',
  },
  'fuente': {
    'nombre': 'Prueba local',
    'producto': 'Resultado controlado',
    'metodo': 'Solo prueba',
    'resolucionMetros': 20,
  },
  'cobertura': {
    'areaEstudiadaKm2': 1,
    'areaComparableKm2': 1,
    'fraccionComparable': 1,
  },
  'resumen': {
    'aguaPersistenteKm2': 1,
    'aguaNuevaKm2': 0,
    'aguaRetiradaKm2': 0,
    'inundacionConfirmada': null,
  },
  'capa': {'type': 'FeatureCollection', 'features': <Object?>[]},
  'advertencias': <String>[],
};
