import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
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
    super.key,
  });

  final GeoCruzApiClient api;
  final VoidCallback onUnauthorized;

  @override
  State<ForestalScreen> createState() => _ForestalScreenState();
}

class _ForestalScreenState extends State<ForestalScreen> {
  static const _base = MapBaseStyle.satelite;
  final _format = NumberFormat('0.###', 'es_BO');
  final _hits = ValueNotifier<LayerHitResult<int>?>(null);
  int _from = 2024;
  int _to = 2025;
  int? _selected;
  bool _loading = false;
  String? _error;
  _Result? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _hits.dispose();
    super.dispose();
  }

  String _area(double value) => '${_format.format(value)} ha';

  Future<void> _load() async {
    final from = _from;
    final to = _to;
    setState(() {
      _loading = true;
      _error = null;
      _selected = null;
      _result = null;
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
      final bounds = LatLngBounds(
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
      final coverage = summary['cobertura_observaciones_fraccion'] as num?;
      final total = double.parse(summary['total_periodo_ha'] as String);
      final version =
          (summary['fuente'] as Map<String, dynamic>)['version'] as String;
      if (!mounted) return;
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
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        if (mounted) Navigator.of(context).pop();
      } else {
        setState(
          () => _error = error.statusCode == 404
              ? 'El piloto forestal no está habilitado en este servidor.'
              : error.message,
        );
      }
    } catch (_) {
      if (!mounted) return;
      setState(
        () => _error =
            'No se pudo consultar el piloto. Revisa la conexión e inténtalo de nuevo.',
      );
    } finally {
      if (mounted) setState(() => _loading = false);
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
                      update(year);
                      _result = null;
                      _selected = null;
                      _error = null;
                    });
                  }
                },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final selected = _selected;
    return Scaffold(
      appBar: AppBar(title: const Text('Cobertura forestal')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Piloto de Santa Rosa',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          const Text(
            'Pérdida de cobertura arbórea en el recorte técnico. Resultado en revisión para publicación.',
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _yearInput('Desde', _from, (year) => _from = year),
              ),
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
            child: const Text('Consultar'),
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
            Text('${result.cells.length} polígonos · $_from–$_to'),
            Text(result.coverage),
            const SizedBox(height: 12),
            SizedBox(
              height: 290,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: FlutterMap(
                  options: MapOptions(
                    initialCameraFit: CameraFit.bounds(
                      bounds: result.bounds,
                      padding: const EdgeInsets.all(12),
                    ),
                    maxZoom: 18,
                  ),
                  children: [
                    _base.toLayer(),
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
                              borderColor: i == selected
                                  ? Colors.black
                                  : _color(cell.year),
                              borderStrokeWidth: i == selected ? 3 : 1,
                              hitValue: i,
                            ),
                        ],
                      ),
                    ),
                    RichAttributionWidget(
                      attributions: [TextSourceAttribution(_base.attribution)],
                    ),
                  ],
                ),
              ),
            ),
            if (selected != null)
              ListTile(
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
                  'Toca un polígono para consultar su año y superficie.',
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
      ),
    );
  }
}
