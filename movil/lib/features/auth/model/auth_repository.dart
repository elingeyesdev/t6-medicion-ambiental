import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/geocruz_api_client.dart';

class GeoSurUser {
  const GeoSurUser({
    required this.name,
    required this.email,
    required this.role,
    required this.permissions,
  });

  final String name;
  final String email;
  final String role;
  final Set<String> permissions;

  factory GeoSurUser.fromJson(Object? value) {
    if (value is! Map) {
      throw const FormatException('La API devolvió una cuenta inválida.');
    }
    final permissions = value['permisos'];
    return GeoSurUser(
      name: value['nombre'] is String ? value['nombre'] as String : '',
      email: value['email'] is String ? value['email'] as String : '',
      role: value['rolNombre'] is String
          ? value['rolNombre'] as String
          : (value['rol'] is String ? value['rol'] as String : 'Cuenta'),
      permissions: permissions is List
          ? permissions.whereType<String>().toSet()
          : const {},
    );
  }
}

class AuthRepository {
  AuthRepository({required this.api, FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'geosur_mobile_token';

  final GeoCruzApiClient api;
  final FlutterSecureStorage _storage;

  Future<GeoSurUser?> restoreSession() async {
    final token = await _storage.read(key: _tokenKey);
    if (token == null || token.isEmpty) return null;
    api.bearerToken = token;
    try {
      final payload = await api.getJson(
        'cuenta',
        authenticated: true,
        timeoutOverride: const Duration(seconds: 4),
      );
      return GeoSurUser.fromJson(_map(payload)['usuario']);
    } on GeoCruzApiException catch (error) {
      if (error.statusCode == 401 || error.statusCode == 403) {
        await clearSession();
        return null;
      }
      rethrow;
    }
  }

  Future<GeoSurUser> signIn(String email, String password) =>
      _authorize('cuenta/ingresar', {'email': email, 'password': password});

  Future<GeoSurUser> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) => _authorize('cuenta/registrar', {
    'name': name,
    'email': email,
    'password': password,
    'password_confirmation': passwordConfirmation,
  });

  Future<GeoSurUser> updateName(String name) async {
    final payload = await api.putJson(
      'cuenta/nombre',
      body: {'name': name},
      authenticated: true,
    );
    return GeoSurUser.fromJson(_map(payload)['usuario']);
  }

  Future<void> signOut() async {
    try {
      await api.postJson('cuenta/salir', authenticated: true);
    } finally {
      await clearSession();
    }
  }

  Future<void> clearSession() async {
    api.bearerToken = null;
    await _storage.delete(key: _tokenKey);
  }

  Future<GeoSurUser> _authorize(
    String endpoint,
    Map<String, String> values,
  ) async {
    final payload = await api.postJson(endpoint, body: values);
    final response = _map(payload);
    final token = response['token'];
    if (token is! String || token.isEmpty) {
      throw const FormatException('La API no entregó un token de acceso.');
    }
    await _storage.write(key: _tokenKey, value: token);
    api.bearerToken = token;
    return GeoSurUser.fromJson(response['usuario']);
  }

  static Map<String, dynamic> _map(Object? value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((key, value) => MapEntry('$key', value));
    return const {};
  }
}
