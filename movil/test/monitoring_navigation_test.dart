import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/monitoring/view/monitoring_screen.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets('cambiar país limpia la región y permite elegir simulados explícitamente', (tester) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    // The large region asset decodes in an isolate outside the widget fake clock.
    await tester.runAsync(() async {
      await Future.wait([
        rootBundle.loadString('assets/geografia/paises-envirosense.json'),
        rootBundle.loadString('assets/geografia/regiones/indice.json'),
      ]);
    });
    Future<void> advance() async {
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
    }
    final queries = <Uri>[];
    final api = GeoCruzApiClient(baseUrl: Uri.parse('http://localhost/api/v1/'), client: MockClient((request) async {
      queries.add(request.url);
      return http.Response(jsonEncode({
        'metrica': 'temperatura', 'unidad': '°C',
        'desde': request.url.queryParameters['desde'], 'hasta': request.url.queryParameters['hasta'],
        'datos': [], 'total': 0, 'pagina': 1, 'ultimaPagina': 1,
      }), 200, headers: {'content-type': 'application/json'});
    }))..bearerToken = 'token-de-prueba';
    addTearDown(api.close);
    await tester.pumpWidget(MaterialApp(home: MonitoringScreen(api: api, onUnauthorized: () {})));
    await advance();
    expect(queries, isNotEmpty);

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
    expect(find.byKey(const ValueKey('monitoring-region-AR')), findsOneWidget);

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
  });
}
