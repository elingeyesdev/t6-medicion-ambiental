import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/formatters/consultation_period.dart';
import '../../../core/maps/map_base_styles.dart';
import '../../../core/maps/map_chrome.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../../core/widgets/geosur_brand.dart';
import '../../../core/widgets/units_scope.dart';
import '../model/monitoring_models.dart';
import '../model/monitoring_repository.dart';
import '../model/place_search_repository.dart';
import 'air_sheet.dart';
import 'capas_panel.dart';
import 'place_search_sheet.dart';
import 'weather_sheet.dart';

/// Métricas del catálogo EnviroSense (puntos). Pronóstico vía `/tiempo`;
/// calidad del aire puntual vía `/aire`; capas raster GFS vía `/mapa/capas`.
const _metrics = <String, String>{
  'temperatura': 'Temperatura',
  'humedad': 'Humedad',
  'aqi': 'Calidad del aire',
  'ica': 'Índice de agua',
  'ruido': 'Ruido',
};

class MonitoringScreen extends StatefulWidget {
  const MonitoringScreen({
    required this.api,
    required this.onUnauthorized,
    this.repository,
    super.key,
  });

  final GeoCruzApiClient api;
  final VoidCallback onUnauthorized;
  final MonitoringRepository? repository;

  @override
  State<MonitoringScreen> createState() => _MonitoringScreenState();
}

class _MonitoringScreenState extends State<MonitoringScreen> {
  late final MonitoringRepository _repository;
  final MapController _mapController = MapController();
  late MonitoringFilters _filters;
  List<CountryOption> _countries = const [];
  List<RegionOption> _regions = const [];
  MonitoringResponse? _response;
  MonitoringPoint? _selected;
  Object? _error;
  bool _loading = true;
  bool _catalogReady = false;
  bool _mapReady = false;
  List<OverlayImage> _capaOverlays = const [];
  MapBaseStyle _mapStyle = MapBaseStyle.relieve;
  LatLng? _searchPin;
  String? _searchPinLabel;

  @override
  void initState() {
    super.initState();
    _repository = widget.repository ?? MonitoringRepository(widget.api);
    final period = ConsultationPeriod.lastSevenDays();
    _filters = MonitoringFilters(from: period.from, until: period.until);
    _initialize();
  }

