import 'package:latlong2/latlong.dart';

const waterClasses = <String, String>{
  'agua_persistente': 'Agua en ambas fechas',
  'agua_nueva': 'Agua nueva',
  'agua_retirada': 'Agua retirada',
  'no_comparable': 'Sin comparación',
};

class WaterCatalog {
  const WaterCatalog({required this.products});

  final List<WaterProductOption> products;

  factory WaterCatalog.fromJson(Object? value) {
    final json = _asMap(
      value,
      'El catálogo de agua no tiene el formato esperado.',
    );
    if (json['versionContrato'] != 'agua-0.1-candidata' ||
        json['estado'] is! String ||
        json['productos'] is! List) {
      throw const FormatException(
        'El catálogo de agua no tiene el formato esperado.',
      );
    }
    final raw = json['productos'] as List;
    if (raw.length > 20) {
      throw const FormatException(
        'El catálogo de agua supera el límite permitido.',
      );
    }
    final products =
        raw.map(WaterProductOption.fromJson).toList(growable: false)
          ..sort((a, b) => b.comparisonDate.compareTo(a.comparisonDate));
    final ids = <String>{};
    final identities = <String>{};
    for (final product in products) {
      if (!ids.add(product.id) || !identities.add(product.identity)) {
        throw const FormatException(
          'El catálogo contiene comparaciones repetidas.',
        );
      }
    }
    final expectedState = products.isEmpty
        ? 'sin_productos_validados'
        : 'disponible';
    if (json['estado'] != expectedState) {
      throw const FormatException(
        'El estado del catálogo no coincide con sus productos.',
      );
    }
    return WaterCatalog(products: List.unmodifiable(products));
  }
}

class WaterProductOption {
  const WaterProductOption({
    required this.id,
    required this.territoryId,
    required this.territoryName,
    required this.referenceDate,
    required this.comparisonDate,
    required this.state,
  });

  final String id;
  final String territoryId;
  final String territoryName;
  final String referenceDate;
  final String comparisonDate;
  final String state;

  String get identity => '$territoryId|$referenceDate|$comparisonDate';

  factory WaterProductOption.fromJson(Object? value) {
    final json = _asMap(value, 'Una comparación del catálogo no es válida.');
    final territory = _asMap(json['territorio'], 'Falta el territorio.');
    final comparison = _asMap(json['comparacion'], 'Faltan las fechas.');
    final id = json['id'];
    final territoryId = territory['identificador'];
    final territoryName = territory['nombre'];
    final reference = comparison['referencia'];
    final compared = comparison['comparacion'];
    final state = json['estado'];
    if (id is! String ||
        !RegExp(r'^[a-f0-9]{64}$').hasMatch(id) ||
        territoryId is! String ||
        !RegExp(r'^BO07(?:[0-9]{2}){0,2}$').hasMatch(territoryId) ||
        territoryName is! String ||
        territoryName.trim().isEmpty ||
        territory['alcance'] != 'recorte_parcial' ||
        !_isDate(reference) ||
        !_isDate(compared) ||
        reference.compareTo(compared) >= 0 ||
        !const {
          'cambio_agua_observado',
          'sin_cambios',
          'sin_datos',
        }.contains(state)) {
      throw const FormatException('Una comparación del catálogo no es válida.');
    }
    return WaterProductOption(
      id: id,
      territoryId: territoryId,
      territoryName: territoryName,
      referenceDate: reference,
      comparisonDate: compared,
      state: state as String,
    );
  }
}

class WaterResult {
  const WaterResult({
    required this.state,
    required this.territoryId,
    required this.territoryName,
    required this.referenceDate,
    required this.comparisonDate,
    required this.areaStudiedKm2,
    required this.areaComparableKm2,
    required this.comparableFraction,
    required this.persistentKm2,
    required this.newKm2,
    required this.removedKm2,
    required this.sourceName,
    required this.sourceProduct,
    required this.method,
    required this.resolutionMeters,
    required this.warnings,
    required this.features,
  });

  final String state;
  final String territoryId;
  final String territoryName;
  final String referenceDate;
  final String comparisonDate;
  final double areaStudiedKm2;
  final double areaComparableKm2;
  final double comparableFraction;
  final double? persistentKm2;
  final double? newKm2;
  final double? removedKm2;
  final String sourceName;
  final String sourceProduct;
  final String method;
  final double resolutionMeters;
  final List<String> warnings;
  final List<WaterFeature> features;

