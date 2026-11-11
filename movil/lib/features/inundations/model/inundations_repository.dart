import 'package:flutter/foundation.dart';

import '../../../core/network/geocruz_api_client.dart';
import 'inundation_models.dart';

class InundationsRepository {
  const InundationsRepository(this.api);

  final GeoCruzApiClient api;

  Future<WaterCatalog> loadCatalog() async {
    final payload = await api.getJsonWithProfiling(
      'inundaciones/catalogo',
      authenticated: true,
      timeoutOverride: const Duration(seconds: 20),
      profileLabel: 'agua/catalogo',
    );
    final timer = Stopwatch()..start();
    final catalog = WaterCatalog.fromJson(payload);
    timer.stop();
    if (kDebugMode) {
      debugPrint(
        '[rendimiento][agua/modelo-catalogo] parse=${timer.elapsedMilliseconds}ms '
        'productos=${catalog.products.length}',
      );
    }
    return catalog;
  }

  Future<WaterResult> query(WaterProductOption product) async {
    final payload = await api.getJsonWithProfiling(
      'inundaciones/consulta',
      queryParameters: {
        'territorio': product.territoryId,
        'referencia': product.referenceDate,
        'comparacion': product.comparisonDate,
      },
      authenticated: true,
      timeoutOverride: const Duration(seconds: 30),
      profileLabel: 'agua/consulta',
    );
    final timer = Stopwatch()..start();
    final result = WaterResult.fromJson(payload);
    timer.stop();
    if (kDebugMode) {
      debugPrint(
        '[rendimiento][agua/modelo-consulta] parse=${timer.elapsedMilliseconds}ms '
        'figuras=${result.features.length}',
      );
    }
    if (result.territoryId != product.territoryId ||
        result.referenceDate != product.referenceDate ||
        result.comparisonDate != product.comparisonDate) {
      throw const FormatException(
        'El resultado no corresponde al lugar y las fechas elegidos.',
      );
    }
    return result;
  }
}
