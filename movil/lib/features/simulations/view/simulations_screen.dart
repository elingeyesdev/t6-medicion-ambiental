import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/uikits.dart';
import '../../auth/model/auth_repository.dart';
import '../model/simulation_models.dart';
import '../model/simulation_repository.dart';
import 'simulation_detail_screen.dart';
import 'simulation_disclaimer.dart';

class SimulationsScreen extends StatefulWidget {
  const SimulationsScreen({
    required this.api,
    required this.user,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final GeoSurUser user;
  final VoidCallback onUnauthorized;

  @override
  State<SimulationsScreen> createState() => _SimulationsScreenState();
}

class _SimulationsScreenState extends State<SimulationsScreen> {
  late final SimulationRepository _repository;
  final List<SimulationSummary> _items = [];
  int _page = 0;
  int _lastPage = 1;
  bool _loading = false;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repository = SimulationRepository(widget.api);
    if (widget.user.canManageSimulations) {
      _load(reset: true);
    }
  }

  Future<void> _load({required bool reset}) async {
    if (!widget.user.canManageSimulations) return;
    if (_loading || _loadingMore) return;
    final nextPage = reset ? 1 : _page + 1;
    if (!reset && nextPage > _lastPage) return;

    setState(() {
      if (reset) {
        _loading = true;
        _error = null;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final page = await _repository.list(page: nextPage);
      if (!mounted) return;
      setState(() {
        if (reset) _items.clear();
        _items.addAll(page.items);
        _page = page.page;
        _lastPage = page.lastPage;
        _loading = false;
        _loadingMore = false;
        _error = null;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = error.statusCode == 403
            ? 'No tienes permiso para gestionar simulaciones.'
            : error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = 'No se pudo cargar el listado de simulaciones.';
      });
    }
  }

  Future<void> _openDetail(SimulationSummary item) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SimulationDetailScreen(
          api: widget.api,
          simulationId: item.id,
          onUnauthorized: widget.onUnauthorized,
        ),
      ),
    );
    if (mounted) _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Simulaciones'),
        actions: [
          IconButton(
            tooltip: 'Acerca de simulaciones',
            onPressed: () => showSimulationDisclaimer(context),
            icon: const Icon(Icons.info_outline),
          ),
          if (widget.user.canManageSimulations)
            IconButton(
              tooltip: 'Actualizar',
              onPressed: _loading ? null : () => _load(reset: true),
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
      body: !widget.user.canManageSimulations
          ? const Padding(
              padding: EdgeInsets.all(20),
              child: UiKitSectionCard(
                title: 'Simulaciones bloqueadas',
                subtitle: 'Acceso restringido',
                icon: Icons.lock_outline,
                child: Text(
                  'Tu cuenta no tiene el permiso «simulaciones.gestionar». '
                  'Pide a un administrador que te habilite esta función.',
                ),
              ),
            )
          : _loading && _items.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: () => _load(reset: true),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    children: [
                      if (_error != null) ...[
                        UiKitSectionCard(
                          title: 'No se pudo cargar',
                          icon: Icons.error_outline,
                          child: Text(_error!),
                        ),
                        const SizedBox(height: 12),
                      ],
                      if (_items.isEmpty && _error == null)
                        const UiKitSectionCard(
                          title: 'Sin simulaciones',
                          subtitle:
                              'Aún no hay sesiones propias. Crea una desde la web GeoSur.',
                          icon: Icons.science_outlined,
                          child: Text(
                            'Cuando prepares un escenario, aparecerá aquí para reproducirlo.',
                          ),
                        )
                      else
                        for (final item in _items) ...[
                          Material(
                            color: Colors.transparent,
                            child: InkWell(
                              borderRadius: BorderRadius.circular(20),
                              onTap: () => _openDetail(item),
                              child: UiKitSectionCard(
                                title: item.name,
                                subtitle:
                                    '${simulationStatusLabel(item.status)}'
                                    '${item.createdAt != null ? ' · ${BoliviaTime.dateTime(item.createdAt!)}' : ''}',
                                icon: Icons.science_outlined,
                                child: Align(
                                  alignment: Alignment.centerRight,
                                  child: Icon(
                                    Icons.chevron_right,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurfaceVariant,
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                        ],
                      if (_page < _lastPage) ...[
                        const SizedBox(height: 8),
                        OutlinedButton(
                          onPressed: _loadingMore
                              ? null
                              : () => _load(reset: false),
                          child: _loadingMore
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Text('Cargar más'),
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }
}
