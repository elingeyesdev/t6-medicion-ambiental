import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../model/capas_models.dart';
import '../model/capas_repository.dart';
import '../model/raster_decode.dart';

/// Controles de capas raster GFS: recorrido de fechas y comparación A/B.
class CapasPanel extends StatefulWidget {
  const CapasPanel({
    required this.api,
    required this.onOverlayChanged,
    super.key,
  });

  final GeoCruzApiClient api;
  final void Function(List<OverlayImage> overlays) onOverlayChanged;

  @override
  State<CapasPanel> createState() => CapasPanelState();
}

class CapasPanelState extends State<CapasPanel> {
  late final CapasRepository _repository;
  CapasCatalog? _catalog;
  Object? _error;
  bool _loading = true;
  bool _visible = false;
  bool _comparing = false;
  bool _playing = false;
  String? _capaA;
  String? _capaB;
  String? _fechaA;
  String? _fechaB;
  double _opacity = 0.65;
  double _division = 0.5;
  Timer? _playTimer;
  final Map<String, Uint8List> _cache = {};
  int _paintGeneration = 0;

  @override
  void initState() {
    super.initState();
    _repository = CapasRepository(widget.api);
    _load();
  }

  @override
  void dispose() {
    _playTimer?.cancel();
    _paintGeneration++;
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final catalog = await _repository.loadCatalog();
      if (!mounted) return;
      final first = catalog.capas.where((c) => c.cuadros.isNotEmpty).firstOrNull;
      setState(() {
        _catalog = catalog;
        _loading = false;
        _capaA = first?.id;
        _fechaA = first?.ordenados.lastOrNull?.id;
        _capaB = first?.id;
        _fechaB = first != null && first.ordenados.length > 1
            ? first.ordenados.first.id
            : first?.ordenados.lastOrNull?.id;
      });
      if (_visible) await _refreshOverlays();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
      widget.onOverlayChanged(const []);
    }
  }

  CapaVariable? _variable(String? id) {
    final catalog = _catalog;
    if (catalog == null || id == null) return null;
    for (final capa in catalog.capas) {
      if (capa.id == id) return capa;
    }
    return null;
  }

  CapaCuadro? _cuadro(String? capaId, String? cuadroId) {
    final variable = _variable(capaId);
    if (variable == null || cuadroId == null) return null;
    for (final cuadro in variable.cuadros) {
      if (cuadro.id == cuadroId) return cuadro;
    }
    return null;
  }

  Future<Uint8List> _pngFor(CapaCuadro cuadro) async {
    final cached = _cache[cuadro.id];
    if (cached != null) return cached;
    final bytes = await _repository.loadPng(cuadro);
    final colored = await colorearCuadroPng(cuadro, bytes);
    _cache[cuadro.id] = colored;
    while (_cache.length > 4) {
      _cache.remove(_cache.keys.first);
    }
    return colored;
  }

  Future<void> _refreshOverlays() async {
    final generation = ++_paintGeneration;
    if (!_visible) {
      widget.onOverlayChanged(const []);
      return;
    }
    final a = _cuadro(_capaA, _fechaA);
    if (a == null) {
      widget.onOverlayChanged(const []);
      return;
    }
    try {
      final pngA = await _pngFor(a);
      if (!mounted || generation != _paintGeneration) return;
      final overlays = <OverlayImage>[
        OverlayImage(
          bounds: a.bounds,
          opacity: _comparing ? _opacity * _division : _opacity,
          imageProvider: MemoryImage(pngA),
        ),
      ];
      if (_comparing) {
        final b = _cuadro(_capaB, _fechaB);
        if (b != null) {
          final pngB = await _pngFor(b);
          if (!mounted || generation != _paintGeneration) return;
          overlays.add(
            OverlayImage(
              bounds: b.bounds,
              opacity: _opacity * (1 - _division),
              imageProvider: MemoryImage(pngB),
            ),
          );
        }
      }
      widget.onOverlayChanged(overlays);
    } catch (_) {
      if (!mounted || generation != _paintGeneration) return;
      widget.onOverlayChanged(const []);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo cargar la capa raster.')),
      );
    }
  }

  void _togglePlay() {
    if (_playing) {
      _playTimer?.cancel();
      setState(() => _playing = false);
      return;
    }
    final cuadros = _variable(_capaA)?.ordenados ?? const [];
    if (cuadros.length < 2) return;
    setState(() => _playing = true);
    _playTimer = Timer.periodic(const Duration(seconds: 1), (_) async {
      final list = _variable(_capaA)?.ordenados ?? const [];
      if (list.isEmpty) return;
      final index = list.indexWhere((c) => c.id == _fechaA);
      final next = index < 0 || index >= list.length - 1 ? 0 : index + 1;
      setState(() => _fechaA = list[next].id);
      await _refreshOverlays();
    });
  }

  void _step(int delta) {
    final list = _variable(_capaA)?.ordenados ?? const [];
    if (list.isEmpty) return;
    final index = list.indexWhere((c) => c.id == _fechaA);
    final next = (index < 0 ? 0 : index + delta).clamp(0, list.length - 1);
    setState(() => _fechaA = list[next].id);
    _refreshOverlays();
  }

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd/MM HH:mm');
    String labelFecha(CapaCuadro? cuadro) {
      if (cuadro == null || cuadro.instante.isEmpty) return 'Sin fecha';
      final dt = DateTime.tryParse(cuadro.instante);
      if (dt == null) return cuadro.instante;
      return '${fmt.format(dt.toUtc().subtract(const Duration(hours: 4)))} · Bolivia';
    }

    if (_loading) {
      return Card(
        child: ListTile(
          leading: const SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          title: const Text('Capas del ambiente'),
          subtitle: const Text('Cargando catálogo GFS…'),
        ),
      );
    }

    if (_error != null || _catalog == null || !_catalog!.disponible) {
      final detail = _error is GeoCruzApiException
          ? (_error as GeoCruzApiException).message
          : (_catalog?.estado == 'sin_insumos'
              ? 'Aún no hay imágenes raster publicadas en el servidor.'
              : 'No se pudo abrir el catálogo de capas.');
      return Card(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.layers_outlined,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Capas del ambiente',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(detail, style: Theme.of(context).textTheme.bodyMedium),
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: _load,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Reintentar'),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final catalog = _catalog!;

    final variables = catalog.capas.where((c) => c.cuadros.isNotEmpty).toList();
    final cuadrosA = _variable(_capaA)?.ordenados ?? const [];
    final cuadrosB = _variable(_capaB)?.ordenados ?? const [];
    final maxIndex = (cuadrosA.length - 1).clamp(0, 9999);
    final indexA = cuadrosA.isEmpty
        ? 0
        : cuadrosA.indexWhere((c) => c.id == _fechaA).clamp(0, maxIndex);

    return Card(
      child: ExpansionTile(
        key: const PageStorageKey<String>('monitoring-capas'),
        initiallyExpanded: false,
        tilePadding: const EdgeInsets.symmetric(horizontal: 14),
        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
        leading: const Icon(Icons.layers_outlined),
        title: const Text('Capas del ambiente'),
        subtitle: Text(
          _visible
              ? '${_variable(_capaA)?.nombre ?? 'Capa'} · ${labelFecha(_cuadro(_capaA, _fechaA))}'
              : 'Lluvia, nieve, viento y más (GFS)',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        children: [
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Mostrar capa'),
            value: _visible,
            onChanged: (value) async {
              setState(() => _visible = value);
              await _refreshOverlays();
            },
          ),
          DropdownButtonFormField<String>(
            key: ValueKey('capa-a-$_capaA'),
            initialValue: _capaA,
            decoration: const InputDecoration(labelText: 'Capa A'),
            items: [
              for (final capa in variables)
                DropdownMenuItem(value: capa.id, child: Text(capa.nombre)),
            ],
            onChanged: !_visible
                ? null
                : (value) async {
                    final cuadros = _variable(value)?.ordenados ?? const [];
                    setState(() {
                      _capaA = value;
                      _fechaA = cuadros.lastOrNull?.id;
                    });
                    await _refreshOverlays();
                  },
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            key: ValueKey('fecha-a-$_fechaA-${cuadrosA.length}'),
            initialValue: _fechaA,
            decoration: const InputDecoration(labelText: 'Fecha de A · Bolivia'),
            items: [
              for (final cuadro in cuadrosA)
                DropdownMenuItem(
                  value: cuadro.id,
                  child: Text(labelFecha(cuadro)),
                ),
            ],
            onChanged: !_visible
                ? null
                : (value) async {
                    setState(() => _fechaA = value);
                    await _refreshOverlays();
                  },
          ),
          if (cuadrosA.length > 1) ...[
            const SizedBox(height: 8),
            Text('Recorrer imágenes', style: Theme.of(context).textTheme.labelLarge),
            Slider(
              value: indexA.toDouble(),
              min: 0,
              max: maxIndex.toDouble(),
              divisions: maxIndex > 0 ? maxIndex : null,
              label: labelFecha(cuadrosA[indexA]),
              onChanged: !_visible
                  ? null
                  : (value) {
                      setState(() => _fechaA = cuadrosA[value.round()].id);
                    },
              onChangeEnd: (_) => _refreshOverlays(),
            ),
            Row(
              children: [
                IconButton(
                  tooltip: 'Anterior',
                  onPressed: !_visible || indexA <= 0 ? null : () => _step(-1),
                  icon: const Icon(Icons.skip_previous),
                ),
                FilledButton.tonalIcon(
                  onPressed: !_visible ? null : _togglePlay,
                  icon: Icon(_playing ? Icons.pause : Icons.play_arrow),
                  label: Text(_playing ? 'Pausar' : 'Reproducir'),
                ),
                IconButton(
                  tooltip: 'Siguiente',
                  onPressed: !_visible || indexA >= maxIndex
                      ? null
                      : () => _step(1),
                  icon: const Icon(Icons.skip_next),
                ),
              ],
            ),
          ],
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Comparar capas o fechas'),
            value: _comparing,
            onChanged: !_visible
                ? null
                : (value) async {
                    setState(() => _comparing = value);
                    await _refreshOverlays();
                  },
          ),
          if (_comparing) ...[
            DropdownButtonFormField<String>(
              key: ValueKey('capa-b-$_capaB'),
              initialValue: _capaB,
              decoration: const InputDecoration(labelText: 'Capa B'),
              items: [
                for (final capa in variables)
                  DropdownMenuItem(value: capa.id, child: Text(capa.nombre)),
              ],
              onChanged: (value) async {
                final cuadros = _variable(value)?.ordenados ?? const [];
                setState(() {
                  _capaB = value;
                  _fechaB = cuadros.firstOrNull?.id ?? cuadros.lastOrNull?.id;
                });
                await _refreshOverlays();
              },
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              key: ValueKey('fecha-b-$_fechaB-${cuadrosB.length}'),
              initialValue: _fechaB,
              decoration: const InputDecoration(labelText: 'Fecha de B · Bolivia'),
              items: [
                for (final cuadro in cuadrosB)
                  DropdownMenuItem(
                    value: cuadro.id,
                    child: Text(labelFecha(cuadro)),
                  ),
              ],
              onChanged: (value) async {
                setState(() => _fechaB = value);
                await _refreshOverlays();
              },
            ),
            Text(
              '${(_division * 100).round()} % de A · ${((1 - _division) * 100).round()} % de B',
            ),
            Slider(
              value: _division,
              onChanged: (value) {
                setState(() => _division = value);
              },
              onChangeEnd: (_) => _refreshOverlays(),
            ),
          ],
          Text('Opacidad ${(_opacity * 100).round()} %'),
          Slider(
            value: _opacity,
            min: 0.1,
            max: 1,
            onChanged: !_visible
                ? null
                : (value) {
                    setState(() => _opacity = value);
                  },
            onChangeEnd: (_) => _refreshOverlays(),
          ),
          Text(
            'Estimado GFS · transparente = sin dato',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}
