import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../features/monitoring/model/place_search_repository.dart';
import '../../features/monitoring/view/air_sheet.dart';
import '../../features/monitoring/view/place_search_sheet.dart';
import '../../features/monitoring/view/weather_sheet.dart';
import '../network/geocruz_api_client.dart';

/// Acciones compartidas del mapa completo (#62): buscar, tiempo y aire.
Future<PlaceSearchResult?> openPlaceSearch({
  required BuildContext context,
  required GeoCruzApiClient api,
  LatLng? near,
}) {
  return showModalBottomSheet<PlaceSearchResult>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => PlaceSearchSheet(api: api, near: near),
  );
}

Future<void> openWeatherSheet({
  required BuildContext context,
  required GeoCruzApiClient api,
  required LatLng point,
  String? title,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => WeatherSheet(api: api, point: point, title: title),
  );
}

Future<void> openAirSheet({
  required BuildContext context,
  required GeoCruzApiClient api,
  required LatLng point,
  String? title,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => AirSheet(api: api, point: point, title: title),
  );
}

/// Botones compactos de mapa completo (misma fila en Mapa / Focos / Sequías).
List<Widget> mapCompletoActions({
  required VoidCallback onSearch,
  required VoidCallback onWeather,
  VoidCallback? onAir,
}) =>
    [
      IconButton(
        tooltip: 'Buscar lugar',
        onPressed: onSearch,
        icon: const Icon(Icons.search_rounded),
      ),
      IconButton(
        tooltip: 'Tiempo y pronóstico',
        onPressed: onWeather,
        icon: const Icon(Icons.wb_cloudy_outlined),
      ),
      if (onAir != null)
        IconButton(
          tooltip: 'Calidad del aire',
          onPressed: onAir,
          icon: const Icon(Icons.air_rounded),
        ),
    ];
