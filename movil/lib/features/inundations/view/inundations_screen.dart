import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/maps/map_chrome.dart';
import '../../../core/maps/map_completo_actions.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../model/inundation_models.dart';
import '../model/inundations_repository.dart';

class InundationsScreen extends StatefulWidget {
  const InundationsScreen({
    required this.api,
    required this.onUnauthorized,
    this.showAppBar = true,
    this.repository,
    super.key,
  });

  final GeoCruzApiClient api;
  final VoidCallback onUnauthorized;
  final bool showAppBar;
  final InundationsRepository? repository;

  @override
  State<InundationsScreen> createState() => _InundationsScreenState();
}

class _InundationsScreenState extends State<InundationsScreen> {
  static const _initialCenter = LatLng(-17.7833, -63.1821);

  final MapController _mapController = MapController();
  final LayerHitNotifier<WaterFeature> _hitNotifier = ValueNotifier(null);
  late final InundationsRepository _repository;
  MapBaseStyle _mapStyle = MapBaseStyle.relieve;
  WaterCatalog? _catalog;
  WaterProductOption? _selectedProduct;
  WaterResult? _result;
  WaterFeature? _selectedFeature;
  String? _selectedPlace;
  Object? _catalogError;
  Object? _queryError;
  bool _loadingCatalog = true;
  bool _loadingResult = false;
  bool _mapReady = false;
  LatLng? _searchPin;
  String? _searchPinLabel;
  int _catalogRequest = 0;
  int _queryRequest = 0;

  @override
  void initState() {
    super.initState();
    _repository = widget.repository ?? InundationsRepository(widget.api);
    _loadCatalog();
  }

