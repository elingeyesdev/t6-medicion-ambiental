import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/formatters/bolivia_time.dart';
import '../../../core/formatters/consultation_period.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../../core/widgets/geosur_brand.dart';
import '../model/monitoring_models.dart';
import '../model/monitoring_repository.dart';

const _metrics = <String, String>{
  'temperatura': 'Temperatura',
  'humedad': 'Humedad',
  'aqi': 'AQI',
  'ica': 'ICA',
  'ruido': 'Ruido',
};

class MonitoringScreen extends StatefulWidget {
  const MonitoringScreen({
    required this.api,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final VoidCallback onUnauthorized;

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

  @override
  void initState() {
    super.initState();
    _repository = MonitoringRepository(widget.api);
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
              _resultSummary(context, response),
              const SizedBox(height: 10),
              _mapCard(context, response),
              const SizedBox(height: 10),
              if (_selected != null)
                _selectedCard(context, _selected!, response),
              _pointsList(context, response),
              _pagination(context, response),
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

  Widget _filtersCard(BuildContext context) => Card(
    child: ExpansionTile(
      key: const PageStorageKey<String>('monitoring-filters'),
      initiallyExpanded: false,
      tilePadding: const EdgeInsets.symmetric(horizontal: 14),
      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
      leading: const Icon(Icons.tune_rounded),
      title: const Text('Filtros'),
      subtitle: Text(
        '${_countryName(_filters.country)} · ${_metrics[_filters.metric] ?? _filters.metric} · ${_periodLabel()}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    key: const Key('monitoring-country'),
                    initialValue: _filters.country,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'País'),
                    items: [
                      const DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Sudamérica'),
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
                    decoration: const InputDecoration(labelText: 'Región'),
                    items: [
                      const DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Todas'),
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
                    decoration: const InputDecoration(labelText: 'Variable'),
                    items: _metrics.entries
                        .map(
                          (entry) => DropdownMenuItem(
                            value: entry.key,
                            child: Text(entry.value),
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
                    decoration: const InputDecoration(labelText: 'Origen'),
                    items: const [
                      DropdownMenuItem<String?>(
                        value: null,
                        child: Text('Registros y estimaciones'),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'observado',
                        child: Text('Observado'),
                      ),
                      DropdownMenuItem<String?>(
                        value: 'estimado',
                        child: Text('Estimado'),
                      ),
                      DropdownMenuItem<String?>(value: 'simulado', child: Text('Solo simulados')),
                      DropdownMenuItem<String?>(value: 'todos', child: Text('Todos, incluidos simulados')),
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
            Text(
              'Período máximo: 7 días.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ],
    ),
  );

  Widget _resultSummary(
    BuildContext context,
    MonitoringResponse response,
  ) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Icon(Icons.public, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _countryName(_filters.country),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                Text(
                  '${response.total} puntos · ${_metrics[response.metric] ?? response.metric} · ${response.unit}',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          if (_loading)
            const SizedBox.square(
              dimension: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
        ],
      ),
    ),
  );

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
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'Mapa de monitoreo',
                    style: Theme.of(context).textTheme.titleLarge,
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
            height: 280,
            child: Stack(
              children: [
                FlutterMap(
                  key: ValueKey(
                    'monitoring-${_filters.country}-${_filters.region}-${response.page}',
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
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'bo.edu.univalle.geocruz_movil',
                      maxNativeZoom: 19,
                    ),
                    if (response.boundary?.polygons.isNotEmpty ?? false)
                      PolygonLayer(polygons: response.boundary!.polygons),
                    MarkerLayer(markers: markers),
                    const RichAttributionWidget(
                      alignment: AttributionAlignment.bottomRight,
                      attributions: [
                        TextSourceAttribution('© OpenStreetMap contributors'),
                      ],
                    ),
                  ],
                ),
                Positioned(
                  left: 10,
                  top: 10,
                  child: Material(
                    color: Colors.white,
                    elevation: 2,
                    borderRadius: BorderRadius.circular(10),
                    clipBehavior: Clip.antiAlias,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          tooltip: 'Acercar',
                          visualDensity: VisualDensity.compact,
                          onPressed: _mapReady ? () => _zoomMap(1) : null,
                          icon: const Icon(Icons.add),
                        ),
                        const SizedBox(width: 28, child: Divider(height: 1)),
                        IconButton(
                          tooltip: 'Alejar',
                          visualDensity: VisualDensity.compact,
                          onPressed: _mapReady ? () => _zoomMap(-1) : null,
                          icon: const Icon(Icons.remove),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (response.notice.isNotEmpty)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                response.notice,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
        ],
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
                        '${reading.value} ${response.unit}',
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
                        '${row['valor'] ?? 'Sin dato'} ${row['unidad'] ?? ''}',
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
