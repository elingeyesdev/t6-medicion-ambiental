import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/forestal/view/forestal_screen.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets(
    'MOV-08 mapa y lista comparten la ficha y límites del piloto forestal',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = GeoCruzApiClient(
        baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
        client: MockClient((_) async => _json(_forestResult())),
      )..bearerToken = 'token-prueba';
      addTearDown(api.close);

      await tester.pumpWidget(
        MaterialApp(
          home: ForestalScreen(
            api: api,
            onUnauthorized: () {},
            showAppBar: false,
          ),
        ),
      );
      for (var i = 0; i < 12; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }

      final listButton = find.text('Ver lista de polígonos');
      await tester.ensureVisible(listButton);
      await tester.tap(listButton);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Polígono 1').last);
      await tester.pumpAndSettle();

      expect(find.text('Detalle · Polígono 1'), findsOneWidget);
      expect(find.text('forestal-2025-1'), findsOneWidget);
      expect(find.text('Santa Rosa'), findsWidgets);
      expect(find.text('2024–2025'), findsWidgets);
      expect(find.text('0,074 ha'), findsWidgets);
      expect(
        find.text('Hansen Global Forest Change · GFC-2025-v1.13'),
        findsOneWidget,
      );
      expect(find.text('Piloto técnico'), findsOneWidget);
      expect(find.text('hectáreas (ha)'), findsOneWidget);
      expect(find.textContaining('requiere verificación'), findsWidgets);
    },
  );

  testWidgets('MOV-08 una sesión vencida vuelve al acceso', (tester) async {
    var unauthorized = false;
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('https://ejemplo.invalid/api/v1/'),
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({'message': 'Sesión vencida'}),
          401,
          headers: const {'content-type': 'application/json'},
        ),
      ),
    )..bearerToken = 'token-vencido';
    addTearDown(api.close);

    await tester.pumpWidget(
      MaterialApp(
        home: ForestalScreen(
          api: api,
          onUnauthorized: () => unauthorized = true,
          showAppBar: false,
        ),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    expect(unauthorized, isTrue);
  });
}

http.Response _json(Object value) => http.Response(
  jsonEncode(value),
  200,
  headers: const {'content-type': 'application/json'},
);

Map<String, Object?> _forestResult() => {
  'revision': true,
  'resumen': {
    'consulta': {'desde': 2024, 'hasta': 2025},
    'bbox': [-63.20, -17.80, -63.19, -17.79],
    'total_periodo_ha': '0.07364891',
    'cobertura_observaciones_fraccion': null,
    'fuente': {'version': 'GFC-2025-v1.13'},
  },
  'capa': {
    'type': 'FeatureCollection',
    'features': [
      {
        'id': 'forestal-2025-1',
        'properties': {'anio': 2025, 'area_ha': '0.07364891'},
        'geometry': {
          'type': 'Polygon',
          'coordinates': [
            [
              [-63.20, -17.80],
              [-63.19, -17.80],
              [-63.19, -17.79],
              [-63.20, -17.80],
            ],
          ],
        },
      },
    ],
  },
};