  String get stateLabel => switch (state) {
    'cambio_agua_observado' => 'Cambio de agua observado',
    'sin_cambios' => 'Sin cambios observados',
    'sin_datos' => 'Sin datos comparables',
    _ => 'Estado no disponible',
  };

  factory WaterResult.fromJson(Object? value) {
    final json = _asMap(value, 'La comparación no tiene el formato esperado.');
    final territory = _asMap(json['territorio'], 'Falta el territorio.');
    final comparison = _asMap(json['comparacion'], 'Faltan las fechas.');
    final coverage = _asMap(json['cobertura'], 'Falta la cobertura.');
    final summary = _asMap(json['resumen'], 'Falta el resumen.');
    final source = _asMap(json['fuente'], 'Falta la fuente.');
    final layer = _asMap(json['capa'], 'Falta la capa del mapa.');
    if (json['versionContrato'] != 'agua-0.1-candidata' ||
        json['tipoEvidencia'] != 'resultado_validado' ||
        !const {
          'cambio_agua_observado',
          'sin_cambios',
          'sin_datos',
        }.contains(json['estado']) ||
        territory['alcance'] != 'recorte_parcial' ||
        territory['identificador'] is! String ||
        territory['nombre'] is! String ||
        !_isDate(comparison['referencia']) ||
        !_isDate(comparison['comparacion']) ||
        (comparison['referencia'] as String).compareTo(
              comparison['comparacion'] as String,
            ) >=
            0) {
      throw const FormatException(
        'La comparación no coincide con el contrato de agua.',
      );
    }
    final studied = _number(
      coverage['areaEstudiadaKm2'],
      'El área estudiada no es válida.',
    );
    final comparable = _number(
      coverage['areaComparableKm2'],
      'El área comparable no es válida.',
    );
    final fraction = _number(
      coverage['fraccionComparable'],
      'La cobertura no es válida.',
    );
    if (studied <= 0 ||
        comparable < 0 ||
        comparable > studied ||
        fraction < 0 ||
        fraction > 1 ||
        (fraction - comparable / studied).abs() > 1e-8) {
      throw const FormatException('La cobertura del resultado no coincide.');
    }
    final persistent = _optionalNumber(summary['aguaPersistenteKm2']);
    final newWater = _optionalNumber(summary['aguaNuevaKm2']);
    final removed = _optionalNumber(summary['aguaRetiradaKm2']);
    if ([
          persistent,
          newWater,
          removed,
        ].whereType<double>().any((item) => item < 0) ||
        summary['inundacionConfirmada'] != null) {
      throw const FormatException('El resumen del agua no es válido.');
    }
    final state = json['estado'] as String;
    if (state == 'sin_datos') {
      if (persistent != null ||
          newWater != null ||
          removed != null ||
          comparable != 0) {
        throw const FormatException(
          'La ausencia de datos no coincide con el resumen.',
        );
      }
    } else if (persistent == null ||
        newWater == null ||
        removed == null ||
        comparable <= 0 ||
        persistent + newWater + removed > comparable + 1e-8 ||
        ((state == 'sin_cambios') != (newWater == 0 && removed == 0))) {
      throw const FormatException(
        'El resumen excede o contradice la cobertura.',
      );
    }
    final rawFeatures = layer['features'];
    if (layer['type'] != 'FeatureCollection' ||
        rawFeatures is! List ||
        rawFeatures.length > 500) {
      throw const FormatException('La capa del mapa no es válida.');
    }
    final features = rawFeatures
        .map(WaterFeature.fromJson)
        .toList(growable: false);
    final featureIds = features.map((item) => item.id).toSet();
    if (featureIds.length != features.length) {
      throw const FormatException(
        'La capa del mapa contiene ubicaciones repetidas.',
      );
    }
    if (state == 'sin_datos' &&
        features.any((item) => item.kind != 'no_comparable')) {
      throw const FormatException(
        'La capa no coincide con la ausencia de datos.',
      );
    }
    if (state == 'sin_cambios' &&
        features.any(
          (item) => const {'agua_nueva', 'agua_retirada'}.contains(item.kind),
        )) {
      throw const FormatException('La capa contradice el resumen de cambios.');
    }
    final warningsRaw = json['advertencias'];
    if (warningsRaw is! List || warningsRaw.any((item) => item is! String)) {
      throw const FormatException('Las advertencias no son válidas.');
    }
    final sourceName = _nonEmptyString(source['nombre'], 'Falta la fuente.');
    final sourceProduct = _nonEmptyString(
      source['producto'],
      'Falta el producto de la fuente.',
    );
    final method = _nonEmptyString(source['metodo'], 'Falta el método.');
    final resolution = _number(
      source['resolucionMetros'],
      'La resolución no es válida.',
    );
    if (resolution <= 0) {
      throw const FormatException('La resolución no es válida.');
    }
    return WaterResult(
      state: state,
      territoryId: territory['identificador'] as String,
      territoryName: territory['nombre'] as String,
      referenceDate: comparison['referencia'] as String,
      comparisonDate: comparison['comparacion'] as String,
      areaStudiedKm2: studied,
      areaComparableKm2: comparable,
      comparableFraction: fraction,
      persistentKm2: persistent,
      newKm2: newWater,
      removedKm2: removed,
      sourceName: sourceName,
      sourceProduct: sourceProduct,
      method: method,
      resolutionMeters: resolution,
      warnings: List.unmodifiable(warningsRaw.cast<String>()),
      features: List.unmodifiable(features),
    );
  }
}

