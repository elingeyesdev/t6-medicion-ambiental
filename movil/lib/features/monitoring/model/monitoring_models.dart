import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

String formatApiInstant(DateTime value) {
  final utc = value.toUtc();
  String twoDigits(int number) => number.toString().padLeft(2, '0');

  return '${utc.year.toString().padLeft(4, '0')}-'
      '${twoDigits(utc.month)}-${twoDigits(utc.day)}T'
      '${twoDigits(utc.hour)}:${twoDigits(utc.minute)}:'
      '${twoDigits(utc.second)}Z';
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((key, value) => MapEntry('$key', value));
  return const {};
}

class CountryOption {
  const CountryOption(this.code, this.name);

  final String code;
  final String name;
}

class RegionOption {
  const RegionOption({
    required this.code,
    required this.country,
    required this.name,
  });

  final String code;
  final String country;
  final String name;
}

class MonitoringFilters {
  const MonitoringFilters({
    this.country,
    this.region,
    this.metric = 'temperatura',
    this.origin,
    required this.from,
    required this.until,
    this.page = 1,
  });

  final String? country;
  final String? region;
  final String metric;
  final String? origin;
  final DateTime from;
  final DateTime until;
  final int page;

  Map<String, String> toQuery() {
    final query = <String, String>{
      'metrica': metric,
      'desde': formatApiInstant(from),
      'hasta': formatApiInstant(until),
      'pagina': '$page',
    };
    if (country case final country?) query['pais'] = country;
    if (region case final region?) query['region'] = region;
    if (origin case final origin?) query['origen'] = origin;
    return query;
  }

  MonitoringFilters copyWith({
    String? country,
    bool clearCountry = false,
    String? region,
    bool clearRegion = false,
    String? metric,
    String? origin,
    bool clearOrigin = false,
    DateTime? from,
    DateTime? until,
    int? page,
  }) => MonitoringFilters(
    country: clearCountry ? null : (country ?? this.country),
    region: clearRegion ? null : (region ?? this.region),
    metric: metric ?? this.metric,
    origin: clearOrigin ? null : (origin ?? this.origin),
    from: from ?? this.from,
    until: until ?? this.until,
    page: page ?? this.page,
  );
}

class MonitoringReading {
  const MonitoringReading({
    required this.value,
    required this.at,
    required this.origin,
    required this.source,
    this.latitude,
    this.longitude,
  });

  final num value;
  final DateTime? at;
  final String origin;
  final String source;
  final double? latitude;
  final double? longitude;

  factory MonitoringReading.fromJson(Object? value) {
    final json = _asMap(value);
    final number = json['valor'];
    if (number is! num) throw const FormatException('Lectura sin valor.');
    return MonitoringReading(
      value: number,
      at: DateTime.tryParse('${json['instante'] ?? ''}')?.toLocal(),
      origin: '${json['origen'] ?? 'desconocido'}',
      source: '${json['fuente'] ?? 'Sin fuente'}',
      latitude: (json['latitud'] as num?)?.toDouble(),
      longitude: (json['longitud'] as num?)?.toDouble(),
    );
  }
}

class MonitoringPoint {
  const MonitoringPoint({
    required this.code,
    required this.name,
    required this.country,
    required this.latitude,
    required this.longitude,
    required this.type,
    required this.readings,
  });

  final String code;
  final String name;
  final String country;
  final double latitude;
  final double longitude;
  final String type;
  final List<MonitoringReading> readings;

  bool get hasData => readings.isNotEmpty;

  factory MonitoringPoint.fromJson(Object? value) {
    final json = _asMap(value);
    final readings = json['lecturas'];
    return MonitoringPoint(
      code: '${json['codigo'] ?? ''}',
      name: '${json['nombre'] ?? 'Punto sin nombre'}',
      country: '${json['pais'] ?? ''}',
      latitude: (json['latitud'] as num?)?.toDouble() ?? 0,
      longitude: (json['longitud'] as num?)?.toDouble() ?? 0,
      type: '${json['tipo'] ?? 'referencia'}',
      readings: readings is List
          ? List.unmodifiable(readings.map(MonitoringReading.fromJson))
          : const [],
    );
  }
}

