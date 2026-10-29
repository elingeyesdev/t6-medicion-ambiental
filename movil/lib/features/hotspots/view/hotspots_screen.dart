import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/formatters/bolivia_time.dart';
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
      final body = (_controller.loadingCatalog && _controller.territories.isEmpty) ||
              (_controller.loading && response == null)
          ? const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 12),
                  Text('Cargando focos de calor...'),
                ],
              ),
            )
          : _controller.catalogError != null && _controller.territories.isEmpty
              ? _catalogFailure(context)
              : _controller.queryError != null && response == null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.warning_amber_rounded,
                                size: 48, color: Colors.orange),
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
                  : ListView(
                      key: const PageStorageKey<String>('hotspots-scroll'),
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 30),
              children: [
                if (!widget.showAppBar && response != null)
                  _buildTopControls(context, response),
                if (response != null)
                  _buildHotspotsSummaryCard(context, response),
                if (widget.showAppBar) ...[
                  const _CoverageScopeCard(),
                  const SizedBox(height: 8),
                  if (response != null) _appliedContext(context, response),
                  const SizedBox(height: 8),
                  _searchButton(context),
                  const SizedBox(height: 8),
                  _queryStatus(context, response),
                ],
                if (response != null) ...[
                  const SizedBox(height: 8),
                  _detectionMap(context, response),
                  const SizedBox(height: 8),
                  _summary(context, response),
                ],
              ],
            );

      if (!widget.showAppBar) {
        return body;
      }

      return Scaffold(
        appBar: AppBar(
          title: const Text('Focos de calor'),
          actions: [
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

  Widget _catalogLoading(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 14),
          Text(
            'Cargando cobertura disponible…',
            style: Theme.of(context).textTheme.bodyLarge,
          ),
        ],
      ),
    ),
  );

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
    if (response.total > 0 && coverage != 'sin_cobertura') {
      return const SizedBox.shrink();
    }
    final title = switch (coverage) {
      'sin_cobertura' => 'Sin cobertura comprobada',
      'parcial' => 'Resultados con cobertura parcial',
      _ =>
        response.total == 0
            ? 'Sin detecciones para estos filtros'
            : 'Consulta lista',
    };
    final message = switch (coverage) {
      'sin_cobertura' =>
        'La fuente no cubre el período solicitado. No se puede inferir ausencia de detecciones. '
            '${response.coverage['fundamento'] ?? ''}',
      'parcial' =>
        'La cobertura es parcial; los resultados no describen todo el período. '
            '${response.coverage['fundamento'] ?? ''}',
      _ =>
        response.total == 0
            ? 'La consulta respondió sin detecciones; revisa la cobertura publicada.'
            : response.coverage['fundamento']?.toString() ?? '',
    };
    final tone = coverage == 'sin_cobertura' || coverage == 'parcial'
        ? _StatusTone.warning
        : _StatusTone.success;
    return _StatusCard(
      icon: tone == _StatusTone.warning
          ? Icons.warning_amber_rounded
          : Icons.check_circle_outline_rounded,
      title: title,
      message: message,
      tone: tone,
    );
  }

  Widget _searchButton(BuildContext context) => SizedBox(
    width: double.infinity,
    child: FilledButton.icon(
      onPressed: _controller.loadingCatalog ? null : _openFilters,
      icon: const Icon(Icons.search_rounded),
      label: const Text('Buscar focos'),
    ),
  );

  Widget _detectionMap(BuildContext context, HotspotResponse response) {
    final records = response.records
        .where((record) => record.coordinates != null)
        .toList(growable: false);
    final center = records.isEmpty
        ? const LatLng(-17.5, -63.2)
        : records.first.coordinates!;
    final pageCount = (response.total / response.pageSize).ceil();
    final markers = records
        .map((record) {
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
        })
        .toList(growable: false);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
            child: Row(
              children: [
                const Icon(Icons.local_fire_department_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Mapa de focos',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                Text('${response.total}'),
              ],
            ),
          ),
          SizedBox(
            height: 330,
            child: Stack(
              children: [
                FlutterMap(
                  key: ValueKey(
                    'hotspots-${response.setVersion}-${response.page}',
                  ),
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: center,
                    initialZoom: records.length == 1 ? 8 : 6,
                    initialCameraFit: records.length > 1
                        ? CameraFit.bounds(
                            bounds: LatLngBounds.fromPoints(
                              records.map((r) => r.coordinates!).toList(),
                            ),
                            padding: const EdgeInsets.all(44),
                            maxZoom: 10,
                          )
                        : null,
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
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'bo.edu.univalle.geocruz_movil',
                      maxNativeZoom: 19,
                    ),
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
                  left: 8,
                  top: 8,
                  child: _MapControls(
                    onZoomIn: () => _zoom(1),
                    onZoomOut: () => _zoom(-1),
                    onFit: () => _fitPage(records),
                  ),
                ),
                if (records.isEmpty)
                  Positioned.fill(
                    child: IgnorePointer(
                      child: Center(
                        child: Card(
                          color: Colors.white.withValues(alpha: 0.94),
                          child: const Padding(
                            padding: EdgeInsets.all(12),
                            child: Text(
                              'No hay coordenadas para mostrar en esta página.',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
            child: Row(
              children: [
                Icon(
                  Icons.touch_app_outlined,
                  size: 18,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    records.isEmpty
                        ? 'Sin puntos en esta página'
                        : 'Toca un punto para ver sus datos',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                IconButton(
                  tooltip: 'Página anterior',
                  visualDensity: VisualDensity.compact,
                  onPressed: !_controller.loading && response.page > 1
                      ? () => _changePage(_controller.previousPage)
                      : null,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  '${response.page}/$pageCount',
                  style: Theme.of(context).textTheme.labelMedium,
                ),
                IconButton(
                  tooltip: 'Página siguiente',
                  visualDensity: VisualDensity.compact,
                  onPressed: response.nextPage != null && !_controller.loading
                      ? () => _changePage(_controller.nextPage)
                      : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
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
    );
    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 8, 0, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _controller.territoryName(response.territoryId),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              FilledButton.tonalIcon(
                onPressed: _controller.loadingCatalog ? null : _openFilters,
                icon: const Icon(Icons.tune, size: 18),
                label: const Text('Filtros'),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                onPressed: _controller.loading ? null : () => _controller.apply(_controller.filters),
                icon: const Icon(Icons.refresh, size: 18),
                tooltip: 'Recargar',
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            period,
            style: const TextStyle(fontSize: 12, color: Colors.black54),
          ),
        ],
      ),
    );
  }

  Widget _buildHotspotsSummaryCard(BuildContext context, HotspotResponse response) {
    final highIndicator = response.indicator('focosConfianzaAlta');
    final maxFrp = response.indicator('potenciaMaxima');

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8),
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.local_fire_department_outlined,
                  color: GeoCruzColors.green,
                  size: 20,
                ),
                const SizedBox(width: 8),
                Text(
                  'Resumen de focos de calor',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _metricItem(
                  response.total.toString(),
                  'Detecciones',
                  Colors.blueGrey.shade800,
                ),
                _metricItem(
                  (highIndicator?['valor'] ?? 0).toString(),
                  'Confianza alta',
                  GeoCruzColors.green,
                ),
                _metricItem(
                  maxFrp?['valor'] != null
                      ? (maxFrp!['valor'] as num).toStringAsFixed(1)
                      : '—',
                  'FRP máx (MW)',
                  const Color(0xFFBC673B),
                ),
                _metricItem(
                  'VIIRS',
                  'Satélite',
                  Colors.grey.shade700,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _metricItem(String val, String label, Color color) {
    return Expanded(
      child: Column(
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
      ),
    );
  }

  Widget _appliedContext(BuildContext context, HotspotResponse response) {
    final oldResults = _controller.loading || _controller.queryError != null;
    final obtainAt = response.fetchedAt;
    final ageMinutes = response.ageSeconds == null
        ? null
        : response.ageSeconds! ~/ 60;
    final period = BoliviaTime.interval(
      response.periodFrom,
      response.periodUntil,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            const Icon(Icons.place_outlined, color: GeoCruzColors.green),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _controller.territoryName(response.territoryId),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    '$period${oldResults ? ' · datos anteriores' : ''}',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (response.fetchType == 'cache' || obtainAt != null)
                    Text(
                      response.fetchType == 'cache'
                          ? 'Guardado · ${ageMinutes ?? 0} min'
                          : 'Actualizado · ${BoliviaTime.dateTime(obtainAt!)}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summary(BuildContext context, HotspotResponse response) {
    final totalIndicator = response.indicator('totalFocos');
    final highIndicator = response.indicator('focosConfianzaAlta');
    final maxFrp = response.indicator('potenciaMaxima');
    return Card(
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
                child: _MetricCard(
                  label: 'Detecciones',
                  value: _formatNumber(
                    totalIndicator?['valor'] ?? response.total,
                  ),
                  unit: totalIndicator?['unidad']?.toString() ?? 'registros',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MetricCard(
                  label: 'Confianza alta',
                  value: _formatNumber(highIndicator?['valor']),
                  unit: highIndicator?['unidad']?.toString() ?? 'registros',
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _MetricCard(
                  label: 'FRP máxima',
                  value: _formatNumber(maxFrp?['valor']),
                  unit: maxFrp?['unidad']?.toString() ?? 'MW',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MetricCard(
                  label: 'Con faltantes',
                  value: _formatNumber(
                    response.summary['registrosConFaltantes'],
                  ),
                  unit: 'registros',
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            'Resumen de ${_formatNumber(response.summary['registrosValidos'])} registros válidos · FRP disponible en ${_formatNumber(maxFrp?['registrosValidos'])} registros. La potencia radiativa en MW no es superficie quemada.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 8),
          _CoverageCard(response: response),
          const SizedBox(height: 10),
          Text(
            'NASA FIRMS · ${response.sources.map((source) => source is Map ? source['producto'] : null).whereType<String>().join(', ')} · Observación.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 4),
          Text(
            'Una detección térmica no confirma un incendio.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  String _formatNumber(Object? value) {
    if (value == null) return 'Sin dato';
    if (value is num) return NumberFormat('###,##0.##', 'es_BO').format(value);
    return '$value';
  }
}

/// Makes the difference between GeoSur's South America map and the currently
/// supported fire-query territory visible before a user opens the filters.
class _CoverageScopeCard extends StatelessWidget {
  const _CoverageScopeCard();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      color: colors.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.public_rounded, color: colors.onSecondaryContainer),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Cobertura disponible',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: colors.onSecondaryContainer,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Hoy la API de focos solo consulta Santa Cruz, Bolivia. '
                    'El mapa general muestra referencias de otros países; no son detecciones de fuego ni lecturas actuales.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: colors.onSecondaryContainer,
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
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.unit,
  });

  final String label;
  final String value;
  final String unit;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 6),
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          Text(unit, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    ),
  );
}

class _CoverageCard extends StatelessWidget {
  const _CoverageCard({required this.response});

  final HotspotResponse response;

  @override
  Widget build(BuildContext context) {
    if (response.coverageState == 'parcial') {
      return const SizedBox.shrink();
    }
    final (icon, color) = switch (response.coverageState) {
      'parcial' => (Icons.warning_amber_rounded, GeoCruzColors.amber),
      'sin_cobertura' => (
        Icons.signal_wifi_statusbar_null_rounded,
        GeoCruzColors.amber,
      ),
      'disponible' => (Icons.check_circle_outline_rounded, GeoCruzColors.green),
      _ => (Icons.help_outline_rounded, GeoCruzColors.textSoft),
    };
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 19),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '${switch (response.coverageState) {
                'disponible' => 'Datos disponibles con límites del producto',
                'parcial' => 'Cobertura parcial',
                'sin_cobertura' => 'Sin cobertura comprobada',
                'desconocida' => 'Cobertura desconocida',
                _ => 'Cobertura no informada',
              }}. ${response.coverage['fundamento'] ?? ''}',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}

class _MapControls extends StatelessWidget {
  const _MapControls({
    required this.onZoomIn,
    required this.onZoomOut,
    required this.onFit,
  });

  final VoidCallback onZoomIn;
  final VoidCallback onZoomOut;
  final VoidCallback onFit;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    elevation: 2,
    borderRadius: BorderRadius.circular(10),
    clipBehavior: Clip.antiAlias,
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Acercar',
          onPressed: onZoomIn,
          icon: const Icon(Icons.add),
        ),
        const SizedBox(width: 34, child: Divider(height: 1)),
        IconButton(
          tooltip: 'Alejar',
          onPressed: onZoomOut,
          icon: const Icon(Icons.remove),
        ),
        const SizedBox(width: 34, child: Divider(height: 1)),
        IconButton(
          tooltip: 'Ver todos los focos de esta página',
          onPressed: onFit,
          icon: const Icon(Icons.center_focus_strong),
        ),
      ],
    ),
  );
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