class WaterFeature {
  const WaterFeature({
    required this.id,
    required this.kind,
    required this.rings,
  });

  final String id;
  final String kind;
  final List<List<LatLng>> rings;

  factory WaterFeature.fromJson(Object? value) {
    final json = _asMap(value, 'Una ubicación del mapa no es válida.');
    final properties = _asMap(
      json['properties'],
      'Faltan datos de una ubicación.',
    );
    final geometry = _asMap(
      json['geometry'],
      'Falta la geometría de una ubicación.',
    );
    final id = json['id'];
    final kind = properties['clase'];
    final coordinates = geometry['coordinates'];
    if (id is! String ||
        id.isEmpty ||
        !waterClasses.containsKey(kind) ||
        geometry['type'] != 'Polygon' ||
        coordinates is! List ||
        coordinates.isEmpty ||
        coordinates.length > 20) {
      throw const FormatException('Una ubicación del mapa no es válida.');
    }
    final rings = <List<LatLng>>[];
    for (final rawRing in coordinates) {
      if (rawRing is! List || rawRing.length < 4 || rawRing.length > 10000) {
        throw const FormatException('Un contorno del mapa no es válido.');
      }
      final points = <LatLng>[];
      for (final rawPoint in rawRing) {
        if (rawPoint is! List ||
            rawPoint.length != 2 ||
            rawPoint[0] is! num ||
            rawPoint[1] is! num) {
          throw const FormatException('Una coordenada del mapa no es válida.');
        }
        final longitude = (rawPoint[0] as num).toDouble();
        final latitude = (rawPoint[1] as num).toDouble();
        if (!longitude.isFinite ||
            !latitude.isFinite ||
            longitude.abs() > 180 ||
            latitude.abs() > 90) {
          throw const FormatException('Una coordenada del mapa no es válida.');
        }
        points.add(LatLng(latitude, longitude));
      }
      if (points.first.latitude != points.last.latitude ||
          points.first.longitude != points.last.longitude) {
        throw const FormatException('Un contorno del mapa debe estar cerrado.');
      }
      rings.add(List.unmodifiable(points));
    }
    return WaterFeature(
      id: id,
      kind: kind as String,
      rings: List.unmodifiable(rings),
    );
  }
}

Map<String, dynamic> _asMap(Object? value, String message) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((key, item) => MapEntry('$key', item));
  throw FormatException(message);
}

double _number(Object? value, String message) {
  if (value is! num || !value.toDouble().isFinite) {
    throw FormatException(message);
  }
  return value.toDouble();
}

double? _optionalNumber(Object? value) =>
    value == null ? null : _number(value, 'Una superficie no es válida.');

String _nonEmptyString(Object? value, String message) {
  if (value is! String || value.trim().isEmpty) throw FormatException(message);
  return value;
}

bool _isDate(Object? value) {
  if (value is! String || !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(value)) {
    return false;
  }
  final parsed = DateTime.tryParse(value);
  return parsed != null && parsed.toIso8601String().startsWith(value);
}
