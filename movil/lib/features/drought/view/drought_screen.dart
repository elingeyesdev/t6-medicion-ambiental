import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../hotspots/model/hotspot_models.dart';
import '../model/drought_models.dart';
import '../model/drought_repository.dart';

class DroughtScreen extends StatefulWidget {
  const DroughtScreen({
    required this.apiClient,
    this.initialMonth = '2024-09',
    this.showAppBar = true,
    this.repository,
    super.key,
  });

  final GeoCruzApiClient apiClient;
  final String initialMonth;
  final bool showAppBar;
  final DroughtRepository? repository;

  @override
  State<DroughtScreen> createState() => _DroughtScreenState();
}

class _DroughtScreenState extends State<DroughtScreen> {
  late final DroughtRepository _repository;
  final MapController _mapController = MapController();

  late DroughtFilters _filters;
  List<TerritoryOption> _territories = const [];
  DroughtResponse? _response;
  DroughtSample? _selectedSample;

  bool _loading = false;
  bool _catalogLoading = false;
  String? _errorMessage;
  bool _mapReady = false;
  int _request = 0;

  @override
  void initState() {
    super.initState();
    _repository = widget.repository ?? DroughtRepository(widget.apiClient);
    _filters = DroughtFilters(mesFinal: widget.initialMonth);
    _loadCatalogAndQuery();
  }

  Future<void> _loadCatalogAndQuery() async {
    setState(() {
      _catalogLoading = true;
      _errorMessage = null;
    });

    try {
      _territories = await _repository.loadTerritories();
    } catch (_) {
      // Si falla catálogo territorial, se puede consultar sin filtros territoriales
    } finally {
      if (mounted) setState(() => _catalogLoading = false);
    }

    if (mounted) await _executeQuery();
  }

