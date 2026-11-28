import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/drought/view/drought_screen.dart';
import 'package:geocruz_movil/features/forestal/view/forestal_screen.dart';
import 'package:geocruz_movil/features/hotspots/view/hotspots_screen.dart';
import 'package:geocruz_movil/features/inundations/view/inundations_screen.dart';
import 'package:geocruz_movil/features/monitoring/view/air_sheet.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:latlong2/latlong.dart';

import 'fixtures/drought_response.dart';
import 'fixtures/mov09_responses.dart';

/// Los casos excepcionales son respuestas controladas, no datos de producción.
void main({Future<void> Function(String name)? capture}) {
  for (final module in [
    'Focos',
    'Sequías',
    'Inundaciones',
    'Forestal',
    'Aire',
  ]) {
    for (final scenario in ['normal', 'recuperación', 'sesión vencida']) {
      testWidgets('MOV-09 $module: $scenario', (tester) async {
        tester.view.physicalSize = const Size(900, 1600);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        var status = scenario == 'normal'
            ? 200
            : scenario == 'recuperación'
            ? 503
            : 401;
        var queries = 0;
        var unauthorized = 0;
        const productId =
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        final api = GeoCruzApiClient(
          baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
          client: MockClient((request) async {
            final path = request.url.path;
            if (path.endsWith('/territorios')) return _json(hotspotCatalog());
            if (path.contains('/limite')) return _json(hotspotBoundary());
            if (path.endsWith('/catalogo')) {
              return _json({
                'versionContrato': 'agua-0.1-candidata',
                'estado': 'disponible',
                'productos': [
                  waterProduct(
                    id: productId,
                    territoryId: 'BO070101',
                    territoryName: 'Santa Cruz',
                    referenceDate: '2025-12-06',
                    comparisonDate: '2025-12-18',
                  ),
                ],
              });
            }
            queries++;
            if (status != 200) {
              return _json({
                'error': {
                  'codigo': 'PRUEBA_CONTROLADA',
                  'mensaje': 'Proveedor temporalmente no disponible.',
                },
              }, status);
            }
            return _json(_payload(module));
          }),
        )..bearerToken = 'token-exclusivo-de-prueba';
        addTearDown(api.close);
        void expired() => unauthorized++;
        final screen = switch (module) {
          'Focos' => HotspotsScreen(
            apiClient: api,
            onUnauthorized: expired,
            showAppBar: false,
          ),
          'Sequías' => DroughtScreen(
            apiClient: api,
            initialMonth: '2024-09',
            onUnauthorized: expired,
            showAppBar: false,
          ),
          'Inundaciones' => InundationsScreen(
            api: api,
            onUnauthorized: expired,
            showAppBar: false,
          ),
          'Forestal' => ForestalScreen(
            api: api,
            onUnauthorized: expired,
            showAppBar: false,
          ),
          _ => AirSheet(
            api: api,
            point: const LatLng(-17.7833, -63.1812),
            onUnauthorized: expired,
          ),
        };
        await tester.pumpWidget(MaterialApp(home: Scaffold(body: screen)));
        await _advance(tester);
        if (scenario == 'sesión vencida') {
          expect(unauthorized, 1);
        } else {
          if (scenario == 'recuperación') {
            expect(queries, 1);
            expect(find.textContaining('1,915 ha'), findsNothing);
              await capture?.call('$module-error-temporal');
            status = 200;
            final retry = find.text('Reintentar');
            await tester.ensureVisible(retry);
            await tester.tap(retry);
            await _advance(tester);
            expect(queries, 2);
          }
          switch (module) {
            case 'Focos':
              expect(
                find.textContaining('57 detecciones · 1 en esta página'),
                findsOneWidget,
              );
              expect(
                find.text('Resultados con cobertura parcial'),
                findsOneWidget,
              );
              await tester.tap(find.text('Datos de la consulta'));
              await _advance(tester);
              expect(find.text('3,76'), findsOneWidget);
              expect(find.text('FRP máx (MW)'), findsOneWidget);
            case 'Sequías':
              expect(
                find.text('1 muestra · 1 de 56 municipios'),
                findsOneWidget,
              );
              await tester.tap(find.text('Datos de la consulta'));
              await _advance(tester);
              expect(
                find.textContaining('Cobertura parcial: 1 de 56 municipios'),
                findsOneWidget,
              );
            case 'Inundaciones':
              expect(find.text('Sin cambios observados'), findsOneWidget);
              expect(
                find.textContaining('Cobertura parcial: el cambio de agua'),
                findsOneWidget,
              );
              expect(find.text('1,000 km²'), findsWidgets);
            case 'Forestal':
              expect(find.text('1,915 ha'), findsOneWidget);
              expect(find.textContaining('26 polígonos'), findsOneWidget);
              expect(
                find.textContaining('Cobertura parcial: 50'),
                findsOneWidget,
              );
            case 'Aire':
              expect(find.text('37 EAQI'), findsOneWidget);
              expect(find.text('13.6 µg/m³'), findsOneWidget);
              expect(
                find.textContaining('Cobertura parcial: algunas variables'),
                findsOneWidget,
              );
              final previousQueries = queries;
              await tester.ensureVisible(find.text('Actualizar'));
              await tester.tap(find.text('Actualizar'));
              await _advance(tester);
              expect(queries, previousQueries + 1);
          }
          expect(unauthorized, 0);
        }
        expect(tester.takeException(), isNull);
          await capture?.call('$module-$scenario');
        await tester.pumpWidget(const SizedBox.shrink());
        await tester.pump();
      });
    }
  }
}

Future<void> _advance(WidgetTester tester) async {
  for (var i = 0; i < 15; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Object _payload(String module) {
  switch (module) {
    case 'Focos':
      return hotspotResponse(total: 57, nextPage: 2, radiantPower: 3.76)
        ..['cobertura'] = {'estado': 'parcial'};
    case 'Sequías':
      return droughtResponse();
    case 'Inundaciones':
      final data = waterResult(
        territoryId: 'BO070101',
        territoryName: 'Santa Cruz',
        referenceDate: '2025-12-06',
        comparisonDate: '2025-12-18',
      );
      data['cobertura'] = {
        'areaEstudiadaKm2': 1,
        'areaComparableKm2': 0.8,
        'fraccionComparable': 0.8,
      };
      data['resumen'] = {
        'aguaPersistenteKm2': 0.8,
        'aguaNuevaKm2': 0,
        'aguaRetiradaKm2': 0,
        'inundacionConfirmada': null,
      };
      return data;
    case 'Forestal':
      final data = forestResult();
      final summary = data['resumen'] as Map;
      summary['total_periodo_ha'] = '1.91489180';
      summary['conteos_pixeles'] = {'perdida_periodo': 26};
      summary['cobertura_observaciones_fraccion'] = 0.5;
      return data;
    default:
      return {
        'actual': {
          'instante': '2026-11-25T03:00:00Z',
          'indice': 37,
          'pm25': 13.6,
        },
        'variables': {
          'indice': {'unidad': 'EAQI'},
          'pm25': {'unidad': 'µg/m³'},
        },
        'horas': [],
      };
  }
}

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: const {'content-type': 'application/json'},
);