  Future<void> _initialize() async {
    try {
      final values = await Future.wait([
        _repository.loadCountries(),
        _repository.loadRegions(),
      ]);
      if (!mounted) return;
      _countries = values[0] as List<CountryOption>;
      _regions = values[1] as List<RegionOption>;
      _catalogReady = true;
      await _query();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  Future<void> _query({int page = 1}) async {
    if (!_catalogReady) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      ConsultationPeriod.validate(_filters.from, _filters.until);
      final result = await _repository.query(_filters.copyWith(page: page));
      if (!mounted) return;
      setState(() {
        _filters = _filters.copyWith(page: result.page);
        _response = result;
        _selected = null;
        _loading = false;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) widget.onUnauthorized();
      setState(() {
        _error = error;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _openSearch() async {
    final near = _selected != null
        ? LatLng(_selected!.latitude, _selected!.longitude)
        : (_response?.points.isNotEmpty == true
              ? LatLng(
                  _response!.points.first.latitude,
                  _response!.points.first.longitude,
                )
              : const LatLng(-17.7833, -63.1821));
    final result = await showModalBottomSheet<PlaceSearchResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => PlaceSearchSheet(api: widget.api, near: near),
    );
    if (!mounted || result == null) return;
    final point = LatLng(result.latitude, result.longitude);
    setState(() {
      _searchPin = point;
      _searchPinLabel = result.name;
    });
    if (_mapReady) _mapController.move(point, 11);
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger.clearSnackBars();
    messenger.showSnackBar(
      SnackBar(
        content: Text(result.name),
        action: SnackBarAction(
          label: 'Tiempo',
          onPressed: () {
            showModalBottomSheet<void>(
              context: context,
              isScrollControlled: true,
              useSafeArea: true,
              showDragHandle: true,
              builder: (_) => WeatherSheet(
                api: widget.api,
                point: point,
                title: 'Tiempo · ${result.name}',
              ),
            );
          },
        ),
      ),
    );
  }

  LatLng _focusPoint() {
    if (_selected != null) {
      return LatLng(_selected!.latitude, _selected!.longitude);
    }
    if (_mapReady) return _mapController.camera.center;
    return const LatLng(-17.7833, -63.1821);
  }

  Future<void> _openWeather() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => WeatherSheet(
        api: widget.api,
        point: _focusPoint(),
        title: _selected?.name ?? 'Tiempo en el mapa',
      ),
    );
  }

  Future<void> _openAir() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => AirSheet(
        api: widget.api,
        point: _focusPoint(),
        title: _selected?.name ?? 'Calidad del aire',
      ),
    );
  }

  Future<void> _choosePeriod() async {
    final now = DateTime.now().toUtc();
    final today = ConsultationPeriod.boliviaDay(now);
    final initialEnd = ConsultationPeriod.boliviaDay(_filters.until.subtract(const Duration(seconds: 1)));
    final initialStart = ConsultationPeriod.boliviaDay(_filters.from);
    final selected = await showDateRangePicker(
      context: context,
      firstDate: DateTime(today.year - 5, today.month, today.day),
      lastDate: today,
      initialDateRange: DateTimeRange(
        start: initialEnd.difference(initialStart).inDays > 6 ? DateTime(initialEnd.year, initialEnd.month, initialEnd.day - 6) : initialStart,
        end: initialEnd,
      ),
      helpText: 'Elige un período de hasta 7 días',
      saveText: 'Aplicar período',
    );
    if (!mounted || selected == null) return;
    try {
      final period = ConsultationPeriod.selectedDays(selected.start, selected.end);
      setState(() {
        _filters = _filters.copyWith(from: period.from, until: period.until, page: 1);
      });
    } on FormatException catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  String _periodLabel() => '${BoliviaTime.interval(_filters.from, _filters.until)} · Bolivia';

  String _countryName(String? code) =>
      _countries
          .where((country) => country.code == code)
          .map((country) => country.name)
          .firstOrNull ??
      'Sudamérica';

  void _zoomMap(double change) {
    if (!_mapReady) return;
    final camera = _mapController.camera;
    final zoom = (camera.zoom + change).clamp(2.0, 17.0).toDouble();
    _mapController.move(camera.center, zoom);
  }

  void _centerMap() {
    if (!_mapReady || _response == null) return;
    final response = _response!;
    if (response.boundary?.bounds case final bounds?) {
      _mapController.fitCamera(
        CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(32)),
      );
      return;
    }
    final first = response.points.firstOrNull;
    if (first != null) {
      _mapController.move(LatLng(first.latitude, first.longitude), 7);
    } else {
      _mapController.move(const LatLng(-16.5, -63.5), 4);
    }
  }

  @override
  Widget build(BuildContext context) {
    final response = _response;
    return Scaffold(
      appBar: AppBar(
        title: const GeoSurBrand(compact: true),
        actions: [
          IconButton(
            tooltip: 'Buscar lugar',
            onPressed: _openSearch,
            icon: const Icon(Icons.search_rounded),
          ),
          IconButton(
            tooltip: 'Tiempo y pronóstico',
            onPressed: _openWeather,
            icon: const Icon(Icons.wb_cloudy_outlined),
          ),
          IconButton(
            tooltip: 'Calidad del aire',
            onPressed: _openAir,
            icon: const Icon(Icons.air_rounded),
          ),
          IconButton(
            tooltip: 'Centrar mapa',
            onPressed: response == null ? null : _centerMap,
            icon: const Icon(Icons.center_focus_strong_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _query(page: _filters.page),
        child: ListView(
          key: const PageStorageKey<String>('monitoring-scroll'),
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
          children: [
            _filtersCard(context),
            const SizedBox(height: 12),
            CapasPanel(
              api: widget.api,
              onOverlayChanged: (overlays) {
                if (!mounted) return;
                setState(() => _capaOverlays = overlays);
              },
            ),
            const SizedBox(height: 12),
            if (_loading && response == null)
              const _LoadingCard()
            else if (_error != null && response == null)
              _ErrorCard(
                message: _error is GeoCruzApiException
                    ? (_error as GeoCruzApiException).message
                    : 'Revisa tu conexión e inténtalo otra vez.',
                onRetry: _catalogReady ? _query : _initialize,
              )
            else if (response != null) ...[
              _mapCard(context, response),
              if (_selected != null) ...[
                const SizedBox(height: 10),
                _selectedCard(context, _selected!, response),
              ],
              const SizedBox(height: 10),
              _pointsList(context, response),
              if (response.lastPage > 1) _pagination(context, response),
              if (_error != null)
                _ErrorCard(
                  message: _error is GeoCruzApiException
                      ? (_error as GeoCruzApiException).message
                      : 'No se pudo actualizar la consulta.',
                  onRetry: _query,
                ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _filtersCard(BuildContext context) {
    final points = _response?.total;
    final summary = points == null
        ? '${_metrics[_filters.metric] ?? _filters.metric} · ${_periodLabel()}'
        : '$points pts · ${_metrics[_filters.metric] ?? _filters.metric}';
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
      child: ExpansionTile(
        key: const PageStorageKey<String>('monitoring-filters'),
        initiallyExpanded: false,
        shape: const Border(),
        collapsedShape: const Border(),
        tilePadding: const EdgeInsets.symmetric(horizontal: 14),
        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
        title: Text(
          _countryName(_filters.country),
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
        subtitle: Text(summary, maxLines: 1, overflow: TextOverflow.ellipsis),
        trailing: const Icon(Icons.tune_rounded),
        children: [
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String?>(
                      key: const Key('monitoring-country'),
                      initialValue: _filters.country,
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'País',
                        border: OutlineInputBorder(),
                        contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                      ),
                    items: [
                      const DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Sudamérica', overflow: TextOverflow.ellipsis),
                      ),
                      ..._countries.map(
                        (item) => DropdownMenuItem<String?>(
                          value: item.code,
                          child: Text(
                            item.name,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                    onChanged: _loading
                        ? null
                        : (value) => setState(() {
                            _filters = _filters.copyWith(
                              country: value,
                              clearCountry: value == null,
                              clearRegion: true,
                              page: 1,
                            );
                          }),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    key: ValueKey('monitoring-region-${_filters.country ?? 'sur'}'),
                    initialValue: _filters.region,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Región',
                      border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    items: [
                      const DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Todas', overflow: TextOverflow.ellipsis),
                      ),
                      ..._regions
                          .where(
                            (region) =>
                                _filters.country == null ||
                                region.country == _filters.country,
                          )
                          .map(
                            (region) => DropdownMenuItem<String?>(
                              value: region.code,
                              child: Text(
                                region.name,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                    ],
                    onChanged: _loading || _filters.country == null
                        ? null
                        : (value) => setState(() {
                            _filters = _filters.copyWith(
                              region: value,
                              clearRegion: value == null,
                              page: 1,
                            );
                          }),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _filters.metric,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Variable',
                      border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    items: _metrics.entries
                        .map(
                          (entry) => DropdownMenuItem(
                            value: entry.key,
                            child: Text(entry.value, overflow: TextOverflow.ellipsis),
                          ),
                        )
                        .toList(),
                    onChanged: _loading
                        ? null
                        : (value) => setState(() {
                            if (value != null) {
                              _filters = _filters.copyWith(
                                metric: value,
                                page: 1,
                              );
                            }
                          }),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    key: const Key('monitoring-origin'),
                    initialValue: _filters.origin,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Origen',
                      border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    items: const [
                      DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Registros y estimaciones', overflow: TextOverflow.ellipsis),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'observado',
                        child: Text('Observado', overflow: TextOverflow.ellipsis),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'estimado',
                        child: Text('Estimado', overflow: TextOverflow.ellipsis),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'simulado',
                        child: Text('Solo simulados', overflow: TextOverflow.ellipsis),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'todos',
                        child: Text('Todos, incluidos simulados', overflow: TextOverflow.ellipsis),
                      ),
                    ],
                    onChanged: _loading
                        ? null
                        : (value) => setState(() {
                            _filters = _filters.copyWith(
                              origin: value,
                              clearOrigin: value == null,
                              page: 1,
                            );
                          }),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: _loading ? null : _choosePeriod,
              icon: const Icon(Icons.calendar_month_outlined),
              label: Text(
                _periodLabel(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: _loading ? null : () => _query(page: 1),
              icon: _loading
                  ? const SizedBox.square(
                      dimension: 17,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.search),
              label: const Text('Consultar mapa'),
            ),
            const SizedBox(height: 5),
          ],
        ),
      ),
    ],
      ),
    );
  }

  Widget _mapCard(BuildContext context, MonitoringResponse response) {
    final mapPoints = <LatLng>[];
    for (final point in response.points) {
      if (point.readings.isEmpty) {
        mapPoints.add(LatLng(point.latitude, point.longitude));
      } else {
        for (final reading in point.readings) {
          mapPoints.add(
            LatLng(
              reading.latitude ?? point.latitude,
              reading.longitude ?? point.longitude,
            ),
          );
        }
      }
    }
    final bounds =
        response.boundary?.bounds ??
        (mapPoints.length > 1 ? LatLngBounds.fromPoints(mapPoints) : null);
    final center = mapPoints.isNotEmpty
        ? mapPoints.first
        : const LatLng(-16.5, -63.5);
    final markers = <Marker>[];
    for (final point in response.points) {
      if (point.readings.isEmpty) {
        markers.add(_pointMarker(point, point.latitude, point.longitude));
        continue;
      }
      for (final reading in point.readings) {
        markers.add(
          _pointMarker(
            point,
            reading.latitude ?? point.latitude,
            reading.longitude ?? point.longitude,
            origin: reading.origin,
          ),
        );
      }
    }
    final searchPin = _searchPin;
    if (searchPin != null) {
      markers.add(
        Marker(
          point: searchPin,
          width: 44,
          height: 48,
          child: Tooltip(
            message: _searchPinLabel ?? 'Lugar buscado',
            child: Icon(
              Icons.place_rounded,
              size: 40,
              color: Theme.of(context).colorScheme.tertiary,
              shadows: const [Shadow(color: Colors.white, blurRadius: 4)],
            ),
          ),
        ),
      );
    }
    final mapHeight = MediaQuery.sizeOf(context).height * 0.46;
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: ColoredBox(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Mapa · ${_countryName(_filters.country)}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  Text(
                    '${response.page}/${response.lastPage}',
                    style: Theme.of(context).textTheme.labelMedium,
                  ),
                ],
              ),
            ),
            SizedBox(
              height: mapHeight.clamp(280.0, 520.0),
              child: Stack(
                children: [
                  FlutterMap(
                    key: ValueKey(
                      'monitoring-${_filters.country}-${_filters.region}-${response.page}-$_mapStyle',
                    ),
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: center,
                      initialZoom: 4,
                      initialCameraFit: bounds == null
                          ? null
                          : CameraFit.bounds(
                              bounds: bounds,
                              padding: const EdgeInsets.all(32),
                            ),
                      minZoom: 2,
                      maxZoom: 17,
                      onMapReady: () => _mapReady = true,
                    ),
                    children: [
                      _mapStyle.toLayer(),
                      if (_capaOverlays.isNotEmpty)
                        OverlayImageLayer(overlayImages: _capaOverlays),
                      if (response.boundary?.polygons.isNotEmpty ?? false)
                        PolygonLayer(polygons: response.boundary!.polygons),
                      MarkerLayer(markers: markers),
                      RichAttributionWidget(
                        alignment: AttributionAlignment.bottomLeft,
                        attributions: [
                          TextSourceAttribution(_mapStyle.attribution),
                        ],
                      ),
                    ],
                  ),
                  Positioned.fill(
                    child: MapChrome(
                      style: _mapStyle,
                      onStyleChanged: (style) =>
                          setState(() => _mapStyle = style),
                      onZoomIn: () => _zoomMap(1),
                      onZoomOut: () => _zoomMap(-1),
                      onFit: _centerMap,
                      onFullscreen: () => openFullscreenMap(
                        context: context,
                        title: 'Mapa de monitoreo',
                        center: center,
                        zoom: 4,
                        markers: markers,
                        polygons: response.boundary?.polygons ?? const [],
                        overlays: _capaOverlays,
                        fitBounds: bounds,
                        initialStyle: _mapStyle,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Marker _pointMarker(
    MonitoringPoint point,
    double latitude,
    double longitude, {
    String? origin,
  }) {
    final selected = point.code == _selected?.code;
    final markerContext = origin == null
        ? '${point.name}, sin lecturas'
        : '${point.name}, ${_originLabel(origin)}, ${point.code}';
    return Marker(
      point: LatLng(latitude, longitude),
      width: 34,
      height: 38,
      child: Semantics(
        button: true,
        label: markerContext,
        child: GestureDetector(
          onTap: () => setState(() => _selected = point),
          child: Icon(
            Icons.location_on,
            size: selected ? 36 : 30,
            color: origin == null
                ? const Color(0xff64748b)
                : _originColor(origin),
            shadows: const [Shadow(color: Colors.white, blurRadius: 4)],
          ),
        ),
      ),
    );
  }

  Widget _selectedCard(
    BuildContext context,
    MonitoringPoint point,
    MonitoringResponse response,
  ) => Card(
    key: const Key('monitoring-selected-point'),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  point.name,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              IconButton(
                tooltip: 'Cerrar detalle',
                onPressed: () => setState(() => _selected = null),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
          Text(
            '${point.type} · ${point.code}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 8),
          if (point.readings.isEmpty)
            const Text(
              'Sin lecturas para este período. Esto no significa que el valor sea cero.',
            ),
          for (final reading in point.readings) ...[
            const Divider(height: 18),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  _originIcon(reading.origin),
                  color: _originColor(reading.origin),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _formatReading(context, response, reading.value),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text(
                        '${_originLabel(reading.origin)} · ${reading.source}',
                      ),
                      if (reading.at != null)
                        Text(
                          DateFormat('dd/MM/yyyy HH:mm').format(reading.at!),
                        ),
                      if (reading.latitude != null && reading.longitude != null)
                        Text(
                          'Ubicación de lectura: ${reading.latitude}, ${reading.longitude}',
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: () => _showHistory(point),
            icon: const Icon(Icons.timeline),
            label: const Text('Ver historial'),
          ),
        ],
      ),
    ),
  );

  Widget _pointsList(BuildContext context, MonitoringResponse response) {
    final points = response.points;
    return Card(
      child: ExpansionTile(
        key: const PageStorageKey<String>('monitoring-points'),
        title: const Text('Puntos'),
        subtitle: Text(
          '${response.total} puntos · página ${response.page} de ${response.lastPage}',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
        children: [
          if (points.isEmpty)
            const Padding(
              padding: EdgeInsets.only(bottom: 14),
              child: Text('No hay puntos para estos filtros.'),
            ),
          for (final point in points.take(12))
            ListTile(
              key: ValueKey('monitoring-point-${point.code}'),
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                point.hasData ? Icons.location_on : Icons.location_on_outlined,
                color: point.hasData
                    ? GeoCruzColors.teal
                    : GeoCruzColors.textSoft,
              ),
              title: Text(point.name),
              subtitle: Text(
                point.hasData
                    ? '${point.readings.length} lectura(s) en el período'
                    : 'Sin lecturas en el período',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => setState(() => _selected = point),
            ),
          if (points.length > 12)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                'El mapa muestra ${points.length} puntos; aquí se listan los primeros 12.',
              ),
            ),
        ],
      ),
    );
  }

  Widget _pagination(BuildContext context, MonitoringResponse response) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            OutlinedButton.icon(
              onPressed: _loading || response.page <= 1
                  ? null
                  : () => _query(page: response.page - 1),
              icon: const Icon(Icons.chevron_left),
              label: const Text('Anterior'),
            ),
            const Spacer(),
            Text('Página ${response.page} de ${response.lastPage}'),
            const Spacer(),
            OutlinedButton.icon(
              onPressed: _loading || response.page >= response.lastPage
                  ? null
                  : () => _query(page: response.page + 1),
              icon: const Icon(Icons.chevron_right),
              label: const Text('Siguiente'),
              iconAlignment: IconAlignment.end,
            ),
          ],
        ),
      );

  Future<void> _showHistory(MonitoringPoint point) async {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _HistorySheet(
        point: point,
        repository: _repository,
        filters: _filters,
        onUnauthorized: widget.onUnauthorized,
      ),
    );
  }

  String _formatReading(
    BuildContext context,
    MonitoringResponse response,
    num value,
  ) {
    final units = UnitsScope.maybeOf(context)?.presentation;
    if (units == null) return '$value ${response.unit}';
    return units.format(response.metric, value, response.unit);
  }
}

class _HistorySheet extends StatefulWidget {
  const _HistorySheet({
    required this.point,
    required this.repository,
    required this.filters,
    required this.onUnauthorized,
  });

  final MonitoringPoint point;
  final MonitoringRepository repository;
  final MonitoringFilters filters;
  final VoidCallback onUnauthorized;

  @override
  State<_HistorySheet> createState() => _HistorySheetState();
}

class _HistorySheetState extends State<_HistorySheet> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.history(widget.point, widget.filters);
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.72,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Historial · ${widget.point.name}',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 4),
          Text('Mismo período y variable de la consulta del mapa.'),
          const SizedBox(height: 12),
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  if (snapshot.error is GeoCruzApiException &&
                      (snapshot.error as GeoCruzApiException).statusCode ==
                          401) {
                    WidgetsBinding.instance.addPostFrameCallback(
                      (_) => widget.onUnauthorized(),
                    );
                  }
                  return Center(
                    child: Text(
                      snapshot.error is GeoCruzApiException
                          ? (snapshot.error as GeoCruzApiException).message
                          : 'No se pudo cargar el historial. Inténtalo otra vez.',
                      textAlign: TextAlign.center,
                    ),
                  );
                }
                final rows = snapshot.data?['datos'];
                if (rows is! List || rows.isEmpty) {
                  return const Center(
                    child: Text('Sin lecturas para este período.'),
                  );
                }
                return ListView.separated(
                  itemCount: rows.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final row = rows[index];
                    if (row is! Map) return const SizedBox.shrink();
                    final date = DateTime.tryParse(
                      '${row['instante'] ?? ''}',
                    )?.toLocal();
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        _formatHistoryRow(
                          context,
                          widget.filters.metric,
                          row,
                        ),
                      ),
                      subtitle: Text(
                        '${row['origen'] ?? ''} · ${row['fuente'] ?? ''}',
                      ),
                      trailing: date == null
                          ? null
                          : Text(DateFormat('dd/MM HH:mm').format(date)),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    ),
  );
}

class _LoadingCard extends StatelessWidget {
  const _LoadingCard();

  @override
  Widget build(BuildContext context) => const Card(
    child: Padding(
      padding: EdgeInsets.all(28),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.errorContainer,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'No se pudo consultar',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(message),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Reintentar'),
          ),
        ],
      ),
    ),
  );
}

String _originLabel(String origin) => switch (origin) {
  'observado' => 'Observado',
  'estimado' => 'Estimado',
  'simulado' => 'Simulado',
  _ => origin,
};

Color _originColor(String origin) => switch (origin) {
  'observado' => GeoCruzColors.green,
  'estimado' => GeoCruzColors.teal,
  'simulado' => GeoCruzColors.clay,
  _ => GeoCruzColors.textSoft,
};

IconData _originIcon(String origin) => switch (origin) {
  'observado' => Icons.sensors,
  'estimado' => Icons.auto_graph,
  'simulado' => Icons.science_outlined,
  _ => Icons.circle_outlined,
};

String _formatHistoryRow(
  BuildContext context,
  String metric,
  Map row,
) {
  final raw = row['valor'];
  final base = '${row['unidad'] ?? ''}';
  if (raw is! num) return 'Sin dato $base'.trim();
  final units = UnitsScope.maybeOf(context)?.presentation;
  if (units == null) return '$raw $base'.trim();
  return units.format(metric, raw, base);
}
