import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

/// [OverlayImage] que no pinta el Placeholder rojo de depuración si la imagen falla
/// al re-resolverse (p. ej. mapa dentro de ListView al hacer scroll).
class SafeOverlayImage extends OverlayImage {
  const SafeOverlayImage({
    required super.imageProvider,
    required super.bounds,
    super.opacity,
    super.filterQuality,
    super.key,
  }) : super(gaplessPlayback: true);

  @override
  Widget build(BuildContext context) {
    final camera = MapCamera.of(context);
    final rect = Rect.fromPoints(
      camera.projectAtZoom(bounds.northWest) - camera.pixelOrigin,
      camera.projectAtZoom(bounds.southEast) - camera.pixelOrigin,
    );
    return Positioned(
      left: rect.topLeft.dx,
      top: rect.topLeft.dy,
      width: rect.size.width,
      height: rect.size.height,
      child: Image(
        image: imageProvider,
        fit: BoxFit.fill,
        gaplessPlayback: true,
        opacity: AlwaysStoppedAnimation(opacity),
        filterQuality: filterQuality,
        errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
      ),
    );
  }
}
