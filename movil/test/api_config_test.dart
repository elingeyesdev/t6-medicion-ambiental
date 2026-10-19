import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/config/api_config.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('ApiConfig', () {
    test('normaliza la barra final de la base de API', () {
      expect(
        ApiConfig.parseBaseUrl('http://10.0.2.2:8000/api/v1').toString(),
        'http://10.0.2.2:8000/api/v1/',
      );
    });

    test('rechaza credenciales y protocolos ajenos a HTTP', () {
      expect(
        () => ApiConfig.parseBaseUrl('https://user:secret@example.com/api/v1'),
        throwsFormatException,
      );
      expect(
        () => ApiConfig.parseBaseUrl('ftp://example.com/api/v1'),
        throwsFormatException,
      );
      expect(
        () => ApiConfig.parseBaseUrl('http://example.com/api/v1?token=secret'),
        throwsFormatException,
      );
    });
  });

  test(
    'cliente GET resuelve la ruta y codifica parámetros sin credenciales',
    () async {
      http.Request? capturedRequest;
      final client = GeoCruzApiClient(
        baseUrl: ApiConfig.parseBaseUrl('http://10.0.2.2:8000/api/v1'),
        client: MockClient((request) async {
          capturedRequest = request;
          return http.Response(
            jsonEncode({'datos': <Object>[]}),
            200,
            headers: const {'content-type': 'application/json'},
          );
        }),
      );

      final response = await client.getJson(
        'focos-calor',
        queryParameters: const {
          'desde': '2026-10-10T00:00:00-04:00',
          'hasta': '2026-10-11T00:00:00-04:00',
        },
      );

      expect(response, {'datos': <Object>[]});
      expect(capturedRequest?.url.path, '/api/v1/focos-calor');
      expect(
        capturedRequest?.url.queryParameters['desde'],
        '2026-10-10T00:00:00-04:00',
      );
      expect(capturedRequest?.url.userInfo, isEmpty);
      client.close();
    },
  );

  test('rechaza rutas que intentan salir del backend configurado', () async {
    final client = GeoCruzApiClient(
      baseUrl: ApiConfig.parseBaseUrl('http://10.0.2.2:8000/api/v1'),
      client: MockClient((_) async => http.Response('{}', 200)),
    );

    await expectLater(client.getJson('../externo'), throwsArgumentError);
    await expectLater(
      client.getJson('https://otro-host.example/recurso'),
      throwsArgumentError,
    );
    client.close();
  });
}
