class ApiConfig {
  const ApiConfig._();

  static const String _configuredBaseUrl = String.fromEnvironment(
    'GEOCRUZ_API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1/',
  );

  static Uri fromEnvironment() => parseBaseUrl(_configuredBaseUrl);

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
