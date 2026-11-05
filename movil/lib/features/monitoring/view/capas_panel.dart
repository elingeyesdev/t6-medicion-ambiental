import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';

import '../../../core/maps/safe_overlay_image.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../model/capas_models.dart';
import '../model/capas_overlay_state.dart';
import '../model/capas_repository.dart';
import '../model/raster_decode.dart';
import 'capas_leyenda.dart';

/// Capas raster GFS: fechas, reproducción y comparación A/B.
/// Misma cáscara visual que el filtro de Sudamérica (ExpansionTile suave).
class CapasPanel extends StatefulWidget {
  const CapasPanel({
    required this.api,
    required this.onOverlayChanged,
    this.onDivisionChanged,
    super.key,
  });

  final GeoCruzApiClient api;
  final void Function(CapasOverlayState state) onOverlayChanged;
  final ValueChanged<double>? onDivisionChanged;

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
  bool _animarViento = true;
  String? _capaA;
  String? _capaB;
  String? _fechaA;
  String? _fechaB;
  double _opacity = 0.65;
  double _division = 0.5;
  final Map<String, CuadroRasterDecoded> _cache = {};
  int _paintGeneration = 0;
  int _playGeneration = 0;
  CapasOverlayState _lastState = CapasOverlayState.empty;

  @override
  void initState() {
    super.initState();
    _repository = CapasRepository(widget.api);
    _load();
  }

