import 'dart:typed_data';

import 'package:flutter_map/flutter_map.dart';

import 'capas_models.dart';

/// Datos de un canal (A/B) para muestreo en el centro y partículas de viento.
class CapaCanalSample {
  const CapaCanalSample({
    required this.cuadro,
    required this.pixels,
    required this.nombre,
    required this.unidad,
  });

  final CapaCuadro cuadro;
  final Uint8List pixels;
  final String nombre;
  final String unidad;

  String get capaId => cuadro.capaId;
  bool get esViento => const {
        'viento-uv',
        'viento-uv127',
        'viento-direccion',
      }.contains(cuadro.codificacion);
}

/// Estado de capas raster para el mapa (comparación tipo web: A izquierda · B derecha).
class CapasOverlayState {
  const CapasOverlayState({
    this.layerA,
    this.layerB,
    this.comparing = false,
    this.division = 0.5,
    this.visible = false,
    this.sampleA,
    this.sampleB,
    this.animarViento = false,
    this.opacity = 0.65,
  });

  static const empty = CapasOverlayState();

  final OverlayImage? layerA;
  final OverlayImage? layerB;
  final bool comparing;
  final double division;
  final bool visible;
  final CapaCanalSample? sampleA;
  final CapaCanalSample? sampleB;
  final bool animarViento;
  final double opacity;

  bool get hasContent => visible && layerA != null;

  bool get tieneViento =>
      (sampleA?.esViento ?? false) ||
      (comparing && (sampleB?.esViento ?? false));

  CapasOverlayState copyWith({
    OverlayImage? layerA,
    OverlayImage? layerB,
    bool? comparing,
    double? division,
    bool? visible,
    CapaCanalSample? sampleA,
    CapaCanalSample? sampleB,
    bool? animarViento,
    double? opacity,
    bool clearLayerB = false,
    bool clearSampleB = false,
  }) {
    return CapasOverlayState(
      layerA: layerA ?? this.layerA,
      layerB: clearLayerB ? null : (layerB ?? this.layerB),
      comparing: comparing ?? this.comparing,
      division: division ?? this.division,
      visible: visible ?? this.visible,
      sampleA: sampleA ?? this.sampleA,
      sampleB: clearSampleB ? null : (sampleB ?? this.sampleB),
      animarViento: animarViento ?? this.animarViento,
      opacity: opacity ?? this.opacity,
    );
  }
}
