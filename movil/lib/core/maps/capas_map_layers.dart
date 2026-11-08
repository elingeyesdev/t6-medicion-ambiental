import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import '../../features/monitoring/model/capas_overlay_state.dart';
import '../../features/monitoring/view/capas_leyenda.dart';
import '../units/units_presentation.dart';
import 'swipe_compare_layer.dart';
import 'viento_particulas_layer.dart';

/// Capas raster dentro de [FlutterMap.children] (con swipe A|B si aplica).
List<Widget> capasMapChildren(CapasOverlayState state) {
  if (!state.hasContent || state.layerA == null) return const [];
  if (state.comparing && state.layerB != null) {
    return [
      SwipeCompareOverlays(
        layerA: state.layerA!,
        layerB: state.layerB!,
        division: state.division,
      ),
    ];
  }
  return [
    OverlayImageLayer(overlayImages: [state.layerA!]),
  ];
}

/// Divisor arrastrable encima del mapa.
Widget? capasSwipeHandle(
  CapasOverlayState state,
  ValueChanged<double> onDivision,
) {
  if (!state.comparing || !state.hasContent) return null;
  return SwipeCompareHandle(
    division: state.division,
    onChanged: onDivision,
  );
}

/// Leyenda compacta + partículas de viento (stack sobre el mapa).
List<Widget> capasMapOverlays({
  required CapasOverlayState state,
  required MapController mapController,
  UnitsPresentation? units,
}) {
  if (!state.hasContent) return const [];
  return [
    if (state.animarViento && state.tieneViento)
      Positioned.fill(
        child: VientoParticulasOverlay(
          mapController: mapController,
          state: state,
        ),
      ),
    if (state.sampleA != null)
      Positioned(
        left: 12,
        right: 12,
        bottom: 8,
        child: IgnorePointer(
          child: CapasLeyendaBar(
            capaId: state.sampleA!.capaId,
            nombre: state.sampleA!.nombre,
            compact: true,
          ),
        ),
      ),
  ];
}
