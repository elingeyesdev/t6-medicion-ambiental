import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/core/theme/geocruz_theme.dart';
import 'package:geocruz_movil/features/modules/model/ambient_module.dart';
import 'package:geocruz_movil/features/modules/view/modules_screen.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_models.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('solo ofrece el análisis móvil que ya está integrado', (
    tester,
  ) async {
    AmbientModule? selected;
    await tester.pumpWidget(
      MaterialApp(
        theme: GeoCruzTheme.light(),
        home: ModulesScreen(onModuleSelected: (module) => selected = module),
      ),
    );

    expect(find.text('Focos de calor'), findsOneWidget);
    expect(find.text('Sequías'), findsNothing);
    expect(find.text('Inundaciones'), findsNothing);
    expect(find.text('Deforestación'), findsNothing);
    expect(find.text('Calidad del aire'), findsNothing);
    expect(find.text('Próximamente'), findsNothing);
    await tester.tap(find.text('Focos de calor'));
    expect(selected?.id, 'focos-calor');
  });

  test('filtros del mapa siguen el contrato común de GeoSur', () {
    final query = MonitoringFilters(
      country: 'BO',
      region: 'BO-SCZ',
      metric: 'humedad',
      origin: 'estimado',
      from: DateTime.utc(2026, 10, 20),
      until: DateTime.utc(2026, 10, 27),
      page: 2,
    ).toQuery();

    expect(query, {
      'pais': 'BO',
      'region': 'BO-SCZ',
      'metrica': 'humedad',
      'origen': 'estimado',
      'desde': '2026-10-20T00:00:00Z',
      'hasta': '2026-10-27T00:00:00Z',
      'pagina': '2',
    });
  });

  test('el mapa conserva puntos vacíos y procedencia sin inventar ceros', () {
    final response = MonitoringResponse.fromJson({
      'metrica': 'temperatura',
      'unidad': '°C',
      'desde': '2026-10-20T00:00:00Z',
      'hasta': '2026-10-27T00:00:00Z',
      'origen': 'sin-simulaciones',
      'datos': [
        {
          'codigo': 'punto-vacio',
          'nombre': 'Sin lecturas',
          'pais': 'BO',
          'latitud': -17.7,
          'longitud': -63.2,
          'tipo': 'referencia',
          'lecturas': [],
        },
        {
          'codigo': 'punto-cero',
          'nombre': 'Lectura observada',
          'pais': 'BO',
          'latitud': -17.8,
          'longitud': -63.3,
          'tipo': 'estacion',
          'lecturas': [
            {
              'valor': 0,
              'instante': '2026-10-26T12:00:00Z',
              'origen': 'observado',
              'fuente': 'prueba-controlada',
              'latitud': -17.8,
              'longitud': -63.3,
            },
          ],
        },
      ],
      'pagina': 1,
      'ultimaPagina': 1,
      'total': 2,
      'aviso': 'Puntos de referencia.',
      'limite': null,
    });

    expect(response.points[0].hasData, isFalse);
    expect(response.points[0].readings, isEmpty);
    expect(response.points[1].readings.single.value, 0);
    expect(response.points[1].readings.single.source, 'prueba-controlada');
    expect(response.points[1].readings.single.origin, 'observado');
  });

  test('repositorio carga catálogos y envía token y filtros al mapa', () async {
    http.Request? captured;
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((request) async {
        captured = request;
        return http.Response(
          jsonEncode({
            'metrica': 'temperatura',
            'unidad': '°C',
            'desde': '2026-10-20T00:00:00Z',
            'hasta': '2026-10-27T00:00:00Z',
            'origen': 'sin-simulaciones',
            'datos': <Object>[],
            'pagina': 1,
            'ultimaPagina': 1,
            'total': 0,
            'aviso': '',
            'limite': null,
          }),
          200,
          headers: const {'content-type': 'application/json'},
        );
      }),
    )..bearerToken = 'test-token';
    final repository = MonitoringRepository(api);

    expect(
      (await repository.loadCountries()).any((country) => country.code == 'BO'),
      isTrue,
    );
    expect(await repository.loadRegions(), isNotEmpty);
    await repository.query(
      MonitoringFilters(
        country: 'BO',
        metric: 'temperatura',
        from: DateTime.utc(2026, 10, 20),
        until: DateTime.utc(2026, 10, 27),
      ),
    );

    expect(captured?.url.path, '/api/v1/mapa/puntos');
    expect(captured?.url.queryParameters['pais'], 'BO');
    expect(captured?.headers['authorization'], 'Bearer test-token');
    api.close();
  });
}
