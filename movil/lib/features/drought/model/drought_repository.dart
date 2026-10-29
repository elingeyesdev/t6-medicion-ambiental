import '../../../core/network/geocruz_api_client.dart';
import '../../hotspots/model/hotspot_models.dart';
import 'drought_models.dart';

class DroughtRepository {
  const DroughtRepository(this.apiClient);

  final GeoCruzApiClient apiClient;

  /// Consulta el endpoint de sequías con los filtros especificados.
  Future<DroughtResponse> query(DroughtFilters filters) async {
    filters.validate(DateTime.now().toUtc());

    final payload = await apiClient.getJson(
      'sequias',
      queryParameters: filters.toQueryParameters(),
    );

    if (payload is! Map<String, dynamic>) {
      throw const FormatException('La respuesta de sequías no es un objeto JSON válido.');
    }

    final response = DroughtResponse.fromJson(payload);
    if (response.consulta['mesFinal'] != filters.mesFinal) {
      throw const FormatException('El resultado no corresponde al mes elegido. Inténtalo otra vez.');
    }
    return response;
  }

  /// Carga el catálogo territorial oficial de Santa Cruz.
  Future<List<TerritoryOption>> loadTerritories() async {
    final payload = await apiClient.getJson(
      'territorios',
      queryParameters: const {'tamanioPagina': '500'},
      timeoutOverride: const Duration(seconds: 12),
    );
    if (payload is! Map<String, dynamic> || payload['datos'] is! List) {
      throw const FormatException(
        'El catálogo territorial no tiene el formato esperado.',
      );
    }
    return (payload['datos'] as List)
        .map(TerritoryOption.fromJson)
        .where(
          (territory) =>
              territory.identifier.isNotEmpty && territory.name.isNotEmpty,
        )
        .toList(growable: false);
  }
}
