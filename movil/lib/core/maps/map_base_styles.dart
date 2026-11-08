import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

/// Estilos de mapa base alineados con la web (claro / relieve / satélite / calles).
/// Usa teselas públicas sin token en el cliente (Mapbox de la web requiere pk).
enum MapBaseStyle {
  claro('Claro', Icons.light_mode_outlined),
  relieve('Relieve', Icons.terrain_outlined),
  satelite('Satélite', Icons.satellite_alt_outlined),
  calles('Calles', Icons.map_outlined);

  const MapBaseStyle(this.label, this.icon);

  final String label;
  final IconData icon;

  String get urlTemplate => switch (this) {
    // Esri Light Gray: sin API key (Carto CDN exige apikey).
    MapBaseStyle.claro =>
      'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
    MapBaseStyle.relieve =>
      'https://tile.opentopomap.org/{z}/{x}/{y}.png',
    MapBaseStyle.satelite =>
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    MapBaseStyle.calles =>
      'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  };

  String get attribution => switch (this) {
    MapBaseStyle.claro => '© Esri · OpenStreetMap',
    MapBaseStyle.relieve => '© OpenTopoMap · © OpenStreetMap',
    MapBaseStyle.satelite => '© Esri · Maxar · Earthstar',
    MapBaseStyle.calles => '© OpenStreetMap contributors',
  };

  TileLayer toLayer() => TileLayer(
    urlTemplate: urlTemplate,
    userAgentPackageName: 'bo.edu.univalle.geocruz_movil',
    maxNativeZoom: this == MapBaseStyle.relieve ? 17 : 19,
    // Sin esto, una tesela fallida pinta el Placeholder rojo de depuración.
    errorImage: MemoryImage(TileProvider.transparentImage),
  );
}
