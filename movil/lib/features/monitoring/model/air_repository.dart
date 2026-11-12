import '../../../core/network/geocruz_api_client.dart';

class AirSnapshot {
  const AirSnapshot({
    required this.latitude,
    required this.longitude,
    required this.current,
    required this.hours,
    required this.variables,
  });

  final double latitude;
  final double longitude;
  final Map<String, dynamic> current;
  final List<Map<String, dynamic>> hours;
  final Map<String, dynamic> variables;

  factory AirSnapshot.fromJson(
    Object? value, {
    required double latitude,
    required double longitude,
  }) {
    final json = _asMap(value);
    final hoursRaw = json['horas'] ?? json['serie'];
    final hours = <Map<String, dynamic>>[];
    if (hoursRaw is List) {
      for (final hour in hoursRaw) {
        if (hour is Map) hours.add(hour.map((k, v) => MapEntry('$k', v)));
      }
    }
    return AirSnapshot(
      latitude: latitude,
      longitude: longitude,
      current: _asMap(json['actual'] ?? json['current']),
      hours: hours,
      variables: _asMap(json['variables']),
    );
  }

  String? unitOf(String key) {
    final meta = variables[key];
    if (meta is Map && meta['unidad'] != null) return '${meta['unidad']}';
    return null;
  }

  String? labelOf(String key) {
    final meta = variables[key];
    if (meta is Map && meta['nombre'] != null) return '${meta['nombre']}';
    return key;
  }
}

class AirRepository {
  AirRepository(this.api);

  final GeoCruzApiClient api;

  Future<AirSnapshot> quality({
    required double latitude,
    required double longitude,
  }) async {
    final payload = await api.getJson(
      'aire',
      queryParameters: {
        'latitud': '$latitude',
        'longitud': '$longitude',
      },
      authenticated: true,
      timeoutOverride: const Duration(seconds: 20),
    );
    return AirSnapshot.fromJson(
      payload,
      latitude: latitude,
      longitude: longitude,
    );
  }
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return const {};
}
