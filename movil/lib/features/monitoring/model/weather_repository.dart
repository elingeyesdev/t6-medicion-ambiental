import '../../../core/network/geocruz_api_client.dart';

class WeatherSnapshot {
  const WeatherSnapshot({
    required this.latitude,
    required this.longitude,
    required this.current,
    required this.days,
    required this.variables,
  });

  final double latitude;
  final double longitude;
  final Map<String, dynamic> current;
  final List<Map<String, dynamic>> days;
  final Map<String, dynamic> variables;

  factory WeatherSnapshot.fromJson(
    Object? value, {
    required double latitude,
    required double longitude,
  }) {
    final json = _asMap(value);
    final daysRaw = json['dias'];
    final days = <Map<String, dynamic>>[];
    if (daysRaw is List) {
      for (final day in daysRaw) {
        if (day is Map) days.add(day.map((k, v) => MapEntry('$k', v)));
      }
    }
    return WeatherSnapshot(
      latitude: latitude,
      longitude: longitude,
      current: _asMap(json['actual']),
      days: days,
      variables: _asMap(json['variables']),
    );
  }

  String? unitOf(String key) {
    final meta = variables[key];
    if (meta is Map && meta['unidad'] != null) return '${meta['unidad']}';
    return null;
  }
}

class WeatherRepository {
  WeatherRepository(this.api);

  final GeoCruzApiClient api;

  Future<WeatherSnapshot> forecast({
    required double latitude,
    required double longitude,
  }) async {
    final payload = await api.getJson(
      'tiempo',
      queryParameters: {
        'latitud': '$latitude',
        'longitud': '$longitude',
      },
      authenticated: true,
      timeoutOverride: const Duration(seconds: 20),
    );
    return WeatherSnapshot.fromJson(
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
