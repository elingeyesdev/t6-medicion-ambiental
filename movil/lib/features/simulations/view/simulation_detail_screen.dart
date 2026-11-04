import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/uikits.dart';
import '../model/simulation_models.dart';
import '../model/simulation_repository.dart';
import 'simulation_disclaimer.dart';

class SimulationDetailScreen extends StatefulWidget {
  const SimulationDetailScreen({
    required this.api,
    required this.simulationId,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final String simulationId;
  final VoidCallback onUnauthorized;

  @override
  State<SimulationDetailScreen> createState() => _SimulationDetailScreenState();
}

class _SimulationDetailScreenState extends State<SimulationDetailScreen> {
  late final SimulationRepository _repository;
  SimulationDetail? _detail;
  bool _loading = true;
  bool _acting = false;
  String? _error;
  String? _message;

  @override
  void initState() {
    super.initState();
    _repository = SimulationRepository(widget.api);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final detail = await _repository.get(widget.simulationId);
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _loading = false;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _loading = false;
        _error = error.statusCode == 403
            ? 'No tienes permiso para ver esta simulación.'
            : error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'No se pudo cargar el detalle de la simulación.';
      });
    }
  }

  Future<void> _runAction(String action, {int? position}) async {
    final current = _detail;
    if (current == null || _acting) return;
    setState(() {
      _acting = true;
      _error = null;
      _message = null;
    });
    try {
      final next = await _repository.action(
        id: current.id,
        action: action,
        revision: current.revision,
        position: position ?? current.position,
      );
      if (!mounted) return;
      setState(() {
        _detail = next;
        _acting = false;
        _message = 'Acción «$action» aplicada.';
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _acting = false;
        _error = error.message;
      });
      if (error.statusCode == 409) {
        await _load();
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _acting = false;
        _error = 'No se pudo ejecutar la acción.';
      });
    }
  }

  Future<void> _step(int delta) async {
    final current = _detail;
    if (current == null || !current.canStep) return;
    final next = (current.position + delta).clamp(0, current.lastIndex);
    if (next == current.position) return;
    await _runAction('posicionar', position: next);
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    return Scaffold(
      appBar: AppBar(
        title: Text(detail?.name ?? 'Simulación'),
        actions: [
          IconButton(
            tooltip: 'Acerca de simulaciones',
            onPressed: () => showSimulationDisclaimer(context),
            icon: const Icon(Icons.info_outline),
          ),
          IconButton(
            tooltip: 'Actualizar',
            onPressed: _loading || _acting ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading && detail == null
          ? const Center(child: CircularProgressIndicator())
          : detail == null
              ? Padding(
                  padding: const EdgeInsets.all(20),
                  child: UiKitSectionCard(
                    title: 'No disponible',
                    icon: Icons.error_outline,
                    child: Text(_error ?? 'No se encontró la simulación.'),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    children: [
                      if (_error != null || _message != null) ...[
                        UiKitSectionCard(
                          title: _error != null ? 'Error' : 'Listo',
                          icon: _error != null
                              ? Icons.error_outline
                              : Icons.check_circle_outline,
                          child: Text(_error ?? _message!),
                        ),
                        const SizedBox(height: 12),
                      ],
                      UiKitSectionCard(
                        title: detail.name,
                        subtitle: simulationStatusLabel(detail.status),
                        icon: Icons.science_outlined,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(detail.configSummary),
                            const SizedBox(height: 8),
                            Text(
                              'Posición ${detail.position} / ${detail.lastIndex} · revisión ${detail.revision}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      UiKitSectionCard(
                        title: 'Controles',
                        subtitle: 'Requiere revisión actual del servidor',
                        icon: Icons.play_circle_outline,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                if (detail.canPlay)
                                  FilledButton.icon(
                                    onPressed: _acting
                                        ? null
                                        : () => _runAction('iniciar'),
                                    icon: const Icon(Icons.play_arrow),
                                    label: const Text('Reproducir'),
                                  ),
                                if (detail.canPause)
                                  FilledButton.tonalIcon(
                                    onPressed: _acting
                                        ? null
                                        : () => _runAction('pausar'),
                                    icon: const Icon(Icons.pause),
                                    label: const Text('Pausar'),
                                  ),
                                if (detail.canStop)
                                  OutlinedButton.icon(
                                    onPressed: _acting
                                        ? null
                                        : () => _runAction('detener'),
                                    icon: const Icon(Icons.stop),
                                    label: const Text('Detener'),
                                  ),
                                if (detail.canRestart)
                                  OutlinedButton.icon(
                                    onPressed: _acting
                                        ? null
                                        : () => _runAction('reiniciar'),
                                    icon: const Icon(Icons.restart_alt),
                                    label: const Text('Reiniciar'),
                                  ),
                              ],
                            ),
                            if (detail.canStep) ...[
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: _acting
                                          ? null
                                          : () => _step(-1),
                                      icon: const Icon(Icons.skip_previous),
                                      label: const Text('Paso anterior'),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: _acting
                                          ? null
                                          : () => _step(1),
                                      icon: const Icon(Icons.skip_next),
                                      label: const Text('Paso siguiente'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                            if (_acting) ...[
                              const SizedBox(height: 12),
                              const LinearProgressIndicator(),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}
