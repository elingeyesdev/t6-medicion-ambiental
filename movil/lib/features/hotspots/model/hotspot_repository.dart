import '../../../core/network/geocruz_api_client.dart';
import 'hotspot_models.dart';

class HotspotRepository {
  const HotspotRepository(this.api);

  final GeoCruzApiClient api;

  Future<List<TerritoryOption>> loadTerritories() async {
    final payload = await api.getJson(
      'territorios',
      queryParameters: const {'tamanioPagina': '500'},
      timeoutOverride: const Duration(seconds: 12),
    );
    if (payload is! Map<String, dynamic> || payload['datos'] is! List) {
      throw const FormatException(
        'El catálogo territorial no tiene el formato esperado.',
      );
    }
    final pagination = payload['paginacion'];
    if (pagination is! Map<String, dynamic> ||
        pagination['siguiente'] != null ||
        pagination['total'] != (payload['datos'] as List).length) {
      throw const FormatException(
        'La API no devolvió el catálogo territorial completo.',
      );
    }
    final territories = (payload['datos'] as List)
        .map(TerritoryOption.fromJson)
        .where(
          (territory) =>
              territory.identifier.isNotEmpty && territory.name.isNotEmpty,
        )
        .toList(growable: false);
    if (territories.isEmpty ||
        !territories.any((territory) => territory.identifier == 'BO07')) {
      throw const FormatException('El catálogo no incluye Santa Cruz.');
    }
    return territories;
  }

  Future<HotspotResponse> search(
    HotspotFilters filters, {
    int page = 1,
    String? setVersion,
  }) async {
    final payload = await api.getJson(
      'focos-calor',
      queryParameters: filters.toQuery(page: page, setVersion: setVersion),
    );
    return HotspotResponse.fromJson(payload, expectedPage: page);
  }

  Future<TerritoryBoundary> loadBoundary(String territoryId) async {
    final payload = await api.getJson(
      'territorios/${Uri.encodeComponent(territoryId)}/limite',
      timeoutOverride: const Duration(seconds: 12),
    );
    return TerritoryBoundary.fromJson(payload, expectedId: territoryId);
  }
}
