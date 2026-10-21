import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

Map<String, dynamic> _map(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((key, value) => MapEntry('$key', value));
  return const {};
}

String? _string(Object? value) =>
    value is String && value.isNotEmpty ? value : null;

class TerritoryOption {
  const TerritoryOption({
    required this.identifier,
    required this.name,
    required this.type,
    this.parentId,
  });

  final String identifier;
  final String name;
  final String type;
  final String? parentId;

  factory TerritoryOption.fromJson(Object? json) {
    final value = _map(json);
    return TerritoryOption(
      identifier: _string(value['identificador']) ?? '',
      name: _string(value['nombre']) ?? '',
      type: _string(value['tipo']) ?? '',
      parentId: _string(value['padre']),
    );
  }
}

class HotspotFilters {
  const HotspotFilters({
    required this.fromUtc,
    required this.untilUtc,
    this.provinceId,
    this.municipalityId,
    this.confidence,
  });

  final DateTime fromUtc;
  final DateTime untilUtc;
  final String? provinceId;
  final String? municipalityId;
  final String? confidence;

  Map<String, String> toQuery({
    required int page,
    String? setVersion,
    int pageSize = 25,
  }) {
    final values = <String, String>{
      'desde': _isoSeconds(fromUtc),
      'hasta': _isoSeconds(untilUtc),
      'modo': 'externo',
      'pagina': '$page',
      'tamanioPagina': '$pageSize',
    };
    if (provinceId case final id?) values['provincia'] = id;
    if (municipalityId case final id?) values['municipio'] = id;
    if (confidence case final value?) values['confianza'] = value;
    if (setVersion case final version?) values['versionConjunto'] = version;
    return values;
  }

  static String _isoSeconds(DateTime value) =>
      value.toUtc().toIso8601String().replaceFirst(RegExp(r'\.000Z$'), 'Z');
}

class HotspotRecord {
  const HotspotRecord({
    required this.identifier,
    required this.attributes,
    required this.dates,
    required this.territory,
    required this.missing,
    required this.sourceRefs,
    this.coordinates,
    this.nature,
    this.method,
  });

  final String identifier;
  final Map<String, dynamic> attributes;
  final Map<String, dynamic> dates;
  final Map<String, dynamic> territory;
  final List<dynamic> missing;
  final List<String> sourceRefs;
  final LatLng? coordinates;
  final String? nature;
  final String? method;

  String? get confidence => _string(attributes['confianza']);
  String? get originalConfidence => _string(attributes['confianzaOriginal']);
  num? get radiantPower => attributes['potenciaRadiativa'] is num
      ? attributes['potenciaRadiativa'] as num
      : null;
  String? get radiantPowerUnit =>
      _string(attributes['unidadPotenciaRadiativa']);
  String? get observedAt => _string(dates['observadoDesde']);
  String? get productVersion => _string(attributes['versionOriginal']);

  factory HotspotRecord.fromJson(Object? json) {
    final value = _map(json);
    final geometry = _map(value['geometria']);
    final coordinateList = geometry['coordinates'];
    LatLng? coordinates;
    if (geometry['type'] == 'Point' &&
        coordinateList is List &&
        coordinateList.length >= 2 &&
        coordinateList[0] is num &&
        coordinateList[1] is num) {
      // GeoJSON stores [longitude, latitude]; LatLng expects latitude first.
      coordinates = LatLng(
        (coordinateList[1] as num).toDouble(),
        (coordinateList[0] as num).toDouble(),
      );
    }
    return HotspotRecord(
      identifier: _string(value['identificador']) ?? '',
      attributes: _map(value['atributos']),
      dates: _map(value['fechas']),
      territory: _map(value['territorio']),
      missing: value['faltantes'] is List
          ? List<dynamic>.unmodifiable(value['faltantes'] as List)
          : const [],
      sourceRefs: value['fuentesRef'] is List
          ? List<String>.unmodifiable(
              (value['fuentesRef'] as List).whereType<String>(),
            )
          : const [],
      coordinates: coordinates,
      nature: _string(value['naturaleza']),
      method: _string(value['metodo']),
    );
  }
}

class HotspotResponse {
  const HotspotResponse({
    required this.records,
    required this.raw,
    required this.territoryId,
    required this.territoryType,
    required this.periodFrom,
    required this.periodUntil,
    required this.queryFetchedAt,
    required this.fetchType,
    required this.fetchedAt,
    required this.ageSeconds,
    required this.total,
    required this.summary,
    required this.coverage,
    required this.warnings,
    required this.sources,
    required this.page,
    required this.pageSize,
    required this.nextPage,
    required this.setVersion,
  });

  final List<HotspotRecord> records;
  final Map<String, dynamic> raw;
  final String territoryId;
  final String territoryType;
  final DateTime periodFrom;
  final DateTime periodUntil;
  final DateTime queryFetchedAt;
  final String fetchType;
  final DateTime? fetchedAt;
  final int? ageSeconds;
  final int total;
  final Map<String, dynamic> summary;
  final Map<String, dynamic> coverage;
  final List<dynamic> warnings;
  final List<dynamic> sources;
  final int page;
  final int pageSize;
  final int? nextPage;
  final String setVersion;

  bool get isEmpty => total == 0;
  String? get coverageState => _string(coverage['estado']);

  List<Map<String, dynamic>> get indicators {
    final list = summary['indicadores'];
    if (list is! List) return const [];
    return list.map(_map).toList(growable: false);
  }

  Map<String, dynamic>? indicator(String code) {
    for (final value in indicators) {
      if (value['codigo'] == code) return value;
    }
    return null;
  }

