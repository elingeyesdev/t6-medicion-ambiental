import '../../../core/network/geocruz_api_client.dart';
import 'simulation_models.dart';

class SimulationRepository {
  const SimulationRepository(this.api);

  final GeoCruzApiClient api;

  Future<SimulationPage> list({int page = 1}) async {
    final payload = await api.getJson(
      'simulaciones',
      queryParameters: {'page': '$page'},
      authenticated: true,
    );
    return SimulationPage.fromJson(payload);
  }

  Future<Map<String, dynamic>> catalog() async {
    final payload = await api.getJson(
      'simulaciones/catalogo',
      authenticated: true,
    );
    if (payload is Map<String, dynamic>) return payload;
    if (payload is Map) {
      return payload.map((key, value) => MapEntry('$key', value));
    }
    return const {};
  }

  Future<SimulationDetail> get(String id) async {
    final payload = await api.getJson(
      'simulaciones/$id',
      authenticated: true,
    );
    return SimulationDetail.fromJson(payload);
  }

  Future<SimulationDetail> action({
    required String id,
    required String action,
    required int revision,
    required int position,
  }) async {
    final payload = await api.postJson(
      'simulaciones/$id/accion',
      body: {
        'accion': action,
        'revision': revision,
        'posicion': position,
      },
      authenticated: true,
    );
    return SimulationDetail.fromJson(payload);
  }
}
