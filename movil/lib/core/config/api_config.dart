import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

class ApiConfig {
  const ApiConfig._();

  static const String _configuredBaseUrl = String.fromEnvironment(
    'GEOCRUZ_API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8002/api/v1/',
  );

  /// En emulador Android, 127.0.0.1 es el propio emulador: usar 10.0.2.2 (host).
  static Uri fromEnvironment() {
    var url = _configuredBaseUrl;
    if (!kIsWeb && Platform.isAndroid && url.contains('127.0.0.1')) {
      url = url.replaceFirst('127.0.0.1', '10.0.2.2');
    }
    return parseBaseUrl(url);
  }

  static Uri parseBaseUrl(String value) {
    final trimmed = value.trim();
    final parsed = Uri.tryParse(trimmed);
    if (parsed == null ||
        !parsed.hasAuthority ||
        (parsed.scheme != 'http' && parsed.scheme != 'https') ||
        parsed.userInfo.isNotEmpty ||
        parsed.query.isNotEmpty ||
        parsed.fragment.isNotEmpty) {
      throw FormatException(
        'GEOCRUZ_API_BASE_URL debe ser una URL http(s) sin credenciales, '
        'parámetros ni fragmento.',
        value,
      );
    }

    final path = parsed.path.endsWith('/') ? parsed.path : '${parsed.path}/';
    return parsed.replace(path: path);
  }
}
