import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

/// Mismo rango efectivo para clip de imagen, mango y partículas de viento.
const double kSwipeDivisionMin = 0.05;
const double kSwipeDivisionMax = 0.95;

double effectiveSwipeDivision(double division) =>
    division.clamp(kSwipeDivisionMin, kSwipeDivisionMax);

/// Recorta capa A a la izquierda y B a la derecha (como el clip de panes en la web #62).
class SwipeCompareOverlays extends StatelessWidget {
  const SwipeCompareOverlays({
    required this.layerA,
    required this.layerB,
    required this.division,
    super.key,
  });

  final OverlayImage layerA;
  final OverlayImage layerB;
  final double division;

  @override
  Widget build(BuildContext context) {
    final t = effectiveSwipeDivision(division);
    return Stack(
      fit: StackFit.expand,
      children: [
        ClipRect(
          clipper: _FractionClipper(from: 0, to: t),
          child: OverlayImageLayer(overlayImages: [layerA]),
        ),
        ClipRect(
          clipper: _FractionClipper(from: t, to: 1),
          child: OverlayImageLayer(overlayImages: [layerB]),
        ),
      ],
    );
  }
}

class _FractionClipper extends CustomClipper<Rect> {
  _FractionClipper({required this.from, required this.to});

  final double from;
  final double to;

  @override
  Rect getClip(Size size) {
    final left = size.width * from;
    final right = size.width * to;
    return Rect.fromLTRB(left, 0, right, size.height);
  }

  @override
  bool shouldReclip(covariant _FractionClipper old) =>
      old.from != from || old.to != to;
}

/// Línea vertical arrastrable sobre el mapa (divisor A | B).
class SwipeCompareHandle extends StatelessWidget {
  const SwipeCompareHandle({
    required this.division,
    required this.onChanged,
    super.key,
  });

  final double division;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return LayoutBuilder(
      builder: (context, constraints) {
        final x = constraints.maxWidth * effectiveSwipeDivision(division);
        return Stack(
          children: [
            Positioned(
              left: x - 1,
              top: 0,
              bottom: 0,
              child: IgnorePointer(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.95),
                    boxShadow: const [
                      BoxShadow(color: Colors.black54, blurRadius: 3),
                    ],
                  ),
                  child: const SizedBox(width: 2, height: double.infinity),
                ),
              ),
            ),
            Positioned(
              left: x - 18,
              top: 0,
              bottom: 0,
              width: 36,
              child: GestureDetector(
                behavior: HitTestBehavior.translucent,
                onHorizontalDragUpdate: (details) {
                  final next =
                      (details.localPosition.dx + x - 18) / constraints.maxWidth;
                  onChanged(effectiveSwipeDivision(next));
                },
                child: Center(
                  child: Material(
                    elevation: 4,
                    color: scheme.primary,
                    borderRadius: BorderRadius.circular(20),
                    child: const SizedBox(
                      width: 28,
                      height: 44,
                      child: Icon(Icons.drag_handle_rounded, color: Colors.white, size: 20),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
