import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/theme/geocruz_theme.dart';
import '../model/hotspot_models.dart';

class HotspotMapCard extends StatefulWidget {
  const HotspotMapCard({
    required this.records,
    required this.boundary,
    required this.territoryName,
    required this.onOpenRecord,
    super.key,
  });

  final List<HotspotRecord> records;
  final TerritoryBoundary? boundary;
  final String territoryName;
  final ValueChanged<HotspotRecord> onOpenRecord;

  @override
  State<HotspotMapCard> createState() => _HotspotMapCardState();
}

class _HotspotMapCardState extends State<HotspotMapCard> {
  final MapController _mapController = MapController();
  bool _tileError = false;
  bool _mapReady = false;

  static const _defaultCenter = LatLng(-17.5, -61.5);

  @override
  void didUpdateWidget(covariant HotspotMapCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.boundary?.identifier != widget.boundary?.identifier) {
      _tileError = false;
    }
  }

  void _centerTerritory() {
    final bounds = widget.boundary?.bounds;
    if (_mapReady && bounds != null) {
      _mapController.fitCamera(
        CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(28)),
      );
    } else if (_mapReady) {
      _mapController.move(_defaultCenter, 5.8);
    }
  }

  @override
  Widget build(BuildContext context) {
    final boundary = widget.boundary;
    final bounds = boundary?.bounds;
    final markers = widget.records
        .where((record) => record.coordinates != null)
        .map((record) {
          final selectedColor = switch (record.confidence) {
            'alta' => const Color(0xffbd671d),
            'nominal' => const Color(0xff25856b),
            'baja' => const Color(0xffad9a53),
            _ => const Color(0xff788084),
          };
          return Marker(
            point: record.coordinates!,
            width: 38,
            height: 38,
            child: Semantics(
              button: true,
              label: 'Abrir detección ${record.identifier}',
              child: Material(
                color: Colors.transparent,
                child: InkResponse(
                  onTap: () => widget.onOpenRecord(record),
                  radius: 22,
                  child: Center(
                    child: Container(
                      width: 17,
                      height: 17,
                      decoration: BoxDecoration(
                        color: selectedColor,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 2),
                        boxShadow: const [
                          BoxShadow(color: Color(0x44000000), blurRadius: 4),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        })
        .toList(growable: false);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 10),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Mapa de detecciones',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        widget.territoryName,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Centrar territorio',
                  onPressed: _centerTerritory,
                  icon: const Icon(Icons.center_focus_strong_rounded),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 270,
            child: Stack(
              children: [
                FlutterMap(
                  key: ValueKey(boundary?.identifier ?? 'santa-cruz'),
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: _defaultCenter,
                    initialZoom: 5.8,
                    initialCameraFit: bounds == null
                        ? null
                        : CameraFit.bounds(
                            bounds: bounds,
                            padding: const EdgeInsets.all(28),
                          ),
                    minZoom: 4,
                    maxZoom: 18,
                    onMapReady: () => _mapReady = true,
                  ),
                  children: [
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'bo.edu.univalle.geocruz_movil',
                      maxNativeZoom: 19,
                      maxZoom: 18,
                      errorTileCallback: (tile, error, stackTrace) {
                        if (mounted && !_tileError) {
                          setState(() => _tileError = true);
                        }
                      },
                    ),
                    if (boundary != null)
                      PolygonLayer(polygons: boundary.polygons),
                    MarkerLayer(markers: markers),
                    const RichAttributionWidget(
                      alignment: AttributionAlignment.bottomRight,
                      attributions: [
                        TextSourceAttribution('© OpenStreetMap contributors'),
                      ],
                    ),
                  ],
                ),
                if (_tileError)
                  Positioned(
                    left: 10,
                    right: 10,
                    bottom: 42,
                    child: Material(
                      color: Colors.white.withValues(alpha: 0.95),
                      borderRadius: BorderRadius.circular(10),
                      child: const Padding(
                        padding: EdgeInsets.all(10),
                        child: Text(
                          'No se cargó el mapa base. Los puntos, el listado y los detalles siguen disponibles.',
                          style: TextStyle(fontSize: 12),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
            child: Row(
              children: [
                const Icon(Icons.circle, size: 9, color: GeoCruzColors.green),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    '${markers.length} de esta página · color por confianza',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
                const Text(
                  '© OpenStreetMap',
                  style: TextStyle(fontSize: 10, color: GeoCruzColors.textSoft),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
