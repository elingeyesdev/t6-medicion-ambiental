import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../model/hotspot_models.dart';
import '../model/hotspot_repository.dart';

class HotspotsViewModel extends ChangeNotifier {
  HotspotsViewModel(this.repository) : filters = defaultFilters();

  final HotspotRepository repository;
  final Map<String, TerritoryBoundary> _boundaries = {};

  List<TerritoryOption> territories = const [];
  HotspotFilters filters;
  HotspotResponse? response;
  TerritoryBoundary? boundary;
  String? catalogError;
  String? queryError;
  String? mapError;
  bool loadingCatalog = false;
  bool loading = false;
  bool queryExpired = false;
  _PendingQuery? _pending;
  int _requestGeneration = 0;

  List<TerritoryOption> get provinces => territories
      .where((territory) => territory.type == 'provincia')
      .toList(growable: false);

  List<TerritoryOption> municipalitiesFor(String? provinceId) => territories
      .where(
        (territory) =>
            territory.type == 'municipio' &&
            (provinceId == null || territory.parentId == provinceId),
      )
      .toList(growable: false);

  TerritoryOption? territoryById(String? id) {
    if (id == null) return null;
    for (final territory in territories) {
      if (territory.identifier == id) return territory;
    }
    return null;
  }

  String territoryName(String? id) =>
      territoryById(id)?.name ??
      (id == 'BO07' ? 'Santa Cruz' : id ?? 'Santa Cruz');

  Future<void> initialize() async {
    await loadCatalog(runInitialQuery: true);
  }

  Future<void> loadCatalog({bool runInitialQuery = false}) async {
    loadingCatalog = true;
    catalogError = null;
    notifyListeners();
    try {
      territories = await repository.loadTerritories();
      loadingCatalog = false;
      notifyListeners();
      if (runInitialQuery) await apply(filters);
    } catch (error) {
      loadingCatalog = false;
      catalogError = _friendlyError(error);
      notifyListeners();
    }
  }

  Future<void> apply(HotspotFilters next) {
    final normalized = _validateAndNormalize(next);
    return _request(normalized, page: 1);
  }

  Future<void> retry() {
    final pending = _pending;
    if (pending == null || queryExpired) return Future<void>.value();
    return _request(
      pending.filters,
      page: pending.page,
      setVersion: pending.setVersion,
    );
  }

  Future<void> restartExpiredQuery() {
    final pending = _pending;
    if (pending == null) return Future<void>.value();
    return _request(pending.filters, page: 1);
  }

  Future<void> previousPage() async {
    final current = response;
    if (current == null || loading || current.page <= 1) return;
    await _request(
      filters,
      page: current.page - 1,
      setVersion: current.setVersion,
    );
  }

  Future<void> nextPage() async {
    final current = response;
    if (current == null || loading || current.nextPage == null) return;
    await _request(
      filters,
      page: current.nextPage!,
      setVersion: current.setVersion,
    );
  }

  HotspotFilters _validateAndNormalize(HotspotFilters value) {
    final duration = value.untilUtc.toUtc().difference(value.fromUtc.toUtc());
    if (duration <= Duration.zero) {
      throw const FormatException('“Hasta” debe ser posterior a “Desde”.');
    }
    if (duration > const Duration(hours: 168)) {
      throw const FormatException(
        'El período supera el máximo de 7 días. Acorta el rango seleccionado.',
      );
    }
    var province = value.provinceId;
    final municipality = value.municipalityId;
    if (province != null && territoryById(province)?.type != 'provincia') {
      throw const FormatException(
        'La provincia seleccionada no existe en el catálogo.',
      );
    }
    if (municipality != null) {
      final selectedMunicipality = territoryById(municipality);
      if (selectedMunicipality?.type != 'municipio' ||
          selectedMunicipality?.parentId == null) {
        throw const FormatException(
          'El municipio seleccionado no existe en el catálogo.',
        );
      }
      if (province != null && selectedMunicipality!.parentId != province) {
        throw const FormatException(
          'El municipio no pertenece a la provincia seleccionada.',
        );
      }
      province ??= selectedMunicipality!.parentId;
    }
    return HotspotFilters(
      fromUtc: value.fromUtc.toUtc(),
      untilUtc: value.untilUtc.toUtc(),
      provinceId: province,
      municipalityId: municipality,
      confidence: value.confidence,
    );
  }

