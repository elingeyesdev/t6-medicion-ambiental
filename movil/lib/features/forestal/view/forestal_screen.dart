import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/maps/map_chrome.dart';
import '../../../core/maps/map_completo_actions.dart';
import '../../../core/network/geocruz_api_client.dart';

typedef _Cell = ({int year, double area, List<LatLng> points});
typedef _Result = ({
  String area,
  String version,
  String coverage,
  List<_Cell> cells,
  List<int> years,
  LatLngBounds bounds,
});

class ForestalScreen extends StatefulWidget {
  const ForestalScreen({
    required this.api,
    required this.onUnauthorized,
    this.showAppBar = true,
    this.sharedMapStyle,
    this.onMapStyleChanged,
    super.key,
  });

  final GeoCruzApiClient api;
  final VoidCallback onUnauthorized;
  final bool showAppBar;
  final MapBaseStyle? sharedMapStyle;
  final ValueChanged<MapBaseStyle>? onMapStyleChanged;

  @override
  State<ForestalScreen> createState() => _ForestalScreenState();
}

class _ForestalScreenState extends State<ForestalScreen> {
  final _format = NumberFormat('0.###', 'es_BO');
  final _hits = ValueNotifier<LayerHitResult<int>?>(null);
  final _mapController = MapController();
  MapBaseStyle _mapStyle = MapBaseStyle.satelite;
  bool _mapReady = false;
  LatLng? _searchPin;
  String? _searchPinLabel;
  int _from = 2024;
  int _to = 2025;
  int? _selected;
  bool _loading = false;
  int _request = 0;
  String? _error;
  _Result? _result;

  @override
  void initState() {
    super.initState();
    _mapStyle = widget.sharedMapStyle ?? _mapStyle;
    _load();
  }

