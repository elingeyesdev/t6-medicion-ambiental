import 'dart:convert';

import 'package:flutter/services.dart';

import '../../../core/network/geocruz_api_client.dart';
import 'monitoring_models.dart';

class MonitoringRepository {
  const MonitoringRepository(this.api);

  final GeoCruzApiClient api;

  Future<List<CountryOption>> loadCountries() async {
    final raw = await rootBundle.loadString(
      'assets/geografia/paises-envirosense.json',
    );
    final json = jsonDecode(raw);
    if (json is! Map || json['datos'] is! List) {
      throw const FormatException(
        'El catálogo de países no tiene el formato esperado.',
      );
    }
    return List.unmodifiable(
      (json['datos'] as List)
          .whereType<Map>()
          .map(
            (country) => CountryOption(
              '${country['codigo'] ?? ''}',
              '${country['nombre'] ?? ''}',
            ),
          )
          .where(
            (country) => country.code.isNotEmpty && country.name.isNotEmpty,
          ),
    );
  }

  Future<List<RegionOption>> loadRegions() async {
    final raw = await rootBundle.loadString(
      'assets/geografia/regiones/indice.json',
    );
    final json = jsonDecode(raw);
    if (json is! Map || json['datos'] is! List) {
      throw const FormatException(
        'El catálogo de regiones no tiene el formato esperado.',
      );
    }
    return List.unmodifiable(
      (json['datos'] as List)
          .whereType<Map>()
          .map(
            (region) => RegionOption(
              code: '${region['codigo'] ?? ''}',
              country: '${region['pais'] ?? ''}',
              name: '${region['nombre'] ?? ''}',
            ),
          )
          .where(
            (region) => region.code.isNotEmpty && region.country.isNotEmpty,
          ),
    );
  }

  Future<MonitoringResponse> query(MonitoringFilters filters) async {
    final payload = await api.getJson(
      'mapa/puntos',
      queryParameters: filters.toQuery(),
      authenticated: true,
      timeoutOverride: const Duration(seconds: 35),
    );
    return MonitoringResponse.fromJson(payload);
  }

  Future<Map<String, dynamic>> history(
    MonitoringPoint point,
    MonitoringFilters filters,
  ) async {
    final payload = await api.getJson(
      'sensores/${Uri.encodeComponent(point.code)}/lecturas',
      queryParameters: {
        'desde': formatApiInstant(filters.from),
        'hasta': formatApiInstant(filters.until),
        'metrica': filters.metric,
        if (filters.origin != null && filters.origin != 'todos')
          'origen': filters.origin!,
        'pagina': '1',
      },
      authenticated: true,
      timeoutOverride: const Duration(seconds: 25),
    );
    if (payload is Map<String, dynamic>) return payload;
    if (payload is Map) {
      return payload.map((key, value) => MapEntry('$key', value));
    }
    throw const FormatException('El historial no tiene el formato esperado.');
  }
}
