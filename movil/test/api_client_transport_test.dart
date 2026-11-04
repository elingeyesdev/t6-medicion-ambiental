import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  for (final method in ['GET', 'POST', 'PUT']) {
    test('$method no cambia de servidor ni repite una petición fallida', () async {
      final sent = <http.Request>[];
      final base = Uri.parse('http://127.0.0.1:8002/api/v1/');
      final api = GeoCruzApiClient(baseUrl: base, client: MockClient((request) async {
        sent.add(request);
        throw http.ClientException('Conexión interrumpida');
      }))..bearerToken = 'token-ficticio-de-prueba';
      addTearDown(api.close);
      final request = switch (method) {
        'POST' => api.postJson('cuenta', body: {'nombre': 'Prueba'}, authenticated: true),
        'PUT' => api.putJson('cuenta', body: {'nombre': 'Prueba'}, authenticated: true),
        _ => api.getJson('cuenta', authenticated: true),
      };
      await expectLater(request, throwsA(isA<http.ClientException>()));
      expect(sent, hasLength(1));
      expect(sent.single.url.host, '127.0.0.1');
      expect(api.baseUrl, base);
    });
  }
}
