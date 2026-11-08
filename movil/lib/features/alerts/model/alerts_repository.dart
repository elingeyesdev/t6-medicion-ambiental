import '../../../core/network/geocruz_api_client.dart';
import 'alert_models.dart';

class AlertsRepository {
  const AlertsRepository(this.api);

  final GeoCruzApiClient api;

  Future<AvisosPage> listAvisos({int page = 1}) async {
    final payload = await api.getJson(
      'avisos',
      queryParameters: {'page': '$page'},
      authenticated: true,
    );
    return AvisosPage.fromJson(payload);
  }

  Future<void> markAvisoRead(String id) async {
    await api.patchJson(
      'avisos/$id/leer',
      authenticated: true,
    );
  }

  Future<AlertPage> listAlertas({
    int page = 1,
    String? severidad,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (severidad != null && severidad.isNotEmpty) {
      query['severidad'] = severidad;
    }
    final payload = await api.getJson(
      'alertas',
      queryParameters: query,
      authenticated: true,
    );
    return AlertPage.fromJson(payload);
  }

  Future<AlertAmbiental> acknowledge(String id) async {
    final payload = await api.patchJson(
      'alertas/$id/reconocer',
      authenticated: true,
    );
    return AlertAmbiental.fromJson(payload);
  }

  Future<UmbralesCatalog> umbrales() async {
    final payload = await api.getJson(
      'umbrales',
      authenticated: true,
    );
    return UmbralesCatalog.fromJson(payload);
  }
}
