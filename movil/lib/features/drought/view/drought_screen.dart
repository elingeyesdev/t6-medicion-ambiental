import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/maps/map_chrome.dart';
import '../../../core/maps/map_completo_actions.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../hotspots/model/hotspot_models.dart';
import '../model/drought_models.dart';
import '../model/drought_repository.dart';
import 'drought_detail_screen.dart';

class DroughtScreen extends StatefulWidget {
  DroughtScreen({
    required this.apiClient,
    String? initialMonth,
    this.showAppBar = true,
    this.repository,
    super.key,
  }) : initialMonth = initialMonth ?? DroughtFilters.defaultMesFinal();

  final GeoCruzApiClient apiClient;
  final String initialMonth;
  final bool showAppBar;
  final DroughtRepository? repository;

  @override
  State<DroughtScreen> createState() => _DroughtScreenState();
}

class _DroughtScreenState extends State<DroughtScreen>
    with AutomaticKeepAliveClientMixin {
  late final DroughtRepository _repository;
  final MapController _mapController = MapController();

  @override
  bool get wantKeepAlive => true;

  late DroughtFilters _filters;
  List<TerritoryOption> _territories = const [];
  DroughtResponse? _response;
  DroughtSample? _selectedSample;

  bool _loading = false;
  bool _catalogLoading = false;
  String? _errorMessage;
  bool _mapReady = false;
  int _request = 0;
  MapBaseStyle _mapStyle = MapBaseStyle.relieve;
  LatLng? _searchPin;
  String? _searchPinLabel;

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
        _errorMessage = _friendlyDroughtError(e);
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
      _mapController.move(LatLng(datos.first.lat, datos.first.lon), 7.5);
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
      builder: (sheetContext) {
        final place = _territoryName(sample.territorioContexto);
        final category = sample.spi.categoryInfo;
        final rainfall = sample.precipitacionAcumuladaMm != null
            ? '${sample.precipitacionAcumuladaMm!.toStringAsFixed(1)} mm'
            : 'Sin dato';
        final spiText = sample.spi.valor != null
            ? 'SPI ${sample.spi.valor!.toStringAsFixed(2)}'
            : 'Sin índice';

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
              Text(
                'Trimestre hasta ${_filters.mesFinal}',
                style: Theme.of(sheetContext).textTheme.bodyMedium,
              ),
              Text(
                '${category.label} · $spiText · Lluvia $rainfall',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Reanálisis climático · Open-Meteo ERA5',
                style: Theme.of(sheetContext).textTheme.bodySmall,
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () {
                    Navigator.of(sheetContext).pop();
                    _openSampleDetails(sample);
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

  void _openSampleDetails(DroughtSample sample) {
    final resp = _response;
    if (resp == null) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => DroughtDetailScreen(
          sample: sample,
          response: resp,
          territoryName: _territoryName,
        ),
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
                .where(
                  (t) =>
                      t.type == 'municipio' &&
                      (selectedProv == null || t.parentId == selectedProv),
                )
                .toList();

            return SafeArea(
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(ctx).size.height * 0.85,
                ),
                child: Padding(
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
                        const SizedBox(height: 8),
                        TextFormField(
                          initialValue: selectedMonth,
                          decoration: const InputDecoration(
                            labelText: 'Mes final (AAAA-MM)',
                            hintText: 'Ej. 2024-09',
                            helperText:
                                'Piloto disponible: 2024-09 · ventana de 3 meses (UTC)',
                            prefixIcon: Icon(Icons.calendar_month_outlined),
                            border: OutlineInputBorder(),
                          ),
                          onChanged: (val) => selectedMonth = val.trim(),
                        ),
                        const SizedBox(height: 16),
                        DropdownButtonFormField<String?>(
                          key: const Key('drought-province'),
                          initialValue: selectedProv,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Provincia',
                            border: OutlineInputBorder(),
                            contentPadding: EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 12,
                            ),
                            isDense: true,
                          ),
                          items: [
                            const DropdownMenuItem<String?>(
                              value: null,
                              child: Text('Todas las provincias'),
                            ),
                            ...provinces.map(
                              (p) => DropdownMenuItem<String?>(
                                value: p.identifier,
                                child: Text(
                                  p.name,
                                  overflow: TextOverflow.ellipsis,
                                ),
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
                          key: ValueKey(
                            'drought-municipality-${selectedProv ?? 'all'}',
                          ),
                          initialValue: selectedMun,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Municipio',
                            border: OutlineInputBorder(),
                            contentPadding: EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 12,
                            ),
                            isDense: true,
                          ),
                          items: [
                            const DropdownMenuItem<String?>(
                              value: null,
                              child: Text('Todos los municipios'),
                            ),
                            ...munList.map(
                              (m) => DropdownMenuItem<String?>(
                                value: m.identifier,
                                child: Text(
                                  m.name,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ),
                          ],
                          onChanged: (val) {
                            setSheetState(() => selectedMun = val);
                          },
                        ),
                        const SizedBox(height: 24),
                        if (validationError != null)
                          Text(
                            validationError!,
                            style: TextStyle(
                              color: Theme.of(ctx).colorScheme.error,
                            ),
                          ),
                        FilledButton.icon(
                          icon: const Icon(Icons.check),
                          label: const Text('Aplicar filtros'),
                          onPressed: () {
                            final next = DroughtFilters(
                              mesFinal: selectedMonth,
                              provincia: selectedProv,
                              municipio: selectedMun,
                            );
                            try {
                              next.validate(DateTime.now().toUtc());
                            } on FormatException catch (error) {
                              setSheetState(
                                () => validationError = error.message,
                              );
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
    super.build(context);
    final response = _response;
    final summary = response?.resumen;

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
        ? ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 30),
            children: [
              if (!widget.showAppBar) _buildTopControls(),
              const SizedBox(height: 24),
              const Center(
                child: Icon(
                  Icons.warning_amber_rounded,
                  size: 48,
                  color: Colors.orange,
                ),
              ),
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
          )
        : response == null
        ? const SizedBox.shrink()
        : Stack(
            fit: StackFit.expand,
            children: [
              _buildMapCard(response.datos),
              if (!widget.showAppBar)
                Positioned(
                  top: 10,
                  left: 10,
                  right: 58,
                  child: _buildTopControls(),
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
                            color: Theme.of(context).colorScheme.outlineVariant,
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      ),
                      _buildSummaryCard(summary),
                      _buildLegend(),
                      _buildSamplesList(response.datos),
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
          ...mapCompletoActions(
            onSearch: _openPlaceSearch,
            onWeather: _openWeather,
          ),
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

  LatLng _focusPoint() {
    if (_selectedSample != null) {
      return LatLng(_selectedSample!.lat, _selectedSample!.lon);
    }
    if (_searchPin != null) return _searchPin!;
    if (_mapReady) return _mapController.camera.center;
    return const LatLng(-17.8, -63.1);
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
    if (_mapReady) _mapController.move(point, 9);
  }

  Future<void> _openWeather() {
    final pin = _searchPinLabel?.trim();
    return openWeatherSheet(
      context: context,
      api: widget.apiClient,
      point: _focusPoint(),
      title: (pin != null && pin.isNotEmpty) ? pin : 'Tiempo en este lugar',
    );
  }

  Widget _buildTopControls() {
    final place = _filters.municipio != null
        ? _territoryName(_filters.municipio)
        : _filters.provincia != null
        ? _territoryName(_filters.provincia)
        : 'Santa Cruz';
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
                    place,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  Text(
                    'Mes final ${_filters.mesFinal} · 3 meses · ERA5',
                    style: TextStyle(fontSize: 14, color: Colors.black54),
                  ),
                ],
              ),
            ),
            IconButton.filledTonal(
              onPressed: _openPlaceSearch,
              icon: const Icon(Icons.search_rounded, size: 18),
              tooltip: 'Buscar lugar',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _openWeather,
              icon: const Icon(Icons.wb_cloudy_outlined, size: 18),
              tooltip: 'Tiempo',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _openFiltersDialog,
              icon: const Icon(Icons.tune_rounded, size: 18),
              tooltip: 'Filtros',
            ),
            const SizedBox(width: 4),
            IconButton.filledTonal(
              onPressed: _executeQuery,
              icon: const Icon(Icons.refresh, size: 18),
              tooltip: 'Recargar',
            ),
          ],
        ),
      ),
    );
  }

  String _friendlyDroughtError(GeoCruzApiException error) {
    final msg = error.message.toLowerCase();
    final vencidos =
        error.code == 'PERIODO_NO_DISPONIBLE' ||
        msg.contains('vencieron') ||
        msg.contains('vencida') ||
        msg.contains('caducad');
    if (vencidos) {
      return 'Los datos de sequías están vencidos. '
          'Hay que actualizarlos; cambiar el mes no lo resuelve.';
    }
    if (error.code == 'MUESTRAS_NO_DISPONIBLES' ||
        msg.contains('no hay resultados preparados') ||
        msg.contains('sequias:preparar') ||
        msg.contains('petición web')) {
      return 'No hay datos de sequías para ese mes. '
          'Prueba otro mes en Filtros (p. ej. 2024-09).';
    }
    return error.message;
  }

  Widget _buildSummaryCard(DroughtSummary? summary) {
    if (summary == null) return const SizedBox.shrink();
    final coverage = _response?.cobertura;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ExpansionTile(
        leading: const Icon(Icons.water_drop_outlined),
        title: const Text('Datos de la consulta'),
        subtitle: Text(
          '${summary.totalMuestras} ${summary.totalMuestras == 1 ? 'muestra' : 'muestras'} · ${coverage?.municipiosConDatos ?? 0} de ${coverage?.municipiosEnFiltro ?? 0} municipios',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
        children: [
          Row(
            children: [
              _metricItem(
                '${summary.totalMuestras}',
                'Evaluados',
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
                'Déficit (≤-1)',
                summary.muestrasConDeficit > 0
                    ? const Color(0xFFBC673B)
                    : Colors.black54,
              ),
            ],
          ),
        ],
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
            style: const TextStyle(fontSize: 14, color: Colors.black54),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  Widget _buildMapCard(List<DroughtSample> datos) {
    final markers = datos.map((sample) {
      final cat = sample.spi.categoryInfo;
      final isSelected = _selectedSample?.idMuestra == sample.idMuestra;
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
    final fitBounds = datos.length > 1
        ? LatLngBounds.fromPoints(
            datos.map((d) => LatLng(d.lat, d.lon)).toList(),
          )
        : null;
    return Stack(
      fit: StackFit.expand,
      children: [
        FlutterMap(
          key: ValueKey('drought-map-$_request-$_mapStyle'),
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
            onZoomIn: () {
              if (!_mapReady) return;
              _mapController.move(
                _mapController.camera.center,
                _mapController.camera.zoom + 1,
              );
            },
            onZoomOut: () {
              if (!_mapReady) return;
              _mapController.move(
                _mapController.camera.center,
                _mapController.camera.zoom - 1,
              );
            },
            onFit: () => _fitBounds(datos),
            onFullscreen: () => openFullscreenMap(
              context: context,
              title: 'Sequías',
              center: const LatLng(-17.8, -63.1),
              zoom: 7.2,
              markers: markers,
              fitBounds: fitBounds,
              initialStyle: _mapStyle,
            ),
          ),
        ),
      ],
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
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
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
                    child: const Icon(
                      Icons.water_drop,
                      size: 14,
                      color: Colors.white,
                    ),
                  ),
                  title: Text(
                    '${d.idMuestra} · ${_territoryName(d.territorioContexto)}',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
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
                  'Mostrando 10 de ${datos.length}. Toca el mapa para más detalle.',
                  style: const TextStyle(fontSize: 14, color: Colors.black54),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