  @override
  void dispose() {
    _catalogRequest++;
    _queryRequest++;
    _hitNotifier.dispose();
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _loadCatalog() async {
    final request = ++_catalogRequest;
    ++_queryRequest;
    final previous = _selectedProduct;
    setState(() {
      _loadingCatalog = true;
      _loadingResult = false;
      _catalogError = null;
      _queryError = null;
      _result = null;
      _selectedFeature = null;
    });
    try {
      final catalog = await _repository.loadCatalog();
      if (!mounted || request != _catalogRequest) return;
      WaterProductOption? selected;
      if (previous != null) {
        for (final product in catalog.products) {
          if (product.identity == previous.identity) {
            selected = product;
            break;
          }
        }
      }
      selected ??= catalog.products.firstOrNull;
      setState(() {
        _catalog = catalog;
        _selectedProduct = selected;
        _selectedPlace = selected?.territoryId;
        _loadingCatalog = false;
      });
      // La lista y el resultado se consultan una sola vez cada uno al abrir.
      // Cambiar filtros no dispara llamadas hasta que se toca el botón.
      if (selected != null) await _query(selected);
    } catch (error) {
      if (!mounted || request != _catalogRequest) return;
      _handleUnauthorized(error);
      setState(() {
        _loadingCatalog = false;
        _catalogError = error;
      });
    }
  }

  Future<void> _query([WaterProductOption? product]) async {
    final selected = product ?? _selectedProduct;
    if (selected == null) return;
    final request = ++_queryRequest;
    setState(() {
      _selectedProduct = selected;
      _selectedFeature = null;
      _result = null;
      _queryError = null;
      _loadingResult = true;
    });
    try {
      final result = await _repository.query(selected);
      if (!mounted || request != _queryRequest) return;
      setState(() {
        _result = result;
        _loadingResult = false;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) => _fitToResult());
    } catch (error) {
      if (!mounted || request != _queryRequest) return;
      _handleUnauthorized(error);
      setState(() {
        _queryError = error;
        _loadingResult = false;
      });
    }
  }

  void _handleUnauthorized(Object error) {
    if (error is GeoCruzApiException && error.statusCode == 401) {
      widget.onUnauthorized();
    }
  }

  List<WaterProductOption> get _comparisons =>
      _catalog?.products
          .where((product) => product.territoryId == _selectedPlace)
          .toList(growable: false) ??
      const [];

  void _changePlace(String? place) {
    if (place == null) return;
    final options = (_catalog?.products ?? const <WaterProductOption>[])
        .where((product) => product.territoryId == place)
        .toList(growable: false);
    if (options.isEmpty) return;
    ++_queryRequest;
    setState(() {
      _selectedPlace = place;
      _selectedProduct = options.first;
      _result = null;
      _queryError = null;
      _loadingResult = false;
      _selectedFeature = null;
    });
  }

  void _changeComparison(String? id) {
    if (id == null) return;
    WaterProductOption? selected;
    for (final product in _comparisons) {
      if (product.id == id) selected = product;
    }
    if (selected == null) return;
    ++_queryRequest;
    setState(() {
      _selectedProduct = selected;
      _result = null;
      _queryError = null;
      _loadingResult = false;
      _selectedFeature = null;
    });
  }

  void _fitToResult() {
    if (!mounted || !_mapReady || _result == null) return;
    final points = _result!.features
        .expand((feature) => feature.rings.first)
        .toList(growable: false);
    if (points.length < 2) return;
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds.fromPoints(points),
        padding: const EdgeInsets.fromLTRB(44, 90, 44, 280),
        maxZoom: 14,
      ),
    );
  }

  Future<void> _openFullscreenMap() {
    final result = _result;
    final points =
        result?.features
            .expand((feature) => feature.rings.first)
            .toList(growable: false) ??
        const <LatLng>[];
    final polygons =
        result?.features
            .map(
              (feature) => Polygon<WaterFeature>(
                points: feature.rings.first,
                holePointsList: feature.rings.skip(1).toList(growable: false),
                color: _colorFor(feature.kind).withValues(alpha: 0.28),
                borderColor: _colorFor(feature.kind),
                borderStrokeWidth: feature.id == _selectedFeature?.id ? 3 : 1.2,
              ),
            )
            .toList(growable: false) ??
        const <Polygon<WaterFeature>>[];
    return openFullscreenMap(
      context: context,
      title: 'Agua e inundaciones',
      center: _mapReady ? _mapController.camera.center : _initialCenter,
      zoom: _mapReady ? _mapController.camera.zoom : 6,
      markers: const <Marker>[],
      polygons: polygons,
      fitBounds: points.length > 1 ? LatLngBounds.fromPoints(points) : null,
      initialStyle: _mapStyle,
    );
  }

  void _selectTouchedFeature() {
    final hits = _hitNotifier.value?.hitValues;
    if (hits == null || hits.isEmpty) return;
    final id = hits.first.id;
    final feature = _result?.features
        .where((item) => item.id == id)
        .firstOrNull;
    if (feature == null) return;
    setState(() => _selectedFeature = feature);
  }

  void _zoom(double change) {
    if (!_mapReady) return;
    final camera = _mapController.camera;
    _mapController.move(camera.center, (camera.zoom + change).clamp(3, 18));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.showAppBar
          ? AppBar(
              title: const Text('Agua e inundaciones'),
              actions: [
                IconButton(
                  tooltip: 'Actualizar comparaciones',
                  onPressed: _loadingCatalog ? null : _loadCatalog,
                  icon: const Icon(Icons.refresh_rounded),
                ),
              ],
            )
          : null,
      body: Stack(
        fit: StackFit.expand,
        children: [
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: _initialCenter,
              initialZoom: 6,
              minZoom: 3,
              maxZoom: 18,
              onMapReady: () {
                _mapReady = true;
                _fitToResult();
              },
            ),
            children: [
              _mapStyle.toLayer(),
              if (_result case final result? when result.features.isNotEmpty)
                GestureDetector(
                  onTap: _selectTouchedFeature,
                  child: PolygonLayer<WaterFeature>(
                    hitNotifier: _hitNotifier,
                    polygons: [
                      for (final feature in result.features)
                        Polygon<WaterFeature>(
                          points: feature.rings.first,
                          holePointsList: feature.rings
                              .skip(1)
                              .toList(growable: false),
                          color: _colorFor(
                            feature.kind,
                          ).withValues(alpha: 0.28),
                          borderColor: _colorFor(feature.kind),
                          borderStrokeWidth: feature.id == _selectedFeature?.id
                              ? 3
                              : 1.2,
                          hitValue: feature,
                        ),
                    ],
                  ),
                ),
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
              onFit: _fitToResult,
              onFullscreen: _openFullscreenMap,
            ),
          ),
          if (!widget.showAppBar)
            Positioned(
              top: 10,
              left: 10,
              right: 58,
              child: _buildTopControls(context),
            ),
          DraggableScrollableSheet(
            initialChildSize: 0.43,
            minChildSize: 0.29,
            maxChildSize: 0.82,
            snap: true,
            snapSizes: const [0.43, 0.82],
            builder: (context, scrollController) => Material(
              color: Theme.of(context).colorScheme.surface,
              elevation: 8,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(24),
              ),
              clipBehavior: Clip.antiAlias,
              child: SingleChildScrollView(
                controller: scrollController,
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 30),
                child: _sheetContent(context),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTopControls(BuildContext context) {
    final selected = _selectedProduct;
    final place = selected?.territoryName ?? 'Santa Cruz';
    final dates = selected == null
        ? 'Sin comparación publicada'
        : '${_date(selected.referenceDate)} → ${_date(selected.comparisonDate)}';
    return Material(
      elevation: 2,
      color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.96),
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 4, 4, 4),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    place,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    dates,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                ],
              ),
            ),
            IconButton.filledTonal(
              visualDensity: VisualDensity.compact,
              tooltip: 'Buscar lugar',
              onPressed: _openPlaceSearch,
              icon: const Icon(Icons.search_rounded, size: 19),
            ),
            IconButton.filledTonal(
              visualDensity: VisualDensity.compact,
              tooltip: 'Tiempo y pronóstico',
              onPressed: _openWeather,
              icon: const Icon(Icons.cloud_outlined, size: 19),
            ),
            IconButton.filledTonal(
              visualDensity: VisualDensity.compact,
              tooltip: 'Filtros',
              onPressed: _openFilterSheet,
              icon: const Icon(Icons.tune_rounded, size: 19),
            ),
            IconButton.filledTonal(
              visualDensity: VisualDensity.compact,
              tooltip: 'Actualizar',
              onPressed: _loadingCatalog ? null : _loadCatalog,
              icon: const Icon(Icons.refresh_rounded, size: 19),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openPlaceSearch() async {
    final result = await openPlaceSearch(
      context: context,
      api: widget.api,
      near:
          _searchPin ??
          (_mapReady ? _mapController.camera.center : _initialCenter),
    );
    if (!mounted || result == null) return;
    final point = LatLng(result.latitude, result.longitude);
    setState(() => _searchPinLabel = result.name);
    _searchPin = point;
    if (_mapReady) _mapController.move(point, 10);
  }

  Future<void> _openWeather() => openWeatherSheet(
    context: context,
    api: widget.api,
    point:
        _searchPin ??
        (_mapReady ? _mapController.camera.center : _initialCenter),
    title: _searchPinLabel ?? _selectedProduct?.territoryName ?? 'Santa Cruz',
  );

  Future<void> _openFilterSheet() => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    // El panel modal es una ruta separada: necesita su propio estado para que
    // la lista de fechas se reconstruya al cambiar el lugar.
    builder: (sheetContext) => StatefulBuilder(
      builder: (context, setSheetState) => Padding(
        padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Lugar y fechas',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            if (_comparisons.isEmpty)
              const Padding(
                padding: EdgeInsets.only(bottom: 8),
                child: Text('No hay comparaciones publicadas para filtrar.'),
              )
            else ...[
              _filters(
                context,
                onPlaceChanged: (place) {
                  _changePlace(place);
                  setSheetState(() {});
                },
                onComparisonChanged: (id) {
                  _changeComparison(id);
                  setSheetState(() {});
                },
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _loadingResult
                      ? null
                      : () {
                          Navigator.of(sheetContext).pop();
                          _query();
                        },
                  icon: const Icon(Icons.compare_arrows_rounded),
                  label: const Text('Ver comparación'),
                ),
              ),
            ],
          ],
        ),
      ),
    ),
  );

  Widget _sheetContent(BuildContext context) {
    final catalog = _catalog;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Center(
          child: Container(
            width: 34,
            height: 4,
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.outlineVariant,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
        ),
        Text(
          'Agua e inundaciones',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 4),
        Text(
          'Elige un lugar y un par de fechas publicados.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 14),
        if (_loadingCatalog && catalog == null)
          const _LoadingState()
        else if (_catalogError != null && catalog == null)
          _ErrorState(
            message: _errorMessage(_catalogError!),
            onRetry: _loadCatalog,
          )
        else if (catalog?.products.isEmpty ?? true)
          const _EmptyState()
        else ...[
          _filters(context),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _loadingResult ? null : () => _query(),
              icon: _loadingResult
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.compare_arrows_rounded),
              label: Text(_loadingResult ? 'Consultando…' : 'Ver comparación'),
            ),
          ),
          if (_queryError case final error?) ...[
            const SizedBox(height: 10),
            _ErrorState(message: _errorMessage(error), onRetry: _query),
          ],
          if (_loadingResult) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(minHeight: 3),
            const SizedBox(height: 8),
            Text(
              'Cargando el mapa y el resumen…',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          if (_result case final result?) ...[
            const SizedBox(height: 16),
            _resultSummary(context, result),
          ],
        ],
      ],
    );
  }

  Widget _filters(
    BuildContext context, {
    ValueChanged<String?>? onPlaceChanged,
    ValueChanged<String?>? onComparisonChanged,
  }) {
    final selected = _selectedProduct;
    if (selected == null) return const SizedBox.shrink();
    final products = _comparisons;
    final places = <String, String>{};
    for (final product in _catalog!.products) {
      places.putIfAbsent(product.territoryId, () => product.territoryName);
    }
    return Column(
      children: [
        DropdownButtonFormField<String>(
          key: ValueKey('water-place-$_selectedPlace'),
          initialValue: _selectedPlace,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Lugar',
            prefixIcon: Icon(Icons.place_outlined),
          ),
          items: [
            for (final entry in places.entries)
              DropdownMenuItem(
                value: entry.key,
                child: Text(entry.value, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: onPlaceChanged ?? _changePlace,
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          key: ValueKey('water-comparison-${selected.id}'),
          initialValue: selected.id,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Comparación',
            prefixIcon: Icon(Icons.date_range_rounded),
          ),
          items: [
            for (final product in products)
              DropdownMenuItem(
                value: product.id,
                child: Text(
                  '${_date(product.referenceDate)} → ${_date(product.comparisonDate)}',
                  overflow: TextOverflow.ellipsis,
                ),
              ),
          ],
          onChanged: onComparisonChanged ?? _changeComparison,
        ),
      ],
    );
  }

  Widget _resultSummary(BuildContext context, WaterResult result) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.water_drop_rounded, color: scheme.primary),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        result.stateLabel,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${result.territoryName} · ${_date(result.referenceDate)} a ${_date(result.comparisonDate)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _metric(
                        context,
                        'Área estudiada',
                        result.areaStudiedKm2,
                        'km²',
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _metric(
                        context,
                        'Área comparable',
                        result.areaComparableKm2,
                        'km²',
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _metric(
                        context,
                        'Cobertura',
                        result.comparableFraction * 100,
                        '%',
                      ),
                    ),
                  ],
                ),
                const Divider(height: 24),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    _waterMetric(
                      context,
                      'Agua persistente',
                      result.persistentKm2,
                    ),
                    _waterMetric(context, 'Agua nueva', result.newKm2),
                    _waterMetric(context, 'Agua retirada', result.removedKm2),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        _legend(context),
        if (_selectedFeature case final feature?) ...[
          const SizedBox(height: 8),
          Card(
            child: ListTile(
              leading: Icon(
                Icons.touch_app_outlined,
                color: _colorFor(feature.kind),
              ),
              title: Text(waterClasses[feature.kind]!),
              subtitle: const Text('Clase de la zona seleccionada'),
              trailing: IconButton(
                tooltip: 'Cerrar detalle',
                onPressed: () => setState(() => _selectedFeature = null),
                icon: const Icon(Icons.close_rounded),
              ),
            ),
          ),
        ] else if (result.features.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 8, 4, 2),
            child: Text(
              'Toca una figura del mapa para ver su clase.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        const SizedBox(height: 8),
        Card(
          child: ExpansionTile(
            leading: const Icon(Icons.info_outline_rounded),
            title: const Text('Fuente y alcance'),
            childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            children: [
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  '${result.sourceName} · ${result.sourceProduct}\n${result.method} · ${_formatArea(result.resolutionMeters)} m',
                ),
              ),
            ],
          ),
        ),
        for (final warning in result.warnings)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(warning, style: Theme.of(context).textTheme.bodySmall),
          ),
        const SizedBox(height: 8),
        Text(
          'El análisis corresponde a un recorte parcial y no confirma por sí solo una inundación.',
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
        ),
      ],
    );
  }

  Widget _metric(
    BuildContext context,
    String label,
    double value,
    String unit,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: Theme.of(context).textTheme.labelSmall,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      const SizedBox(height: 3),
      Text(
        '${_formatArea(value)} $unit',
        style: Theme.of(
          context,
        ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
      ),
    ],
  );

  Widget _waterMetric(BuildContext context, String label, double? value) =>
      Chip(
        avatar: Icon(
          Icons.water_drop_outlined,
          size: 16,
          color: Theme.of(context).colorScheme.primary,
        ),
        label: Text(
          '$label: ${value == null ? 'Sin dato' : '${_formatArea(value)} km²'}',
        ),
        visualDensity: VisualDensity.compact,
      );

  Widget _legend(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 4,
    children: [
      for (final entry in waterClasses.entries)
        Chip(
          avatar: CircleAvatar(
            radius: 5,
            backgroundColor: _colorFor(entry.key),
          ),
          label: Text(entry.value),
          visualDensity: VisualDensity.compact,
        ),
    ],
  );

  Color _colorFor(String kind) => switch (kind) {
    'agua_persistente' => const Color(0xFF147D64),
    'agua_nueva' => const Color(0xFF167DB3),
    'agua_retirada' => const Color(0xFFD27A1C),
    _ => const Color(0xFF718096),
  };

  String _date(String date) =>
      '${date.substring(8, 10)}/${date.substring(5, 7)}/${date.substring(0, 4)}';

  String _formatArea(double value) {
    if (value != 0 && value.abs() < 0.001) return '< 0,001';
    return value.toStringAsFixed(3).replaceAll('.', ',');
  }

  String _errorMessage(Object error) {
    if (error is GeoCruzApiException) {
      if (error.statusCode == 404) {
        return 'El servidor conectado todavía no tiene la consulta de inundaciones. Usa el backend de develop actualizado.';
      }
      return error.statusCode == 0
          ? 'No hay conexión. Revisa tu internet e inténtalo de nuevo.'
          : error.message;
    }
    if (error is FormatException) return error.message;
    return 'No se pudo cargar la comparación. Inténtalo de nuevo.';
  }
}

class _LoadingState extends StatelessWidget {
  const _LoadingState();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 24),
    child: Center(child: CircularProgressIndicator()),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          Text(
            'No hay comparaciones de agua publicadas.',
            style: Theme.of(context).textTheme.titleMedium,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

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
            message,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onErrorContainer,
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Reintentar'),
          ),
        ],
      ),
    ),
  );
}
