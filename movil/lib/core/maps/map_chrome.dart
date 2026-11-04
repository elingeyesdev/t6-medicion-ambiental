import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import 'map_base_styles.dart';

/// Controles compactos en esquina (hay que envolverlos en [Positioned.fill]).
class MapChrome extends StatelessWidget {
  const MapChrome({
    required this.style,
    required this.onStyleChanged,
    required this.onZoomIn,
    required this.onZoomOut,
    this.onFit,
    this.onFullscreen,
    this.corner = Alignment.topRight,
    super.key,
  });

  final MapBaseStyle style;
  final ValueChanged<MapBaseStyle> onStyleChanged;
  final VoidCallback onZoomIn;
  final VoidCallback onZoomOut;
  final VoidCallback? onFit;
  final VoidCallback? onFullscreen;
  final Alignment corner;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final panel = Material(
      elevation: 3,
      shadowColor: Colors.black26,
      borderRadius: BorderRadius.circular(22),
      color: scheme.surface.withValues(alpha: 0.92),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _tiny(Icons.add, 'Acercar', onZoomIn),
            _tiny(Icons.remove, 'Alejar', onZoomOut),
            if (onFit != null) _tiny(Icons.my_location_outlined, 'Ajustar', onFit!),
            if (onFullscreen != null)
              _tiny(Icons.fullscreen_rounded, 'Pantalla completa', onFullscreen!),
            PopupMenuButton<MapBaseStyle>(
              tooltip: 'Tipo de mapa',
              padding: EdgeInsets.zero,
              initialValue: style,
              onSelected: onStyleChanged,
              itemBuilder: (context) => [
                for (final option in MapBaseStyle.values)
                  CheckedPopupMenuItem(
                    value: option,
                    checked: option == style,
                    child: Text(option.label),
                  ),
              ],
              child: SizedBox(
                width: 36,
                height: 36,
                child: Icon(style.icon, size: 18, color: scheme.primary),
              ),
            ),
          ],
        ),
      ),
    );

    return Align(
      alignment: corner,
      child: Padding(padding: const EdgeInsets.all(10), child: panel),
    );
  }

  Widget _tiny(IconData icon, String tooltip, VoidCallback onPressed) {
    return SizedBox(
      width: 36,
      height: 36,
      child: IconButton(
        tooltip: tooltip,
        padding: EdgeInsets.zero,
        visualDensity: VisualDensity.compact,
        iconSize: 18,
        onPressed: onPressed,
        icon: Icon(icon),
      ),
    );
  }
}

Future<void> openFullscreenMap({
  required BuildContext context,
  required LatLng center,
  required double zoom,
  required List<Marker> markers,
  List<Polygon> polygons = const [],
  List<OverlayImage> overlays = const [],
  MapBaseStyle initialStyle = MapBaseStyle.relieve,
  String title = 'Mapa',
  LatLngBounds? fitBounds,
}) {
  return Navigator.of(context).push(
    MaterialPageRoute<void>(
      fullscreenDialog: true,
      builder: (_) => FullscreenMapPage(
        title: title,
        center: center,
        zoom: zoom,
        markers: markers,
        polygons: polygons,
        overlays: overlays,
        initialStyle: initialStyle,
        fitBounds: fitBounds,
      ),
    ),
  );
}

class FullscreenMapPage extends StatefulWidget {
  const FullscreenMapPage({
    required this.title,
    required this.center,
    required this.zoom,
    required this.markers,
    this.polygons = const [],
    this.overlays = const [],
    this.initialStyle = MapBaseStyle.relieve,
    this.fitBounds,
    super.key,
  });

  final String title;
  final LatLng center;
  final double zoom;
  final List<Marker> markers;
  final List<Polygon> polygons;
  final List<OverlayImage> overlays;
  final MapBaseStyle initialStyle;
  final LatLngBounds? fitBounds;

  @override
  State<FullscreenMapPage> createState() => _FullscreenMapPageState();
}

class _FullscreenMapPageState extends State<FullscreenMapPage> {
  final MapController _controller = MapController();
  late MapBaseStyle _style;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _style = widget.initialStyle;
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _zoom(double delta) {
    if (!_ready) return;
    final camera = _controller.camera;
    _controller.move(
      camera.center,
      (camera.zoom + delta).clamp(2.0, 18.0).toDouble(),
    );
  }

  void _fit() {
    if (!_ready) return;
    final bounds = widget.fitBounds;
    if (bounds != null) {
      _controller.fitCamera(
        CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(40)),
      );
      return;
    }
    if (widget.markers.length > 1) {
      _controller.fitCamera(
        CameraFit.bounds(
          bounds: LatLngBounds.fromPoints(
            widget.markers.map((m) => m.point).toList(),
          ),
          padding: const EdgeInsets.all(40),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        leading: IconButton(
          tooltip: 'Cerrar',
          icon: const Icon(Icons.close_rounded),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          FlutterMap(
            mapController: _controller,
            options: MapOptions(
              initialCenter: widget.center,
              initialZoom: widget.zoom,
              initialCameraFit: widget.fitBounds == null
                  ? null
                  : CameraFit.bounds(
                      bounds: widget.fitBounds!,
                      padding: const EdgeInsets.all(40),
                    ),
              minZoom: 2,
              maxZoom: 18,
              onMapReady: () => _ready = true,
            ),
            children: [
              _style.toLayer(),
              if (widget.overlays.isNotEmpty)
                OverlayImageLayer(overlayImages: widget.overlays),
              if (widget.polygons.isNotEmpty)
                PolygonLayer(polygons: widget.polygons),
              MarkerLayer(markers: widget.markers),
              RichAttributionWidget(
                alignment: AttributionAlignment.bottomLeft,
                attributions: [
                  TextSourceAttribution(_style.attribution),
                ],
              ),
            ],
          ),
          Positioned.fill(
            child: MapChrome(
              style: _style,
              onStyleChanged: (value) => setState(() => _style = value),
              onZoomIn: () => _zoom(1),
              onZoomOut: () => _zoom(-1),
              onFit: _fit,
            ),
          ),
        ],
      ),
    );
  }
}