  Future<void> _executeQuery() async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _errorMessage = null;
      _selectedSample = null;
      _mapReady = false;
    });

    try {
      final res = await _repository.query(_filters);
      if (!mounted || request != _request) return;
      setState(() {
        _response = res;
        _loading = false;
      });
    } on FormatException catch (e) {
      if (!mounted || request != _request) return;
      setState(() {
        _errorMessage = e.message;
        _loading = false;
      });
    } on GeoCruzApiException catch (e) {
      if (!mounted || request != _request) return;
      setState(() {
        _errorMessage = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted || request != _request) return;
      setState(() {
        _errorMessage = 'No se pudo cargar la consulta. Inténtalo otra vez.';
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _request++;
    _mapController.dispose();
    super.dispose();
  }

  void _fitBounds(List<DroughtSample> datos) {
    if (datos.isEmpty) return;
    if (datos.length == 1) {
      _mapController.move(
        LatLng(datos.first.lat, datos.first.lon),
        9.0,
      );
      return;
    }
    double minLat = 90.0, maxLat = -90.0, minLon = 180.0, maxLon = -180.0;
    for (final d in datos) {
      if (d.lat < minLat) minLat = d.lat;
      if (d.lat > maxLat) maxLat = d.lat;
      if (d.lon < minLon) minLon = d.lon;
      if (d.lon > maxLon) maxLon = d.lon;
    }
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds(
          LatLng(minLat - 0.2, minLon - 0.2),
          LatLng(maxLat + 0.2, maxLon + 0.2),
        ),
        padding: const EdgeInsets.all(24),
      ),
    );
  }

  String _territoryName(String? pcode) {
    if (pcode == null) return 'Sin municipio identificado';
    for (final t in _territories) {
      if (t.identifier == pcode) return t.name;
    }
    return pcode;
  }

  void _onSampleTapped(DroughtSample sample) {
    setState(() => _selectedSample = sample);
    if (_mapReady) {
      _mapController.move(LatLng(sample.lat, sample.lon), 9.5);
    }
    _showSampleDetailSheet(sample);
  }

  void _showSampleDetailSheet(DroughtSample sample) {
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (ctx) {
        final category = sample.spi.categoryInfo;
        final rainfall = sample.precipitacionAcumuladaMm != null
            ? '${sample.precipitacionAcumuladaMm!.toStringAsFixed(1)} mm'
            : 'Sin dato';
        final spiVal = sample.spi.valor != null
            ? sample.spi.valor!.toStringAsFixed(2)
            : 'Sin índice';

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        'Muestra ${sample.idMuestra}',
                        style: Theme.of(ctx).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: category.color.withValues(alpha: 0.15),
                        border: Border.all(color: category.color),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        category.label,
                        style: TextStyle(
                          color: category.color,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _detailRow('Territorio contexto',
                    _territoryName(sample.territorioContexto)),
                _detailRow('Centro de celda ERA5',
                    _territoryName(sample.municipioDelCentroCelda)),
                if (sample.centroDifiere)
                  Container(
                    margin: const EdgeInsets.symmetric(vertical: 6),
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.amber.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.amber.shade300),
                    ),
                    child: Text(
                      'Advertencia: El centro de celda (${sample.municipioDelCentroCelda}) cae fuera de ${sample.territorioContexto}. Se conserva el municipio solicitado.',
                      style: TextStyle(
                          fontSize: 14, color: Colors.amber.shade900),
                    ),
                  ),
                _detailRow(
                  'Coordenada solicitada',
                  '${sample.coordenadaSolicitada[0].toStringAsFixed(4)}, ${sample.coordenadaSolicitada[1].toStringAsFixed(4)}',
                ),
                _detailRow(
                  'Coordenada celda',
                  '${sample.coordenadaCelda[0].toStringAsFixed(4)}, ${sample.coordenadaCelda[1].toStringAsFixed(4)}',
                ),
                _detailRow('Precipitación 3 meses', rainfall),
                _detailRow('Índice SPI-3', spiVal),
                if (!sample.spi.hasValue && sample.spi.motivo != null)
                  _detailRow('Motivo sin índice',
                      DroughtMotives.describe(sample.spi.motivo)),
                _detailRow('Años válidos referencia',
                    '${sample.spi.aniosValidos} años (1991–2020)'),
                _detailRow('Fuente de datos',
                    'Open-Meteo ERA5 (reanálisis 0.25°, CC BY 4.0)'),
                const SizedBox(height: 16),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 140,
            child: Text(
              label,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                color: Colors.black54,
                fontSize: 14,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontWeight: FontWeight.w500,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openFiltersDialog() async {
    final provinces = _territories.where((t) => t.type == 'provincia').toList();
    String? selectedProv = _filters.provincia;
    String? selectedMun = _filters.municipio;
    String selectedMonth = _filters.mesFinal;
    String? validationError;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            final munList = _territories
                .where((t) =>
                    t.type == 'municipio' &&
                    (selectedProv == null || t.parentId == selectedProv))
                .toList();

            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 8,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Filtros de sequías',
                      style: Theme.of(ctx).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      initialValue: selectedMonth,
                      decoration: const InputDecoration(
                        labelText: 'Mes final (AAAA-MM)',
                        hintText: 'Ej. 2024-09',
                        helperText:
                            'Ventana de 3 meses que termina en este mes (UTC)',
                        prefixIcon: Icon(Icons.calendar_month_outlined),
                        border: OutlineInputBorder(),
                      ),
                      onChanged: (val) => selectedMonth = val.trim(),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String?>(
                      key: const Key('drought-province'),
                      isExpanded: true,
                      initialValue: selectedProv,
                      decoration: const InputDecoration(
                        labelText: 'Provincia',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('Todo Santa Cruz'),
                        ),
                        ...provinces.map(
                          (p) => DropdownMenuItem<String?>(
                            value: p.identifier,
                            child: Text(p.name, overflow: TextOverflow.ellipsis),
                          ),
                        ),
                      ],
                      onChanged: (val) {
                        setSheetState(() {
                          selectedProv = val;
                          selectedMun = null;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String?>(
                      key: ValueKey('drought-municipality-${selectedProv ?? 'all'}'),
                      isExpanded: true,
                      initialValue: selectedMun,
                      decoration: const InputDecoration(
                        labelText: 'Municipio',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('Todos los municipios'),
                        ),
                        ...munList.map(
                          (m) => DropdownMenuItem<String?>(
                            value: m.identifier,
                            child: Text(m.name, overflow: TextOverflow.ellipsis),
                          ),
                        ),
                      ],
                      onChanged: (val) {
                        setSheetState(() => selectedMun = val);
                      },
                    ),
                    const SizedBox(height: 24),
                    if (validationError != null)
                      Text(validationError!, style: TextStyle(color: Theme.of(ctx).colorScheme.error)),
                    FilledButton.icon(
                      icon: const Icon(Icons.check),
                      label: const Text('Aplicar filtros'),
                      onPressed: () {
                        final next = DroughtFilters(mesFinal: selectedMonth, provincia: selectedProv, municipio: selectedMun);
                        try {
                          next.validate(DateTime.now().toUtc());
                        } on FormatException catch (error) {
                          setSheetState(() => validationError = error.message);
                          return;
                        }
                        Navigator.of(ctx).pop();
                        if (!mounted) return;
                        setState(() => _filters = next);
                        _executeQuery();
                      },
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final response = _response;
    final summary = response?.resumen;
    final coverage = response?.cobertura;

    final body = _loading || _catalogLoading
        ? const Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(height: 12),
                Text('Cargando...'),
              ],
            ),
          )
        : _errorMessage != null
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
                        _errorMessage!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 15),
                      ),
                      const SizedBox(height: 16),
                      FilledButton.icon(
                        onPressed: _executeQuery,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Reintentar'),
                      ),
                    ],
                  ),
                ),
              )
            : response == null
                ? const SizedBox.shrink()
                : SingleChildScrollView(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (!widget.showAppBar) _buildTopControls(),
                        _buildSummaryCard(summary, coverage),
                        _buildMapCard(response.datos),
                        _buildLegend(),
                        _buildSamplesList(response.datos),
                        _buildWarningsSection(response.advertencias),
                        const SizedBox(height: 24),
                      ],
                    ),
                  );

    if (!widget.showAppBar) {
      return body;
    }

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Sequías meteorológicas',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            Text(
              'Mes final ${_filters.mesFinal} · Ventana 3 meses',
              style: const TextStyle(fontSize: 14, color: Colors.black54),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.tune),
            tooltip: 'Filtros',
            onPressed: _openFiltersDialog,
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Recargar',
            onPressed: _executeQuery,
          ),
        ],
      ),
      body: body,
    );
  }

  Widget _buildTopControls() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Mes final ${_filters.mesFinal}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                const Text(
                  'Ventana de 3 meses · ERA5',
                  style: TextStyle(fontSize: 14, color: Colors.black54),
                ),
              ],
            ),
          ),
          FilledButton.tonalIcon(
            onPressed: _openFiltersDialog,
            icon: const Icon(Icons.tune, size: 18),
            label: const Text('Filtros'),
          ),
          const SizedBox(width: 8),
          IconButton.filledTonal(
            onPressed: _executeQuery,
            icon: const Icon(Icons.refresh, size: 18),
            tooltip: 'Recargar',
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(DroughtSummary? summary, DroughtCoverage? coverage) {
    if (summary == null) return const SizedBox.shrink();

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.water_drop_outlined,
                    color: GeoCruzColors.green, size: 20),
                const SizedBox(width: 8),
                Text(
                  'Resumen de sequías',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 24,
              runSpacing: 16,
              children: [
                _metricItem(
                  '${summary.totalMuestras}',
                  'Puntos evaluados',
                  Colors.blueGrey.shade800,
                ),
                _metricItem(
                  '${summary.muestrasConIndice}',
                  'Con índice',
                  GeoCruzColors.green,
                ),
                _metricItem(
                  '${summary.muestrasSinIndice}',
                  'Sin índice',
                  Colors.grey.shade600,
                ),
                _metricItem(
                  '${summary.muestrasConDeficit}',
                  'Con déficit (≤-1)',
                  summary.muestrasConDeficit > 0
                      ? const Color(0xFFBC673B)
                      : Colors.black54,
                ),
              ],
            ),
            if (coverage != null) ...[
              const SizedBox(height: 16),
              Text(
                'Datos para ${coverage.municipiosConDatos} de ${coverage.municipiosEnFiltro} municipios consultados.',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              Text(
                coverage.esParcial
                    ? 'Cobertura parcial. Faltan datos; eso no significa que no haya sequía.'
                    : 'Son puntos de referencia; no describen todo el municipio.',
                style: const TextStyle(fontSize: 16),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _metricItem(String val, String label, Color color) {
    return Column(
      children: [
        Text(
          val,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(fontSize: 14, color: Colors.black54),
        ),
      ],
    );
  }

  Widget _buildMapCard(List<DroughtSample> datos) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: SizedBox(
        height: 320,
        child: Stack(
          children: [
            FlutterMap(
              key: ValueKey('drought-map-$_request'),
              mapController: _mapController,
              options: MapOptions(
                initialCenter: const LatLng(-17.8, -63.1),
                initialZoom: 7.2,
                onMapReady: () {
                  _mapReady = true;
                  if (datos.isNotEmpty) _fitBounds(datos);
                },
              ),
              children: [
                TileLayer(
                  urlTemplate:
                      'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'bo.edu.univalle.geocruz',
                ),
                MarkerLayer(
                  markers: datos.map((sample) {
                    final cat = sample.spi.categoryInfo;
                    final isSelected =
                        _selectedSample?.idMuestra == sample.idMuestra;

                    return Marker(
                      point: LatLng(sample.lat, sample.lon),
                      width: isSelected ? 40 : 30,
                      height: isSelected ? 40 : 30,
                      child: GestureDetector(
                        onTap: () => _onSampleTapped(sample),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          decoration: BoxDecoration(
                            color: cat.color,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: isSelected ? Colors.white : Colors.black87,
                              width: isSelected ? 3 : 1.5,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.3),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Icon(
                            Icons.water_drop,
                            color: Colors.white,
                            size: isSelected ? 22 : 16,
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ],
            ),
            Positioned(
              right: 12,
              top: 12,
              child: Column(
                children: [
                  FloatingActionButton.small(
                    heroTag: 'drought_zoom_in',
                    onPressed: () {
                      _mapController.move(
                        _mapController.camera.center,
                        _mapController.camera.zoom + 1,
                      );
                    },
                    child: const Icon(Icons.add),
                  ),
                  const SizedBox(height: 6),
                  FloatingActionButton.small(
                    heroTag: 'drought_zoom_out',
                    onPressed: () {
                      _mapController.move(
                        _mapController.camera.center,
                        _mapController.camera.zoom - 1,
                      );
                    },
                    child: const Icon(Icons.remove),
                  ),
                  const SizedBox(height: 6),
                  FloatingActionButton.small(
                    heroTag: 'drought_center',
                    onPressed: () => _fitBounds(datos),
                    child: const Icon(Icons.my_location),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLegend() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Wrap(
        spacing: 12,
        runSpacing: 6,
        children: DroughtCategory.all.map((c) {
          return Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: c.color,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                c.label,
                style: const TextStyle(fontSize: 14, color: Colors.black87),
              ),
            ],
          );
        }).toList(),
      ),
    );
  }

  Widget _buildSamplesList(List<DroughtSample> datos) {
    if (datos.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(
          child: Text('No hay muestras preparadas para este filtro.'),
        ),
      );
    }

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Puntos con datos (${datos.length})',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: 8),
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: datos.length > 10 ? 10 : datos.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (ctx, idx) {
                final d = datos[idx];
                final cat = d.spi.categoryInfo;
                final spiText = d.spi.valor != null
                    ? 'SPI: ${d.spi.valor!.toStringAsFixed(2)}'
                    : 'Sin índice';

                return ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    radius: 14,
                    backgroundColor: cat.color,
                    child: const Icon(Icons.water_drop,
                        size: 14, color: Colors.white),
                  ),
                  title: Text(
                    '${d.idMuestra} · ${_territoryName(d.territorioContexto)}',
                    style: const TextStyle(
                        fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                  subtitle: Text(
                    '${cat.label} · Lluvia: ${d.precipitacionAcumuladaMm?.toStringAsFixed(1) ?? '—'} mm',
                    style: const TextStyle(fontSize: 14),
                  ),
                  trailing: Text(
                    spiText,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: cat.color,
                    ),
                  ),
                  onTap: () => _onSampleTapped(d),
                );
              },
            ),
            if (datos.length > 10)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Mostrando 10 de ${datos.length} puntos. Toca un punto en el mapa para ver su ficha completa.',
                  style: const TextStyle(fontSize: 14, color: Colors.black54),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildWarningsSection(List<String> advertencias) {
    final filtered = advertencias;
    if (filtered.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: ExpansionTile(
        title: const Text(
          'Sobre estos datos y advertencias',
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
        ),
        leading: const Icon(Icons.info_outline, size: 20),
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Fuente: Open-Meteo ERA5 (reanálisis 0.25°, precipitation_sum en mm, licencia CC BY 4.0). Base de referencia 1991–2020.',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                ...filtered.map(
                  (adv) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 2),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('• ',
                            style: TextStyle(fontWeight: FontWeight.bold)),
                        Expanded(
                          child: Text(
                            adv,
                            style: const TextStyle(
                                fontSize: 14, color: Colors.black87),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
