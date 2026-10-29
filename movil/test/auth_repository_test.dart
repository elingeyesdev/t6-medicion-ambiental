import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/auth/model/auth_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('inicio de sesión guarda token y perfil devuelto por Laravel', () async {
    FlutterSecureStorage.setMockInitialValues({});
    http.Request? captured;
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((request) async {
        captured = request;
        return http.Response(
          jsonEncode({
            'token': 'token-de-prueba',
            'usuario': {
              'nombre': 'Jaziel',
              'email': 'jaziel@example.test',
              'rolNombre': 'Consulta',
              'permisos': ['datos.consultar'],
            },
          }),
          200,
          headers: const {'content-type': 'application/json'},
        );
      }),
    );
    final auth = AuthRepository(api: api);

    final user = await auth.signIn('jaziel@example.test', 'clave-de-prueba');

    expect(captured?.url.path, '/api/v1/cuenta/ingresar');
    expect(jsonDecode(captured!.body), {
      'email': 'jaziel@example.test',
      'password': 'clave-de-prueba',
    });
    expect(user.name, 'Jaziel');
    expect(user.permissions, contains('datos.consultar'));
    expect(api.bearerToken, 'token-de-prueba');
    expect(
      await const FlutterSecureStorage().read(key: 'geosur_mobile_token'),
      'token-de-prueba',
    );
    api.close();
  });

  test('restaura sesión y adjunta token a la consulta de cuenta', () async {
    FlutterSecureStorage.setMockInitialValues({
      'geosur_mobile_token': 'token-guardado',
    });
    http.Request? captured;
    final api = GeoCruzApiClient(
      baseUrl: Uri.parse('http://localhost/api/v1/'),
      client: MockClient((request) async {
        captured = request;
        return http.Response(
          jsonEncode({
            'usuario': {
              'nombre': 'Cuenta de prueba',
              'email': 'cuenta@example.test',
              'rolNombre': 'Consulta',
              'permisos': ['datos.consultar'],
            },
          }),
          200,
          headers: const {'content-type': 'application/json'},
        );
      }),
    );

    final user = await AuthRepository(api: api).restoreSession();

    expect(user?.name, 'Cuenta de prueba');
    expect(captured?.url.path, '/api/v1/cuenta');
    expect(captured?.headers['authorization'], 'Bearer token-guardado');
    api.close();
  });
}
