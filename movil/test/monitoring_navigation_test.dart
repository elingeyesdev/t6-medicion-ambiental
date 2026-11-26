import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/monitoring/view/monitoring_screen.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_models.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets(
    'fijar referencia, comparar desde la lista y quitar comparación',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = GeoCruzApiClient(
        baseUrl: Uri.parse('http://localhost/api/v1/'),
        client: MockClient((_) async => http.Response('{"capas":[]}', 200)),
      )..bearerToken = 'prueba';
      addTearDown(api.close);
      await tester.pumpWidget(
        MaterialApp(
          home: MonitoringScreen(
            api: api,
            repository: _ComparisonRepository(api),
            onUnauthorized: () {},
          ),
        ),
      );
      Future<void> advance() async {
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 500));
      }

      final scrollable = find
          .descendant(
            of: find.byKey(const PageStorageKey('monitoring-scroll')),
            matching: find.byType(Scrollable),
          )
          .first;
      Future<void> tap(Finder finder, {double step = 300}) async {
        if (finder.evaluate().isEmpty) {
          await tester.scrollUntilVisible(finder, step, scrollable: scrollable);
        }
        await tester.ensureVisible(finder);
        await advance();
        await tester.tap(finder);
        await advance();
      }

      await advance();
      await tap(find.text('Puntos'));
      await tap(find.byKey(const ValueKey('monitoring-point-A')));
      await tap(find.byKey(const Key('monitoring-pin-reference')));
      expect(find.text('Referencia: Cuiabá'), findsOneWidget);
      await tap(find.byKey(const ValueKey('monitoring-point-B')));
      await tester.ensureVisible(
        find.byKey(const Key('monitoring-comparison')),
      );
      await advance();
      expect(find.text('Cuiabá → Santa Cruz'), findsOneWidget);
      expect(find.textContaining('-3,7 °C'), findsOneWidget);
      await tap(find.byKey(const Key('monitoring-clear-comparison')));
      expect(find.byKey(const Key('monitoring-comparison')), findsNothing);
      expect(find.text('Referencia: Cuiabá'), findsNothing);
      expect(find.text('Fijar para comparar'), findsOneWidget);
      expect(
        find.byKey(const Key('monitoring-selected-point')),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump();
    },
  );

  testWidgets(
    'cambiar país limpia la región y permite elegir simulados explícitamente',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      Future<void> advance() async {
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 400));
      }

      final queries = <Uri>[];
      final api = GeoCruzApiClient(
        baseUrl: Uri.parse('http://localhost/api/v1/'),
        client: MockClient((request) async {
          queries.add(request.url);
          return http.Response(
            jsonEncode({
              'metrica': 'temperatura',
              'unidad': '°C',
              'desde': request.url.queryParameters['desde'],
              'hasta': request.url.queryParameters['hasta'],
              'datos': [],
              'total': 0,
              'pagina': 1,
              'ultimaPagina': 1,
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      )..bearerToken = 'token-de-prueba';
      addTearDown(api.close);
      await tester.pumpWidget(
        MaterialApp(
          home: MonitoringScreen(
            api: api,
            repository: _CatalogRepository(api),
            onUnauthorized: () {},
          ),
        ),
      );
      await advance();
      expect(queries, isNotEmpty);
      await tester.tap(find.text('Filtros'));
      await advance();

      await tester.tap(find.byKey(const Key('monitoring-country')));
      await advance();
      await tester.tap(find.text('Bolivia').last);
      await advance();
      await tester.tap(find.byKey(const ValueKey('monitoring-region-BO')));
      await advance();
      await tester.tap(find.text('Santa Cruz').last);
      await advance();
      await tester.tap(find.byKey(const Key('monitoring-country')));
      await advance();
      await tester.tap(find.text('Argentina').last);
      await advance();
      expect(tester.takeException(), isNull);
      expect(
        find.byKey(const ValueKey('monitoring-region-AR')),
        findsOneWidget,
      );

      await tester.tap(find.byKey(const Key('monitoring-origin')));
      await advance();
      await tester.tap(find.text('Solo simulados').last);
      await advance();
      await tester.tap(find.text('Consultar mapa'));
      await advance();
      expect(queries.last.queryParameters['pais'], 'AR');
      expect(queries.last.queryParameters.containsKey('region'), isFalse);
      expect(queries.last.queryParameters['origen'], 'simulado');
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump();
    },
  );
}

class _CatalogRepository extends MonitoringRepository {
  const _CatalogRepository(super.api);

  @override
  Future<List<CountryOption>> loadCountries() async => const [
    CountryOption('BO', 'Bolivia'),
    CountryOption('AR', 'Argentina'),
  ];

  @override
  Future<List<RegionOption>> loadRegions() async => const [
    RegionOption(code: 'BO-SCZ', country: 'BO', name: 'Santa Cruz'),
    RegionOption(code: 'AR-BA', country: 'AR', name: 'Buenos Aires'),
  ];
}

class _ComparisonRepository extends _CatalogRepository {
  const _ComparisonRepository(super.api);

  @override
  Future<MonitoringResponse> query(MonitoringFilters filters) async =>
      MonitoringResponse.fromJson({
        'metrica': 'temperatura',
        'unidad': '°C',
        'desde': formatApiInstant(filters.from),
        'hasta': formatApiInstant(filters.until),
        'datos': [
          for (final item in [('A', 'Cuiabá', 29.4), ('B', 'Santa Cruz', 25.7)])
            {
              'codigo': item.$1,
              'nombre': item.$2,
              'pais': 'BO',
              'latitud': -17.78,
              'longitud': -63.18,
              'tipo': 'referencia',
              'lecturas': [
                {
                  'valor': item.$3,
                  'instante': '2026-11-26T21:30:00Z',
                  'origen': 'estimado',
                  'fuente': 'open-meteo-modelo',
                },
              ],
            },
        ],
        'total': 2,
        'pagina': 1,
        'ultimaPagina': 1,
      });
}
