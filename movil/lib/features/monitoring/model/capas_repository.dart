import 'dart:typed_data';

import '../../../core/network/geocruz_api_client.dart';
import 'capas_models.dart';

class CapasRepository {
  CapasRepository(this.api);

  final GeoCruzApiClient api;

  Future<CapasCatalog> loadCatalog() async {
    final payload = await api.getJson(
      'mapa/capas',
      authenticated: true,
      timeoutOverride: const Duration(seconds: 30),
    );
    return CapasCatalog.fromJson(payload);
  }

  Future<Uint8List> loadPng(CapaCuadro cuadro) async {
    final path = _apiPath(cuadro.url);
    return api.getBytes(path, authenticated: true);
  }

  /// Acepta URL absoluta de API o ruta relativa `/api/v1/...` / `mapa/capas/...`.
  String _apiPath(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) {
      throw FormatException('URL de capa no válida.');
    }
    var path = uri.path;
    const prefix = '/api/v1/';
    if (path.startsWith(prefix)) {
      path = path.substring(prefix.length);
    } else if (path.startsWith('/')) {
      path = path.substring(1);
    }
    if (!RegExp(r'^mapa/capas/cuadros/[a-z0-9][a-z0-9_-]{0,79}\.png$').hasMatch(path)) {
      throw FormatException('Ruta de imagen de capa no admitida.');
    }
    return path;
  }
}
