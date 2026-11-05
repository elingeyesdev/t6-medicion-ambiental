import '../../../core/network/geocruz_api_client.dart';

class PlaceSearchResult {
  const PlaceSearchResult({
    required this.name,
    required this.description,
    required this.latitude,
    required this.longitude,
  });

  final String name;
  final String description;
  final double latitude;
  final double longitude;

  factory PlaceSearchResult.fromJson(Object? value) {
    final json = _asMap(value);
    return PlaceSearchResult(
      name: '${json['nombre'] ?? ''}',
      description: '${json['descripcion'] ?? ''}',
      latitude: (json['latitud'] as num).toDouble(),
      longitude: (json['longitud'] as num).toDouble(),
    );
  }
}

class PlaceSearchRepository {
  PlaceSearchRepository(this.api);

  final GeoCruzApiClient api;

  Future<List<PlaceSearchResult>> search(
    String text, {
    double? latitude,
    double? longitude,
    String type = 'lugar',
  }) async {
    final query = <String, String>{'texto': text.trim(), 'tipo': type};
    if (latitude != null && longitude != null) {
      query['latitud'] = '$latitude';
      query['longitud'] = '$longitude';
    }
    final payload = await api.getJson(
      'mapa/buscar',
      queryParameters: query,
      authenticated: true,
      timeoutOverride: const Duration(seconds: 15),
    );
    final datos = _asMap(payload)['datos'];
    if (datos is! List) return const [];
    return [
      for (final item in datos)
        if (item is Map) PlaceSearchResult.fromJson(item),
    ];
  }
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return const {};
}