  @override
  void didUpdateWidget(covariant ForestalScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.sharedMapStyle != null && widget.sharedMapStyle != _mapStyle) {
      setState(() => _mapStyle = widget.sharedMapStyle!);
    }
  }

  @override
  void dispose() {
    _request++;
    _hits.dispose();
    _mapController.dispose();
    super.dispose();
  }

  String _area(double value) => '${_format.format(value)} ha';

  Future<void> _load() async {
    final request = ++_request;
    final from = _from;
    final to = _to;
    setState(() {
      _loading = true;
      _error = null;
      _selected = null;
      _result = null;
      _mapReady = false;
    });
    try {
      final data =
          await widget.api.getJson(
                'forestal/piloto',
                authenticated: true,
                timeoutOverride: const Duration(seconds: 15),
                queryParameters: {'desde': '$from', 'hasta': '$to'},
              )
              as Map<String, dynamic>;
      final summary = data['resumen'] as Map<String, dynamic>;
      final query = summary['consulta'] as Map<String, dynamic>;
      final layer = data['capa'] as Map<String, dynamic>;
      if (data['revision'] != true ||
          query['desde'] != from ||
          query['hasta'] != to ||
          layer['type'] != 'FeatureCollection') {
        throw const FormatException('Período o capa incorrectos.');
      }
      final bbox = (summary['bbox'] as List).cast<num>();
      final territoryBounds = LatLngBounds(
        LatLng(bbox[1].toDouble(), bbox[0].toDouble()),
        LatLng(bbox[3].toDouble(), bbox[2].toDouble()),
      );
      final cells = <_Cell>[];
      for (final feature in layer['features'] as List) {
        final properties = feature['properties'] as Map<String, dynamic>;
        final geometry = feature['geometry'] as Map<String, dynamic>;
        final year = properties['anio'] as int;
        if (year < from || year > to || geometry['type'] != 'Polygon') {
          throw const FormatException('Polígono fuera del período.');
        }
        final coordinates = (geometry['coordinates'] as List).single as List;
        cells.add((
          year: year,
          area: double.parse(properties['area_ha'].toString()),
          points: [
            for (final point in coordinates)
              LatLng(
                (point[1] as num).toDouble(),
                (point[0] as num).toDouble(),
              ),
          ],
        ));
      }
      final points = cells.expand((cell) => cell.points).toList();
      final bounds = points.isEmpty
          ? territoryBounds
          : LatLngBounds.fromPoints(points);
      final coverage = summary['cobertura_observaciones_fraccion'] as num?;
      final total = double.parse(summary['total_periodo_ha'] as String);
      final version =
          (summary['fuente'] as Map<String, dynamic>)['version'] as String;
      if (!mounted || request != _request) return;
      setState(() {
        _result = (
          area: _area(total),
          version: version,
          coverage: coverage == null
              ? 'Cobertura observacional no informada'
              : 'Cobertura observacional: ${_format.format(coverage * 100)} %',
          cells: cells,
          years: cells.map((cell) => cell.year).toSet().toList()..sort(),
          bounds: bounds,
        );
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted || request != _request) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
      } else {
        setState(
          () => _error = switch (error.statusCode) {
            404 => 'El piloto forestal no está habilitado en este servidor.',
            503 => 'Los datos del piloto no están disponibles en este momento.',
            _ => error.message,
          },
        );
      }
    } catch (_) {
      if (!mounted || request != _request) return;
      setState(
        () => _error =
            'No se pudo consultar el piloto. Revisa la conexión e inténtalo de nuevo.',
      );
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Color _color(int year) => HSLColor.fromAHSL(
    1,
    ((year - 2001) * 137.508) % 360,
    0.65,
    0.38,
  ).toColor();

  Future<void> _pickCell(List<_Cell> cells) async {
    final picked = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: ListView.builder(
          itemCount: cells.length,
          itemBuilder: (context, i) => ListTile(
            leading: Icon(Icons.square_rounded, color: _color(cells[i].year)),
            title: Text('Polígono ${i + 1}'),
            subtitle: Text('${cells[i].year} · ${_area(cells[i].area)}'),
            selected: i == _selected,
            onTap: () => Navigator.of(context).pop(i),
          ),
        ),
      ),
    );
    if (mounted && picked != null) setState(() => _selected = picked);
  }

  Widget _yearInput(String label, int value, ValueChanged<int> update) {
    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: value,
          isExpanded: true,
          isDense: true,
          items: [
            for (var year = 2025; year >= 2001; year--)
              DropdownMenuItem(value: year, child: Text('$year')),
          ],
          onChanged: _loading
              ? null
              : (year) {
                  if (year != null) {
                    setState(() {
                      _request++;
                      update(year);
                      _result = null;
                      _selected = null;
                      _error = null;
                      _loading = false;
                      _mapReady = false;
                    });
                  }
                },
        ),
      ),
    );
  }

  void _zoom(double change) {
    if (!_mapReady) return;
    final camera = _mapController.camera;
    _mapController.move(camera.center, (camera.zoom + change).clamp(3, 18));
  }

  void _fitResult() {
    final result = _result;
    if (!_mapReady || result == null) return;
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: result.bounds,
        padding: const EdgeInsets.all(40),
        maxZoom: 15,
      ),
    );
  }

  Future<void> _openPlaceSearch() async {
    final result = await openPlaceSearch(
      context: context,
      api: widget.api,
      near: _mapReady ? _mapController.camera.center : _result?.bounds.center,
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
    api: widget.api,
    point:
        _searchPin ??
        (_mapReady
            ? _mapController.camera.center
            : _result?.bounds.center ?? const LatLng(-16.5, -64)),
    title: _searchPinLabel ?? 'Piloto forestal · Santa Rosa',
  );

  Future<void> _openFullscreen() {
    final result = _result;
    if (result == null) return Future<void>.value();
    return openFullscreenMap(
      context: context,
      title: 'Cobertura forestal · Santa Rosa',
      center: _mapReady ? _mapController.camera.center : result.bounds.center,
      zoom: _mapReady ? _mapController.camera.zoom : 12,
      markers: [
        if (_searchPin case final point?)
          Marker(
            point: point,
            width: 40,
            height: 40,
            child: const Icon(Icons.place, color: Colors.red),
          ),
      ],
      polygons: [
        for (final (i, cell) in result.cells.indexed)
          Polygon(
            points: cell.points,
            color: _color(
              cell.year,
            ).withValues(alpha: i == _selected ? .9 : .6),
            borderColor: i == _selected ? Colors.black : _color(cell.year),
            borderStrokeWidth: i == _selected ? 3 : 1,
          ),
      ],
      fitBounds: result.bounds,
      initialStyle: _mapStyle,
    );
  }

  Future<void> _openFilters() async {
    var from = _from;
    var to = _to;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, updateSheet) => Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Filtros del piloto forestal',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              const Text(
                'Lugar: Santa Rosa. El piloto todavía no permite elegir otro territorio.',
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  for (final entry in [('Desde', from), ('Hasta', to)]) ...[
                    Expanded(
                      child: DropdownButtonFormField<int>(
                        initialValue: entry.$2,
                        decoration: InputDecoration(
                          labelText: entry.$1,
                          border: const OutlineInputBorder(),
                        ),
                        items: [
                          for (var year = 2025; year >= 2001; year--)
                            DropdownMenuItem(value: year, child: Text('$year')),
                        ],
                        onChanged: (year) {
                          if (year == null) return;
                          updateSheet(() {
                            if (entry.$1 == 'Desde') {
                              from = year;
                            } else {
                              to = year;
                            }
                          });
                        },
                      ),
                    ),
                    if (entry.$1 == 'Desde') const SizedBox(width: 12),
                  ],
                ],
              ),
              if (from > to)
                const Padding(
                  padding: EdgeInsets.only(top: 8),
                  child: Text(
                    'El año inicial debe ser anterior o igual al final.',
                  ),
                ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: from > to
                      ? null
                      : () {
                          Navigator.of(sheetContext).pop();
                          setState(() {
                            _from = from;
                            _to = to;
                          });
                          _load();
                        },
                  child: const Text('Consultar'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _topControls(BuildContext context) => Material(
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
                  'Santa Rosa',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                Text(
                  '$_from–$_to · Piloto',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
            ),
          ),
          IconButton.filledTonal(
            tooltip: 'Buscar lugar',
            onPressed: _openPlaceSearch,
            icon: const Icon(Icons.search_rounded, size: 19),
          ),
          IconButton.filledTonal(
            tooltip: 'Tiempo y pronóstico',
            onPressed: _openWeather,
            icon: const Icon(Icons.cloud_outlined, size: 19),
          ),
          IconButton.filledTonal(
            tooltip: 'Filtros',
            onPressed: _openFilters,
            icon: const Icon(Icons.tune_rounded, size: 19),
          ),
          IconButton.filledTonal(
            tooltip: 'Actualizar',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded, size: 19),
          ),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final result = _result;
    return Scaffold(
      appBar: widget.showAppBar
          ? AppBar(title: const Text('Cobertura forestal'))
          : null,
      body: result == null
          ? _queryPanel(context, scrollController: null)
          : Stack(
              fit: StackFit.expand,
              children: [
                _forestMap(result),
                Positioned.fill(
                  child: MapChrome(
                    style: _mapStyle,
                    onStyleChanged: (style) {
                      setState(() => _mapStyle = style);
                      widget.onMapStyleChanged?.call(style);
                    },
                    onZoomIn: () => _zoom(1),
                    onZoomOut: () => _zoom(-1),
                    onFit: _fitResult,
                    onFullscreen: _openFullscreen,
                  ),
                ),
                if (!widget.showAppBar)
                  Positioned(
                    top: 10,
                    left: 10,
                    right: 58,
                    child: _topControls(context),
                  ),
                DraggableScrollableSheet(
                  initialChildSize: 0.38,
                  minChildSize: 0.25,
                  maxChildSize: 0.82,
                  snap: true,
                  snapSizes: const [0.38, 0.82],
                  builder: (context, scrollController) => Material(
                    color: Theme.of(context).colorScheme.surface,
                    elevation: 8,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(24),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: _queryPanel(
                      context,
                      scrollController: scrollController,
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _forestMap(_Result result) {
    final selected = _selected;
    return FlutterMap(
      mapController: _mapController,
      options: MapOptions(
        initialCameraFit: CameraFit.bounds(
          bounds: result.bounds,
          padding: const EdgeInsets.all(24),
        ),
        maxZoom: 18,
        onMapReady: () {
          _mapReady = true;
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) _fitResult();
          });
        },
      ),
      children: [
        _mapStyle.toLayer(),
        GestureDetector(
          onTap: () {
            final id = _hits.value?.hitValues.firstOrNull;
            if (id != null) setState(() => _selected = id);
          },
          child: PolygonLayer<int>(
            hitNotifier: _hits,
            polygons: [
              for (final (i, cell) in result.cells.indexed)
                Polygon<int>(
                  points: cell.points,
                  color: _color(
                    cell.year,
                  ).withValues(alpha: i == selected ? .9 : .6),
                  borderColor: i == selected ? Colors.black : _color(cell.year),
                  borderStrokeWidth: i == selected ? 3 : 1,
                  hitValue: i,
                ),
            ],
          ),
        ),
        if (_searchPin case final point?)
          MarkerLayer(
            markers: [
              Marker(
                point: point,
                width: 40,
                height: 40,
                child: const Icon(Icons.place, color: Colors.red),
              ),
            ],
          ),
        RichAttributionWidget(
          attributions: [TextSourceAttribution(_mapStyle.attribution)],
        ),
      ],
    );
  }

  Widget _queryPanel(
    BuildContext context, {
    required ScrollController? scrollController,
  }) {
    final result = _result;
    final selected = _selected;
    final period = _from == _to ? '$_from' : '$_from–$_to';
    return ListView(
      controller: scrollController,
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
      children: [
        if (scrollController != null)
          Center(
            child: Container(
              width: 34,
              height: 4,
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
        Text(
          'Piloto forestal · Santa Rosa',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 4),
        Text(
          'Pérdida de cobertura arbórea en revisión técnica.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(child: _yearInput('Desde', _from, (year) => _from = year)),
            const SizedBox(width: 12),
            Expanded(child: _yearInput('Hasta', _to, (year) => _to = year)),
          ],
        ),
        if (_from > _to)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: Text('El año inicial debe ser anterior o igual al final.'),
          ),
        const SizedBox(height: 8),
        FilledButton(
          onPressed: _loading || _from > _to ? null : _load,
          child: Text(_error == null ? 'Consultar' : 'Reintentar'),
        ),
        if (_loading) const LinearProgressIndicator(),
        if (_error case final error?)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Text(error),
            ),
          ),
        if (result != null) ...[
          const SizedBox(height: 12),
          Text(result.area, style: Theme.of(context).textTheme.headlineSmall),
          Text(
            '${result.cells.length} ${result.cells.length == 1 ? 'polígono' : 'polígonos'} · $period',
          ),
          Text(result.coverage),
          if (selected != null)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('Polígono ${selected + 1}'),
              subtitle: Text(
                '${result.cells[selected].year} · ${_area(result.cells[selected].area)}',
              ),
            )
          else if (result.cells.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('No hay polígonos de pérdida en este período.'),
            )
          else
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Toca un polígono del mapa para consultar su año y superficie.',
              ),
            ),
          if (result.cells.isNotEmpty)
            OutlinedButton.icon(
              onPressed: () => _pickCell(result.cells),
              icon: const Icon(Icons.list_rounded),
              label: const Text('Ver lista de polígonos'),
            ),
          if (result.years.isNotEmpty)
            Wrap(
              spacing: 12,
              children: [
                for (final year in result.years)
                  Chip(
                    avatar: CircleAvatar(backgroundColor: _color(year)),
                    label: Text('$year'),
                  ),
              ],
            ),
          const SizedBox(height: 8),
          Text('Fuente: Hansen GFC · ${result.version}'),
          const Text('Cobertura base 2000 · Umbral de copa ≥ 30 %'),
          const Text(
            'El resultado describe el recorte observado. La causa de la pérdida requiere verificación.',
          ),
        ],
      ],
    );
  }
}
