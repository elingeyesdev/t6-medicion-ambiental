import 'dart:typed_data';

import '../../../core/network/geocruz_api_client.dart';
import 'report_models.dart';

class ReportRepository {
  const ReportRepository(this.api);

  final GeoCruzApiClient api;

  Future<ReportPage> list({int page = 1}) async {
    final payload = await api.getJson(
      'reportes',
      queryParameters: {'page': '$page'},
      authenticated: true,
    );
    return ReportPage.fromJson(payload);
  }

  Future<ReportDetail> get(String id) async {
    final payload = await api.getJson(
      'reportes/$id',
      authenticated: true,
    );
    return ReportDetail.fromJson(payload);
  }

  /// Formato: `pdf` | `xlsx`. Ruta real en API: `/api/v1/reportes/{id}/descargar/{formato}`.
  Future<Uint8List> download(String id, String format) {
    return api.downloadBytes(
      'reportes/$id/descargar/$format',
      authenticated: true,
      accept: format == 'pdf'
          ? 'application/pdf, application/json'
          : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
      timeoutOverride: const Duration(seconds: 120),
    );
  }

  /// Plantillas: ruta real `GET /api/v1/plantillas` (no bajo `/reportes/plantillas`).
  Future<TemplatePage> templates({int page = 1}) async {
    final payload = await api.getJson(
      'plantillas',
      queryParameters: {'page': '$page'},
      authenticated: true,
    );
    return TemplatePage.fromJson(payload);
  }
}
