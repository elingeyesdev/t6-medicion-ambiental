import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/maps/map_base_styles.dart';
import '../../../core/maps/map_chrome.dart';
import '../../../core/maps/map_completo_actions.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../view_model/hotspots_view_model.dart';
import '../model/hotspot_models.dart';
import '../model/hotspot_repository.dart';
import 'hotspot_detail_screen.dart';
import 'hotspot_filters_sheet.dart';

class HotspotsScreen extends StatefulWidget {
  const HotspotsScreen({
    required this.apiClient,
    this.showAppBar = true,
    super.key,
  });

  final GeoCruzApiClient apiClient;
  final bool showAppBar;

  @override
  State<HotspotsScreen> createState() => _HotspotsScreenState();
}

class _HotspotsScreenState extends State<HotspotsScreen>
    with AutomaticKeepAliveClientMixin {
  late final HotspotsViewModel _controller;
  final MapController _mapController = MapController();
  HotspotRecord? _selectedRecord;
  bool _mapReady = false;
  MapBaseStyle _mapStyle = MapBaseStyle.relieve;
  LatLng? _searchPin;
  String? _searchPinLabel;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _controller = HotspotsViewModel(HotspotRepository(widget.apiClient));
    _controller.initialize();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  LatLng _focusPoint() {
    final selected = _selectedRecord?.coordinates;
    if (selected != null) return selected;
    if (_searchPin != null) return _searchPin!;
    if (_mapReady) return _mapController.camera.center;
    return const LatLng(-17.78, -63.18);
  }

  Future<void> _openPlaceSearch() async {
    final result = await openPlaceSearch(
      context: context,
      api: widget.apiClient,
      near: _focusPoint(),
    );
    if (!mounted || result == null) return;
    final point = LatLng(result.latitude, result.longitude);
    setState(() {
      _searchPin = point;
      _searchPinLabel = result.name;
    });
    if (_mapReady) _mapController.move(point, 10);
  }

  Future<void> _openWeather() => openWeatherSheet(
    context: context,
    api: widget.apiClient,
    point: _focusPoint(),
    title: _weatherTitle(),
  );

  String _weatherTitle() {
    final pin = _searchPinLabel?.trim();
    if (pin != null && pin.isNotEmpty) return pin;
    final record = _selectedRecord;
    if (record == null) return 'Tiempo en este foco';
    final municipio = _controller
        .territoryById(record.territory['municipio']?.toString())
        ?.name;
    if (municipio != null && municipio.isNotEmpty) return municipio;
    final provincia = _controller
        .territoryById(record.territory['provincia']?.toString())
        ?.name;
    if (provincia != null && provincia.isNotEmpty) return provincia;
    return 'Tiempo en este foco';
  }

  Future<void> _openFilters() async {
    final selected = await showModalBottomSheet<HotspotFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: false,
      builder: (_) => HotspotFiltersSheet(
        initial: _controller.filters,
        provinces: _controller.provinces,
        municipalities: _controller.municipalitiesFor(null),
        nameFor: _controller.territoryName,
      ),
    );
    if (!mounted || selected == null) return;
    try {
      setState(() => _selectedRecord = null);
      await _controller.apply(selected);
    } on FormatException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  void _selectRecord(HotspotRecord record) {
    setState(() => _selectedRecord = record);
    final point = record.coordinates;
    if (_mapReady && point != null) {
      _mapController.move(point, 8.5);
    }
    final response = _controller.response;
    if (response == null) return;
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) {
        final place = _controller.territoryName(
          record.territory['municipio']?.toString(),
        );
        final observed = record.observedAt == null
            ? 'Fecha no informada'
            : '${BoliviaTime.dateTime(DateTime.tryParse(record.observedAt!) ?? response.queryFetchedAt)} · Bolivia';
        final frp = record.radiantPower == null
            ? 'FRP sin dato'
            : 'FRP ${_formatNumber(record.radiantPower)} ${record.radiantPowerUnit ?? 'MW'}';
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                place,
                style: Theme.of(sheetContext).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(observed),
              Text('Confianza ${record.confidence ?? 'no informada'} · $frp'),
              const SizedBox(height: 4),
              Text(
                'Detección satelital · NASA FIRMS',
                style: Theme.of(sheetContext).textTheme.bodySmall,
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () {
                    Navigator.of(sheetContext).pop();
                    _openRecord(record, response);
                  },
                  icon: const Icon(Icons.article_outlined),
                  label: const Text('Ver todos los datos'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _zoom(double amount) {
    if (!_mapReady) return;
    final camera = _mapController.camera;
    _mapController.move(camera.center, (camera.zoom + amount).clamp(2, 18));
  }

  void _fitPage(List<HotspotRecord> records) {
    if (!_mapReady) return;
    final points = records
        .map((record) => record.coordinates)
        .whereType<LatLng>()
        .toList(growable: false);
    if (points.isEmpty) return;
    if (points.length == 1) {
      _mapController.move(points.single, 8);
      return;
    }
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds.fromPoints(points),
        padding: const EdgeInsets.all(48),
        maxZoom: 10,
      ),
    );
  }

  void _openRecord(HotspotRecord record, HotspotResponse response) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => HotspotDetailScreen(
          record: record,
          response: response,
          territoryName: (id) => _controller.territoryName(id),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final response = _controller.response;
        final body =
            (_controller.loadingCatalog && _controller.territories.isEmpty) ||
                (_controller.loading && response == null)
            ? const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: 12),
                    Text('Consultando focos…'),
                  ],
                ),
              )
            : _controller.catalogError != null &&
                  _controller.territories.isEmpty
            ? _catalogFailure(context)
            : _controller.queryError != null && response == null
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.warning_amber_rounded,
                        size: 48,
                        color: Colors.orange,
                      ),
                      const SizedBox(height: 12),
                      Text(
                        _controller.queryError!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 15),
                      ),
                      const SizedBox(height: 16),
                      FilledButton.icon(
                        onPressed: () => _controller.apply(_controller.filters),
                        icon: const Icon(Icons.refresh),
                        label: const Text('Reintentar'),
                      ),
                    ],
                  ),
                ),
              )
            : response == null
            ? const SizedBox.shrink()
            : Stack(
                fit: StackFit.expand,
                children: [
                  _detectionMap(context, response),
                  if (!widget.showAppBar)
                    Positioned(
                      top: 8,
                      left: 12,
                      right: 58,
                      child: _buildTopControls(context, response),
                    ),
                  DraggableScrollableSheet(
                    initialChildSize: 0.30,
                    minChildSize: 0.22,
                    maxChildSize: 0.82,
                    snap: true,
                    snapSizes: const [0.30, 0.82],
                    builder: (context, scrollController) => Material(
                      color: Theme.of(context).colorScheme.surface,
                      elevation: 8,
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(24),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: ListView(
                        controller: scrollController,
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                        children: [
                          Center(
                            child: Container(
                              width: 34,
                              height: 4,
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: Theme.of(
                                  context,
                                ).colorScheme.outlineVariant,
                                borderRadius: BorderRadius.circular(4),
                              ),
                            ),
                          ),
                          _queryStatus(context, response),
                          if (response.total > 0) ...[
                            _buildHotspotsSummaryCard(context, response),
                            _pageControls(context, response),
                          ],
                        ],
                      ),
                    ),
                  ),
                ],
              );

        if (!widget.showAppBar) {
          return body;
        }

        return Scaffold(
          appBar: AppBar(
            title: const Text('Focos de calor'),
            actions: [
              ...mapCompletoActions(
                onSearch: _openPlaceSearch,
                onWeather: _openWeather,
              ),
              IconButton(
                tooltip: 'Filtros',
                onPressed: _controller.loadingCatalog ? null : _openFilters,
                icon: const Icon(Icons.tune_rounded),
              ),
            ],
          ),
          body: body,
        );
      },
    );
  }

  Widget _catalogFailure(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 38),
          const SizedBox(height: 12),
          Text(_controller.catalogError!, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: () => _controller.loadCatalog(runInitialQuery: true),
            icon: const Icon(Icons.refresh),
            label: const Text('Reintentar catálogo'),
          ),
        ],
      ),
    ),
  );

  Widget _queryStatus(BuildContext context, HotspotResponse? response) {
    if (_controller.loading) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
              const SizedBox(width: 12),
              Text(
                response == null ? 'Buscando detecciones…' : 'Actualizando…',
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ],
          ),
        ),
      );
    }
    if (_controller.queryError != null) {
      return _StatusCard(
        icon: _controller.queryExpired
            ? Icons.hourglass_disabled_rounded
            : Icons.error_outline_rounded,
        title: _controller.queryExpired
            ? 'El conjunto de resultados expiró'
            : 'No se pudo completar la consulta',
        message: _controller.queryError!,
        tone: _StatusTone.error,
        action: _controller.queryExpired
            ? () => _controller.restartExpiredQuery()
            : () => _controller.retry(),
        actionLabel: _controller.queryExpired
            ? 'Reiniciar consulta'
            : 'Reintentar consulta',
      );
    }
    if (response == null) {
      return const _StatusCard(
        icon: Icons.info_outline_rounded,
        title: 'Define una consulta',
        message: 'Elige el territorio y el período.',
        tone: _StatusTone.neutral,
      );
    }

    final coverage = response.coverageState;
    // La tarjeta que explicaba cobertura confundía la vista cuando el API
    // no tenía un período publicado. Se conserva el estado en el modelo/API.
    if (coverage == 'sin_cobertura') return const SizedBox.shrink();
    // Cobertura parcial: se omite el aviso en pantalla (sigue en la API).
    if (coverage == 'parcial') {
      if (response.total > 0) return const SizedBox.shrink();
      return const _StatusCard(
        icon: Icons.info_outline_rounded,
        title: 'Sin detecciones para estos filtros',
        message:
            'La consulta respondió sin detecciones; revisa el período o el territorio.',
        tone: _StatusTone.neutral,
      );
    }
    if (response.total > 0) return const SizedBox.shrink();
    const title = 'Sin detecciones para estos filtros';
    const message = 'La consulta no devolvió focos para el período elegido.';
    const tone = _StatusTone.neutral;
    return _StatusCard(
      icon: tone == _StatusTone.warning
          ? Icons.warning_amber_rounded
          : Icons.check_circle_outline_rounded,
      title: title,
      message: message,
      tone: tone,
    );
  }

  Widget _detectionMap(BuildContext context, HotspotResponse response) {
    final records = response.records
        .where((record) => record.coordinates != null)
        .toList(growable: false);
    final center = records.isEmpty
        ? const LatLng(-17.78, -63.18)
        : records.first.coordinates!;
    final markers = records.map((record) {
      final selected = record.identifier == _selectedRecord?.identifier;
      return Marker(
        point: record.coordinates!,
        width: 44,
        height: 48,
        child: Semantics(
          button: true,
          label: 'Detección ${record.identifier}, tocar para ver datos',
          child: GestureDetector(
            onTap: () => _selectRecord(record),
            child: Icon(
              Icons.local_fire_department_rounded,
              size: selected ? 42 : 34,
              color: selected ? GeoCruzColors.clay : GeoCruzColors.green,
              shadows: const [Shadow(color: Colors.white, blurRadius: 5)],
            ),
          ),
        ),
      );
    }).toList();
    if (_searchPin != null) {
      markers.add(
        Marker(
          point: _searchPin!,
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

    final fitBounds = records.length > 1
        ? LatLngBounds.fromPoints(records.map((r) => r.coordinates!).toList())
        : null;
    return Stack(
      fit: StackFit.expand,
      children: [
        FlutterMap(
          key: ValueKey(
            'hotspots-${response.setVersion}-${response.page}-$_mapStyle',
          ),
          mapController: _mapController,
          options: MapOptions(
            initialCenter: center,
            initialZoom: 7,
            initialCameraFit: fitBounds == null
                ? null
                : CameraFit.bounds(
                    bounds: fitBounds,
                    padding: const EdgeInsets.all(44),
                    maxZoom: 10,
                  ),
            minZoom: 2,
            maxZoom: 18,
            onMapReady: () {
              _mapReady = true;
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted) _fitPage(records);
              });
            },
          ),
          children: [
            _mapStyle.toLayer(),
            MarkerLayer(markers: markers),
            RichAttributionWidget(
              alignment: AttributionAlignment.bottomLeft,
              attributions: [TextSourceAttribution(_mapStyle.attribution)],
            ),
          ],
        ),
        Positioned.fill(
          child: MapChrome(
            style: _mapStyle,
            onStyleChanged: (style) => setState(() => _mapStyle = style),
            onZoomIn: () => _zoom(1),
            onZoomOut: () => _zoom(-1),
            onFit: () => _fitPage(records),
            onFullscreen: () => openFullscreenMap(
              context: context,
              title: 'Focos de calor',
              center: center,
              zoom: 6,
              markers: markers,
              fitBounds: fitBounds,
              initialStyle: _mapStyle,
            ),
          ),
        ),
      ],
    );
  }

  Widget _pageControls(BuildContext context, HotspotResponse response) {
    final pageCount = (response.total / response.pageSize).ceil();
    if (pageCount <= 1) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 4, 8, 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              'Toca un foco · pág. ${response.page}/$pageCount',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
          IconButton(
            tooltip: 'Anterior',
            visualDensity: VisualDensity.compact,
            onPressed: !_controller.loading && response.page > 1
                ? () => _changePage(_controller.previousPage)
                : null,
            icon: const Icon(Icons.chevron_left),
          ),
          IconButton(
            tooltip: 'Siguiente',
            visualDensity: VisualDensity.compact,
            onPressed: response.nextPage != null && !_controller.loading
                ? () => _changePage(_controller.nextPage)
                : null,
            icon: const Icon(Icons.chevron_right),
          ),
        ],
      ),
    );
  }

  Future<void> _changePage(Future<void> Function() change) async {
    setState(() => _selectedRecord = null);
    await change();
  }

  Widget _buildTopControls(BuildContext context, HotspotResponse response) {
    final period = BoliviaTime.interval(
      response.periodFrom,
      response.periodUntil,
    ).replaceFirst(' (fin excluido)', '');
    final oldResults = _controller.loading || _controller.queryError != null;
    final scheme = Theme.of(context).colorScheme;
    return Material(
      elevation: 3,
      color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.96),
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 4, 4, 4),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _controller.territoryName(response.territoryId),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    '$period${oldResults ? ' · datos anteriores' : ''}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            IconButton.filledTonal(
              onPressed: _openPlaceSearch,
              icon: const Icon(Icons.search_rounded),
              tooltip: 'Buscar lugar',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _openWeather,
              icon: const Icon(Icons.wb_cloudy_outlined),
              tooltip: 'Tiempo',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _controller.loadingCatalog ? null : _openFilters,
              icon: const Icon(Icons.tune_rounded),
              tooltip: 'Filtros',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _controller.loading
                  ? null
                  : () => _controller.apply(_controller.filters),
              icon: const Icon(Icons.refresh_rounded),
              tooltip: 'Recargar',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHotspotsSummaryCard(
    BuildContext context,
    HotspotResponse response,
  ) {
    final highIndicator = response.indicator('focosConfianzaAlta');
    final maxFrp = response.indicator('potenciaMaxima');

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ExpansionTile(
        key: const PageStorageKey<String>('hotspots-summary'),
        leading: const Icon(Icons.info_outline_rounded),
        title: const Text('Datos de la consulta'),
        subtitle: Text('${_formatNumber(response.total)} detecciones'),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
        children: [
          Row(
            children: [
              Expanded(
                child: _metricItem(
                  response.total.toString(),
                  'Detecciones',
                  Theme.of(context).colorScheme.primary,
                ),
              ),
              Expanded(
                child: _metricItem(
                  (highIndicator?['valor'] ?? 0).toString(),
                  'Alta conf.',
                  GeoCruzColors.green,
                ),
              ),
              Expanded(
                child: _metricItem(
                  maxFrp?['valor'] != null
                      ? (maxFrp!['valor'] as num).toStringAsFixed(1)
                      : '—',
                  'FRP máx',
                  const Color(0xFFBC673B),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _metricItem(String val, String label, Color color) {
    return Column(
      children: [
        Text(
          val,
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 11, color: Colors.black54),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }

  String _formatNumber(Object? value) {
    if (value == null) return 'Sin dato';
    if (value is num) return NumberFormat('###,##0.##', 'es_BO').format(value);
    return '$value';
  }
}

enum _StatusTone { neutral, success, warning, error }

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.tone,
    this.action,
    this.actionLabel,
  });

  final IconData icon;
  final String title;
  final String message;
  final _StatusTone tone;
  final VoidCallback? action;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    final color = switch (tone) {
      _StatusTone.success => GeoCruzColors.green,
      _StatusTone.warning => GeoCruzColors.amber,
      _StatusTone.error => Theme.of(context).colorScheme.error,
      _StatusTone.neutral => GeoCruzColors.textSoft,
    };
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, color: color),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        message,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (action != null) ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: action,
                  icon: const Icon(Icons.refresh_rounded),
                  label: Text(actionLabel ?? 'Reintentar'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