  factory HotspotResponse.fromJson(Object? json, {required int expectedPage}) {
    final value = _map(json);
    if (value['versionContrato'] != '1.0-m2-candidata') {
      throw const FormatException('Versión de contrato API no compatible.');
    }
    final rows = value['datos'];
    final query = _map(value['consulta']);
    final territory = _map(query['territorio']);
    final period = _map(query['periodo']);
    final retrieval = _map(query['obtencion']);
    final summary = _map(value['resumen']);
    final pagination = _map(value['paginacion']);
    final coverage = _map(value['cobertura']);
    final from = DateTime.tryParse('${period['desde'] ?? ''}');
    final until = DateTime.tryParse('${period['hasta'] ?? ''}');
    final fetched = DateTime.tryParse(
      '${retrieval['fechaRecuperacion'] ?? ''}',
    );
    final queryFetched = DateTime.tryParse('${query['fechaConsulta'] ?? ''}');
    final total = pagination['total'];
    final page = pagination['pagina'];
    final pageSize = pagination['tamanioPagina'];
    final setVersion = _string(pagination['versionConjunto']);

    if (rows is! List ||
        from == null ||
        until == null ||
        fetched == null ||
        queryFetched == null ||
        total is! int ||
        page != expectedPage ||
        pageSize is! int ||
        summary['totalRegistros'] != total ||
        coverage.isEmpty ||
        territory.isEmpty ||
        setVersion == null ||
        _string(_map(value['representacionMapa'])['alcance']) != 'pagina') {
      throw const FormatException(
        'La respuesta no coincide con el contrato de focos.',
      );
    }

    final records = rows.map(HotspotRecord.fromJson).toList(growable: false);
    if (records.any((record) => record.identifier.isEmpty)) {
      throw const FormatException(
        'La API devolvió una detección sin identificador.',
      );
    }
    final next = pagination['siguiente'];
    return HotspotResponse(
      records: records,
      raw: value,
      territoryId: _string(territory['identificador']) ?? '',
      territoryType: _string(territory['tipo']) ?? '',
      periodFrom: from.toUtc(),
      periodUntil: until.toUtc(),
      queryFetchedAt: queryFetched.toUtc(),
      fetchType: _string(retrieval['tipo']) ?? 'desconocida',
      fetchedAt: fetched.toUtc(),
      ageSeconds: retrieval['antiguedadSegundos'] is int
          ? retrieval['antiguedadSegundos'] as int
          : null,
      total: total,
      summary: summary,
      coverage: coverage,
      warnings: value['advertencias'] is List
          ? List<dynamic>.unmodifiable(value['advertencias'] as List)
          : const [],
      sources: value['fuentes'] is List
          ? List<dynamic>.unmodifiable(value['fuentes'] as List)
          : const [],
      page: page as int,
      pageSize: pageSize,
      nextPage: next is int ? next : null,
      setVersion: setVersion,
    );
  }
}

class TerritoryBoundary {
  const TerritoryBoundary({
    required this.identifier,
    required this.catalogVersion,
    required this.polygons,
    required this.bounds,
  });

  final String identifier;
  final String catalogVersion;
  final List<Polygon> polygons;
  final LatLngBounds? bounds;

  factory TerritoryBoundary.fromJson(
    Object? json, {
    required String expectedId,
  }) {
    final value = _map(json);
    final territory = _map(value['territorio']);
    final provenance = _map(value['procedencia']);
    final feature = _map(value['limite']);
    final geometry = _map(feature['geometry']);
    final type = geometry['type'];
    final coordinates = geometry['coordinates'];
    final polygons = <Polygon>[];
    final allPoints = <LatLng>[];

    List<List<dynamic>> ringsFor(Object? polygonCoordinates) {
      if (polygonCoordinates is! List) return const [];
      return polygonCoordinates
          .whereType<List>()
          .map((ring) => ring.cast<dynamic>())
          .toList(growable: false);
    }

    final polygonCoordinates = switch (type) {
      'Polygon' => <Object?>[coordinates],
      'MultiPolygon' when coordinates is List => coordinates.cast<Object?>(),
      _ => const <Object?>[],
    };

    for (final item in polygonCoordinates) {
      final rings = ringsFor(item);
      if (rings.isEmpty) continue;
      List<LatLng> parseRing(List<dynamic> ring) => ring
          .whereType<List>()
          .where(
            (point) => point.length >= 2 && point[0] is num && point[1] is num,
          )
          .map(
            (point) => LatLng(
              (point[1] as num).toDouble(),
              (point[0] as num).toDouble(),
            ),
          )
          .toList(growable: false);
      final exterior = parseRing(rings.first);
      if (exterior.length < 3) continue;
      final holes = rings
          .skip(1)
          .map(parseRing)
          .where((ring) => ring.length >= 3)
          .toList();
      allPoints.addAll(exterior);
      polygons.add(
        Polygon(
          points: exterior,
          holePointsList: holes,
          color: const Color(0x223f765e),
          borderColor: const Color(0xff49705b),
          borderStrokeWidth: 1.6,
        ),
      );
    }

    if (territory['identificador'] != expectedId ||
        provenance['version'] == null ||
        polygons.isEmpty) {
      throw const FormatException(
        'El límite territorial no coincide con la consulta.',
      );
    }
    return TerritoryBoundary(
      identifier: expectedId,
      catalogVersion: '${provenance['version']}',
      polygons: List<Polygon>.unmodifiable(polygons),
      bounds: allPoints.isEmpty ? null : LatLngBounds.fromPoints(allPoints),
    );
  }
}