  @override
  void dispose() {
    _playing = false;
    _playGeneration++;
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
        // El panel y la capa quedan apagados al entrar; el usuario los activa.
        _visible = false;
      });
      _emit(CapasOverlayState.empty);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
      _emit(CapasOverlayState.empty);
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

  Future<CuadroRasterDecoded> _decodedFor(CapaCuadro cuadro) async {
    final cached = _cache[cuadro.id];
    if (cached != null) return cached;
    final bytes = await _repository.loadPng(cuadro);
    final decoded = await decodificarYColorearCuadro(cuadro, bytes);
    _cache[cuadro.id] = decoded;
    while (_cache.length > 4) {
      _cache.remove(_cache.keys.first);
    }
    return decoded;
  }

  CapaCanalSample _sampleOf(CapaVariable variable, CapaCuadro cuadro, Uint8List pixels) {
    return CapaCanalSample(
      cuadro: cuadro,
      pixels: pixels,
      nombre: variable.nombre,
      unidad: variable.unidad,
    );
  }

  Future<void> _refreshOverlays() async {
    final generation = ++_paintGeneration;
    if (!_visible) {
      _emit(CapasOverlayState.empty);
      return;
    }
    final variableA = _variable(_capaA);
    final a = _cuadro(_capaA, _fechaA);
    if (variableA == null || a == null) {
      _emit(CapasOverlayState.empty);
      return;
    }
    try {
      final decodedA = await _decodedFor(a);
      if (!mounted || generation != _paintGeneration) return;
      OverlayImage? layerB;
      CapaCanalSample? sampleB;
      if (_comparing) {
        final variableB = _variable(_capaB);
        final b = _cuadro(_capaB, _fechaB);
        if (variableB != null && b != null) {
          final decodedB = await _decodedFor(b);
          if (!mounted || generation != _paintGeneration) return;
          layerB = SafeOverlayImage(
            bounds: b.bounds,
            opacity: _opacity,
            imageProvider: MemoryImage(decodedB.coloredPng),
          );
          sampleB = _sampleOf(variableB, b, decodedB.pixels);
        }
      }
      final sampleA = _sampleOf(variableA, a, decodedA.pixels);
      _emit(
        CapasOverlayState(
          layerA: SafeOverlayImage(
            bounds: a.bounds,
            opacity: _opacity,
            imageProvider: MemoryImage(decodedA.coloredPng),
          ),
          layerB: layerB,
          comparing: _comparing && layerB != null,
          division: _division,
          visible: true,
          sampleA: sampleA,
          sampleB: sampleB,
          animarViento: _animarViento,
          opacity: _opacity,
        ),
      );
    } catch (_) {
      if (!mounted || generation != _paintGeneration) return;
      _emit(CapasOverlayState.empty);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo cargar la capa raster.')),
      );
    }
  }

  void _emit(CapasOverlayState state) {
    _lastState = state;
    widget.onOverlayChanged(state);
  }

  void _setDivision(double value) {
    final next = value.clamp(0.05, 0.95);
    setState(() => _division = next);
    widget.onDivisionChanged?.call(next);
    if (_lastState.hasContent) {
      _emit(_lastState.copyWith(division: next));
    }
  }

  /// Alinea la posición del divisor con el mapa (una sola fuente de verdad).
  void syncDivision(double value) {
    if (!mounted) return;
    final next = value.clamp(0.05, 0.95);
    if ((next - _division).abs() < 0.0005) return;
    setState(() => _division = next);
    if (_lastState.hasContent) {
      _lastState = _lastState.copyWith(division: next);
    }
  }

  void _togglePlay() {
    if (_playing) {
      _playGeneration++;
      setState(() => _playing = false);
      return;
    }
    final cuadros = _variable(_capaA)?.ordenados ?? const [];
    if (cuadros.length < 2) return;
    final runId = ++_playGeneration;
    setState(() => _playing = true);
    unawaited(_playLoop(runId));
  }

  /// Espera cada cuadro antes del siguiente. [runId] cancela recorridos viejos.
  Future<void> _playLoop(int runId) async {
    while (mounted && _playing && runId == _playGeneration) {
      final list = _variable(_capaA)?.ordenados ?? const [];
      if (list.length < 2) {
        if (runId == _playGeneration) setState(() => _playing = false);
        return;
      }
      final index = list.indexWhere((c) => c.id == _fechaA);
      final next = index < 0 || index >= list.length - 1 ? 0 : index + 1;
      if (runId != _playGeneration) return;
      setState(() => _fechaA = list[next].id);
      await _refreshOverlays();
      if (!mounted || !_playing || runId != _playGeneration) return;
      await Future<void>.delayed(const Duration(milliseconds: 1400));
    }
  }

  void _step(int delta) {
    final list = _variable(_capaA)?.ordenados ?? const [];
    if (list.isEmpty) return;
    final index = list.indexWhere((c) => c.id == _fechaA);
    final next = (index < 0 ? 0 : index + delta).clamp(0, list.length - 1);
    setState(() => _fechaA = list[next].id);
    _refreshOverlays();
  }

  String _labelFecha(CapaCuadro? cuadro) {
    if (cuadro == null || cuadro.instante.isEmpty) return 'Sin fecha';
    final dt = DateTime.tryParse(cuadro.instante);
    if (dt == null) return cuadro.instante;
    final fmt = DateFormat('dd/MM HH:mm');
    return '${fmt.format(dt.toUtc().subtract(const Duration(hours: 4)))} · Bolivia';
  }

  bool get _capaEsViento {
    final a = _cuadro(_capaA, _fechaA);
    final b = _comparing ? _cuadro(_capaB, _fechaB) : null;
    return esCodificacionViento(a?.codificacion) ||
        esCodificacionViento(b?.codificacion);
  }

  Widget _shell({
    required String subtitle,
    required List<Widget> children,
    bool initiallyExpanded = false,
  }) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          // Sin PageStorage: si no, recuerda “abierto” entre visitas al Mapa.
          initiallyExpanded: initiallyExpanded,
          shape: const Border(),
          collapsedShape: const Border(),
          tilePadding: const EdgeInsets.symmetric(horizontal: 14),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          leading: Icon(Icons.layers_outlined, color: scheme.primary),
          title: Text(
            'Capas del ambiente',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          subtitle: Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis),
          children: children,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return _shell(
        subtitle: 'Cargando catálogo…',
        children: const [
          Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Center(
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          ),
        ],
      );
    }

    final disponible = _catalog?.disponible == true;
    if (_error != null || !disponible) {
      final detail = _error is GeoCruzApiException
          ? (_error as GeoCruzApiException).message
          : 'Sin imágenes GFS publicadas. Activa capas cuando el servidor las tenga.';
      return _shell(
        subtitle: 'No disponibles ahora',
        children: [
          Text(detail, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Reintentar'),
            ),
          ),
        ],
      );
    }

    final variables = _catalog!.capas.where((c) => c.cuadros.isNotEmpty).toList();
    final cuadrosA = _variable(_capaA)?.ordenados ?? const [];
    final cuadrosB = _variable(_capaB)?.ordenados ?? const [];
    final maxIndex = (cuadrosA.length - 1).clamp(0, 9999);
    final indexA = cuadrosA.isEmpty
        ? 0
        : cuadrosA.indexWhere((c) => c.id == _fechaA).clamp(0, maxIndex);
    final variableA = _variable(_capaA);

    return _shell(
      initiallyExpanded: false,
      subtitle: _visible
          ? '${variableA?.nombre ?? 'Capa'} · ${_labelFecha(_cuadro(_capaA, _fechaA))}'
          : 'Lluvia, temperatura, viento…',
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Mostrar sobre el mapa'),
          value: _visible,
          onChanged: (value) async {
            setState(() => _visible = value);
            await _refreshOverlays();
          },
        ),
        DropdownButtonFormField<String>(
          key: ValueKey('capa-a-$_capaA'),
          initialValue: _capaA,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Capa',
            border: OutlineInputBorder(),
            isDense: true,
          ),
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
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          key: ValueKey('fecha-a-$_fechaA-${cuadrosA.length}'),
          initialValue: _fechaA,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Momento · Bolivia',
            border: OutlineInputBorder(),
            isDense: true,
          ),
          items: [
            for (final cuadro in cuadrosA)
              DropdownMenuItem(
                value: cuadro.id,
                child: Text(_labelFecha(cuadro)),
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
          Text('Recorrer fechas', style: Theme.of(context).textTheme.labelLarge),
          Slider(
            value: indexA.toDouble(),
            min: 0,
            max: maxIndex.toDouble(),
            divisions: maxIndex > 0 ? maxIndex : null,
            label: _labelFecha(cuadrosA[indexA]),
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
                icon: const Icon(Icons.skip_previous_rounded),
              ),
              FilledButton.tonalIcon(
                onPressed: !_visible ? null : _togglePlay,
                icon: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded),
                label: Text(_playing ? 'Pausar' : 'Reproducir'),
              ),
              IconButton(
                tooltip: 'Siguiente',
                onPressed: !_visible || indexA >= maxIndex ? null : () => _step(1),
                icon: const Icon(Icons.skip_next_rounded),
              ),
            ],
          ),
        ],
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Comparar capas o momentos'),
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
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Capa B',
              border: OutlineInputBorder(),
              isDense: true,
            ),
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
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            key: ValueKey('fecha-b-$_fechaB-${cuadrosB.length}'),
            initialValue: _fechaB,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Momento B · Bolivia',
              border: OutlineInputBorder(),
              isDense: true,
            ),
            items: [
              for (final cuadro in cuadrosB)
                DropdownMenuItem(
                  value: cuadro.id,
                  child: Text(_labelFecha(cuadro)),
                ),
            ],
            onChanged: (value) async {
              setState(() => _fechaB = value);
              await _refreshOverlays();
            },
          ),
          Text(
            '${(_division * 100).round()} % A · ${((1 - _division) * 100).round()} % B',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          Slider(
            value: _division,
            onChanged: _setDivision,
          ),
          Text(
            'A izquierda · B derecha · arrastra la línea en el mapa',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
        if (_capaEsViento)
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Animar viento'),
            subtitle: const Text('Partículas sobre el mapa'),
            value: _animarViento,
            onChanged: !_visible
                ? null
                : (value) async {
                    setState(() => _animarViento = value);
                    if (_lastState.hasContent) {
                      _emit(_lastState.copyWith(animarViento: value));
                    } else {
                      await _refreshOverlays();
                    }
                  },
          ),
        Text(
          'Opacidad ${(_opacity * 100).round()} %',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        Slider(
          value: _opacity,
          min: 0.1,
          max: 1,
          onChanged: !_visible
              ? null
              : (value) => setState(() => _opacity = value),
          onChangeEnd: (_) => _refreshOverlays(),
        ),
        if (_visible && variableA != null) ...[
          const SizedBox(height: 8),
          CapasLeyendaBar(
            capaId: variableA.id,
            nombre: 'A · ${variableA.nombre}',
          ),
          if (_comparing && _variable(_capaB) != null) ...[
            const SizedBox(height: 10),
            CapasLeyendaBar(
              capaId: _variable(_capaB)!.id,
              nombre: 'B · ${_variable(_capaB)!.nombre}',
            ),
          ],
        ],
        const SizedBox(height: 6),
        Text(
          'Estimado GFS · transparente = sin dato',
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }
}
