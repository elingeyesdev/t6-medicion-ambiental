import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/monitoring/view/capas_panel.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets('el panel no interpreta el desplazamiento como estado abierto', (
    tester,
  ) async {
    final bucket = PageStorageBucket();
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((_) async => http.Response(
        jsonEncode({'capas': []}),
        200,
        headers: {'content-type': 'application/json'},
      )),
    )..bearerToken = 'prueba';
    addTearDown(api.close);
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: PageStorage(
          bucket: bucket,
          child: SizedBox(
            key: const PageStorageKey('monitoring-scroll'),
            child: Builder(builder: (context) {
              bucket.writeState(context, 120.0);
              return CapasPanel(api: api, onOverlayChanged: (_) {});
            }),
          ),
        ),
      ),
    ));
    await tester.pump();
    expect(tester.takeException(), isNull);
    expect(find.text('Capas del ambiente'), findsOneWidget);
  });
}