  Future<void> _request(
    HotspotFilters requested, {
    required int page,
    String? setVersion,
  }) async {
    final generation = ++_requestGeneration;
    _pending = _PendingQuery(requested, page, setVersion);
    loading = true;
    queryError = null;
    queryExpired = false;
    mapError = null;
    notifyListeners();

    try {
      final result = await repository.search(
        requested,
        page: page,
        setVersion: setVersion,
      );
      if (generation != _requestGeneration) return;
      if (setVersion != null && setVersion != result.setVersion) {
        throw const GeoCruzApiException(
          statusCode: 409,
          code: 'CONJUNTO_CAMBIADO',
          message: 'El conjunto cambió entre páginas. Reinicia la consulta.',
        );
      }
      final previous = response;
      if (setVersion != null &&
          previous != null &&
          jsonEncode(previous.summary) != jsonEncode(result.summary)) {
        throw const GeoCruzApiException(
          statusCode: 409,
          code: 'CONJUNTO_CAMBIADO',
          message: 'El resumen cambió entre páginas. Reinicia la consulta.',
        );
      }

      TerritoryBoundary? nextBoundary;
      String? nextMapError;
      if (boundary?.identifier == result.territoryId) {
        nextBoundary = boundary;
      } else {
        try {
          nextBoundary =
              _boundaries[result.territoryId] ??
              await repository.loadBoundary(result.territoryId);
          final territory = result.raw['consulta']?['territorio'];
          final expectedVersion = territory is Map
              ? territory['versionCatalogo']?.toString()
              : null;
          if (expectedVersion != null &&
              nextBoundary.catalogVersion != expectedVersion) {
            throw const FormatException(
              'El límite usa una versión de catálogo distinta a la consulta.',
            );
          }
          _boundaries[result.territoryId] = nextBoundary;
        } catch (error) {
          debugPrint('No se pudo cargar el límite territorial: $error');
          nextBoundary = null;
          nextMapError =
              'No se pudo cargar el límite territorial. El listado y los detalles siguen disponibles.';
        }
      }
      if (generation != _requestGeneration) return;
      response = result;
      boundary = nextBoundary;
      mapError = nextMapError;
      filters = requested;
      _pending = null;
      loading = false;
      notifyListeners();
    } catch (error) {
      if (generation != _requestGeneration) return;
      loading = false;
      queryError = _friendlyError(error);
      queryExpired =
          error is GeoCruzApiException &&
          (error.statusCode == 409 || error.code == 'CONJUNTO_CAMBIADO');
      notifyListeners();
    }
  }

  String _friendlyError(Object error) {
    if (error is GeoCruzApiException) {
      if (error.statusCode == 409 || error.code == 'CONJUNTO_CAMBIADO') {
        return 'El conjunto de resultados expiró o cambió. Reinicia la consulta para obtener uno nuevo.';
      }
      if (error.statusCode == 422) {
        return error.message;
      }
      if (error.statusCode == 429) {
        return 'La API recibió demasiadas consultas. Espera un momento y reintenta.';
      }
      return error.message;
    }
    if (error is TimeoutException) {
      return 'La consulta tardó demasiado. Comprueba la conexión y reintenta.';
    }
    if (error is FormatException) return error.message;
    return 'No se pudo conectar con GeoCruz. Comprueba la API y reintenta.';
  }

  static HotspotFilters defaultFilters({DateTime? now}) {
    final boliviaNow = (now ?? DateTime.now()).toUtc().subtract(
      const Duration(hours: 4),
    );
    final midnightBoliviaAsUtc = DateTime.utc(
      boliviaNow.year,
      boliviaNow.month,
      boliviaNow.day,
      4,
    );
    return HotspotFilters(
      fromUtc: midnightBoliviaAsUtc.subtract(const Duration(days: 1)),
      untilUtc: midnightBoliviaAsUtc,
    );
  }
}

class _PendingQuery {
  const _PendingQuery(this.filters, this.page, this.setVersion);

  final HotspotFilters filters;
  final int page;
  final String? setVersion;
}
