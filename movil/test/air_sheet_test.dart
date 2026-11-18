import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/monitoring/view/air_sheet.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:latlong2/latlong.dart';

void main() {
  testWidgets('Aire muestra la fecha del dato y el historial en hora de Bolivia', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1024, 1024);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final response = Completer<http.Response>();
    final api = _airApi((request) {
      expect(request.url.path, '/api/v1/aire');
      expect(request.url.queryParameters, {
        'latitud': '-17.7833',
        'longitud': '-63.1812',
      });
      return response.future;
    });

    await tester.pumpWidget(_screen(api));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    response.complete(
      _json({
        'actual': {
          'instante': '2026-11-11T04:00:00Z',
          'indice': 28,
          'pm25': null,
          'pm10': 0,
        },
        'horas': [
          {'instante': '2026-11-10T23:00:00Z', 'indice': 27},
          {'instante': 'sin-fecha', 'indice': 26},
        ],
        'variables': {
          'indice': {'unidad': 'EAQI'},
          'pm10': {'unidad': 'µg/m³'},
        },
      }),
    );
    await tester.pumpAndSettle();

    expect(find.text('Santa Cruz'), findsOneWidget);
    expect(find.text('Actualizado 11/11/2026 00:00 · Bolivia'), findsOneWidget);
    expect(find.text('10/11/2026 19:00'), findsOneWidget);
    expect(find.text('Sin fecha'), findsOneWidget);
    expect(find.text('Sin dato'), findsWidgets);
    expect(find.text('0 µg/m³'), findsOneWidget);
  });

  testWidgets('Aire permite recuperar la consulta después de un error de la API', (
    tester,
  ) async {
    var attempts = 0;
    final api = _airApi((request) async {
      expect(request.url.queryParameters['latitud'], '-17.7833');
      expect(request.url.queryParameters['longitud'], '-63.1812');
      attempts++;
      if (attempts == 2) {
        return _json({
          'actual': {'indice': 28},
          'variables': {
            'indice': {'unidad': 'EAQI'},
          },
        });
      }
      return _json({
        'error': {'mensaje': 'La calidad del aire no está disponible.'},
      }, 503);
    });

    await tester.pumpWidget(_screen(api));
    await tester.pumpAndSettle();

    expect(find.text('La calidad del aire no está disponible.'), findsOneWidget);
    expect(find.text('Últimas horas'), findsNothing);
    expect(attempts, 1);

    await tester.tap(find.text('Reintentar'));
    await tester.pumpAndSettle();

    expect(attempts, 2);
    expect(find.text('28 EAQI'), findsOneWidget);
    expect(find.text('Reintentar'), findsNothing);
  });
}

GeoCruzApiClient _airApi(
  Future<http.Response> Function(http.Request) handler,
) {
  final api = GeoCruzApiClient(
    baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
    client: MockClient(handler),
  )..bearerToken = 'token-prueba';
  addTearDown(api.close);
  return api;
}

Widget _screen(GeoCruzApiClient api) => MaterialApp(
  home: Scaffold(
    body: AirSheet(
      api: api,
      point: const LatLng(-17.7833, -63.1812),
      title: 'Santa Cruz',
    ),
  ),
);

http.Response _json(Object data, [int status = 200]) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);