class MonitoringBoundary {
  const MonitoringBoundary({required this.polygons, required this.bounds});

  final List<Polygon> polygons;
  final LatLngBounds? bounds;

  factory MonitoringBoundary.fromFeature(Object? value) {
    final feature = _asMap(value);
    final geometry = _asMap(feature['geometry']);
    final type = geometry['type'];
    final coordinates = geometry['coordinates'];
    final polygonGroups = switch (type) {
      'Polygon' => <Object?>[coordinates],
      'MultiPolygon' when coordinates is List => coordinates.cast<Object?>(),
      _ => const <Object?>[],
    };
    final polygons = <Polygon>[];
    final allPoints = <LatLng>[];

    for (final group in polygonGroups) {
      if (group is! List || group.isEmpty || group.first is! List) continue;
      List<LatLng> parseRing(Object? ring) {
        if (ring is! List) return const [];
        return ring
            .whereType<List>()
            .where(
              (point) =>
                  point.length >= 2 && point[0] is num && point[1] is num,
            )
            .map(
              (point) => LatLng(
                (point[1] as num).toDouble(),
                (point[0] as num).toDouble(),
              ),
            )
            .toList(growable: false);
      }

      final exterior = parseRing(group.first);
      if (exterior.length < 3) continue;
      final holes = group
          .skip(1)
          .map(parseRing)
          .where((ring) => ring.length >= 3)
          .toList();
      allPoints.addAll(exterior);
      polygons.add(
        Polygon(
          points: exterior,
          holePointsList: holes,
          color: const Color(0x22155b3d),
          borderColor: const Color(0xff155b3d),
          borderStrokeWidth: 1.8,
        ),
      );
    }
    return MonitoringBoundary(
      polygons: List.unmodifiable(polygons),
      bounds: allPoints.isEmpty ? null : LatLngBounds.fromPoints(allPoints),
    );
  }
}

class MonitoringResponse {
  const MonitoringResponse({
    required this.metric,
    required this.unit,
    required this.from,
    required this.until,
    required this.origin,
    required this.points,
    required this.boundary,
    required this.page,
    required this.lastPage,
    required this.total,
    required this.notice,
  });

  final String metric;
  final String unit;
  final DateTime? from;
  final DateTime? until;
  final String origin;
  final List<MonitoringPoint> points;
  final MonitoringBoundary? boundary;
  final int page;
  final int lastPage;
  final int total;
  final String notice;

  factory MonitoringResponse.fromJson(Object? value) {
    final json = _asMap(value);
    final points = json['datos'];
    if (points is! List) {
      throw const FormatException('La API no devolvió puntos.');
    }
    final boundaryJson = json['limite'];
    final boundary = boundaryJson is Map
        ? MonitoringBoundary.fromFeature(boundaryJson)
        : null;
    return MonitoringResponse(
      metric: '${json['metrica'] ?? ''}',
      unit: '${json['unidad'] ?? ''}',
      from: DateTime.tryParse('${json['desde'] ?? ''}')?.toLocal(),
      until: DateTime.tryParse('${json['hasta'] ?? ''}')?.toLocal(),
      origin: '${json['origen'] ?? 'sin-simulaciones'}',
      points: List.unmodifiable(points.map(MonitoringPoint.fromJson)),
      boundary: boundary,
      page: (json['pagina'] as num?)?.toInt() ?? 1,
      lastPage: (json['ultimaPagina'] as num?)?.toInt() ?? 1,
      total: (json['total'] as num?)?.toInt() ?? points.length,
      notice: '${json['aviso'] ?? ''}',
    );
  }
}
