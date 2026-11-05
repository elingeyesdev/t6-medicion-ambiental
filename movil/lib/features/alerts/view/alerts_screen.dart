import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/uikits.dart';
import '../../auth/model/auth_repository.dart';
import '../model/alert_models.dart';
import '../model/alerts_repository.dart';
import '../model/aviso_local_notifications.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({
    required this.api,
    required this.user,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final GeoSurUser user;
  final VoidCallback onUnauthorized;

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen>
    with SingleTickerProviderStateMixin {
  late final AlertsRepository _repository;
  late final TabController _tabs;

  final List<AvisoAmbiental> _avisos = [];
  final List<AlertAmbiental> _alertas = [];
  UmbralesCatalog? _umbrales;

  int _avisosPage = 0;
  int _avisosLastPage = 1;
  int _sinLeer = 0;

  int _alertasPage = 0;
  int _alertasLastPage = 1;
  int _alertasTotal = 0;
  String? _severidadFiltro;

  bool _loadingAvisos = false;
  bool _loadingMoreAvisos = false;
  bool _loadingAlertas = false;
  bool _loadingMoreAlertas = false;
  bool _loadingUmbrales = false;
  final Set<String> _markingRead = {};
  final Set<String> _acknowledging = {};

  String? _avisosError;
  String? _alertasError;
  String? _umbralesError;

  @override
  void initState() {
    super.initState();
    _repository = AlertsRepository(widget.api);
    _tabs = TabController(length: 3, vsync: this);
    _tabs.addListener(_onTabChanged);
    _loadAvisos(reset: true);
  }

  void _onTabChanged() {
    if (_tabs.indexIsChanging) return;
    switch (_tabs.index) {
      case 0:
        if (_avisos.isEmpty && !_loadingAvisos) {
          _loadAvisos(reset: true);
        }
      case 1:
        if (_alertas.isEmpty && !_loadingAlertas) {
          _loadAlertas(reset: true);
        }
      case 2:
        if (_umbrales == null && !_loadingUmbrales) {
          _loadUmbrales();
        }
    }
  }

  @override
  void dispose() {
    _tabs.removeListener(_onTabChanged);
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _handleApiError(
    GeoCruzApiException error, {
    required void Function(String message) onMessage,
  }) async {
    if (!mounted) return;
    if (error.statusCode == 401) {
      widget.onUnauthorized();
      return;
    }
    onMessage(error.message);
  }

  Future<void> _loadAvisos({required bool reset}) async {
    if (_loadingAvisos || _loadingMoreAvisos) return;
    final nextPage = reset ? 1 : _avisosPage + 1;
    if (!reset && nextPage > _avisosLastPage) return;

    setState(() {
      if (reset) {
        _loadingAvisos = true;
        _avisosError = null;
      } else {
        _loadingMoreAvisos = true;
      }
    });

    try {
      final page = await _repository.listAvisos(page: nextPage);
      if (!mounted) return;
      setState(() {
        if (reset) _avisos.clear();
        _avisos.addAll(page.items);
        _avisosPage = page.page;
        _avisosLastPage = page.lastPage;
        _sinLeer = page.unread;
        _loadingAvisos = false;
        _loadingMoreAvisos = false;
      });
      if (reset) {
        await AvisoLocalNotifications.instance.notifyUnread(page.items);
      }
    } on GeoCruzApiException catch (error) {
      await _handleApiError(
        error,
        onMessage: (message) {
          if (!mounted) return;
          setState(() {
            _loadingAvisos = false;
            _loadingMoreAvisos = false;
            _avisosError = error.statusCode == 403
                ? 'No tienes permiso para consultar avisos.'
                : message;
          });
        },
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingAvisos = false;
        _loadingMoreAvisos = false;
        _avisosError = 'No se pudieron cargar tus avisos.';
      });
    }
  }

  Future<void> _loadAlertas({required bool reset}) async {
    if (_loadingAlertas || _loadingMoreAlertas) return;
    final nextPage = reset ? 1 : _alertasPage + 1;
    if (!reset && nextPage > _alertasLastPage) return;

    setState(() {
      if (reset) {
        _loadingAlertas = true;
        _alertasError = null;
      } else {
        _loadingMoreAlertas = true;
      }
    });

    try {
      final page = await _repository.listAlertas(
        page: nextPage,
        severidad: _severidadFiltro,
      );
      if (!mounted) return;
      setState(() {
        if (reset) _alertas.clear();
        _alertas.addAll(page.items);
        _alertasPage = page.page;
        _alertasLastPage = page.lastPage;
        _alertasTotal = page.total;
        _loadingAlertas = false;
        _loadingMoreAlertas = false;
      });
    } on GeoCruzApiException catch (error) {
      await _handleApiError(
        error,
        onMessage: (message) {
          if (!mounted) return;
          setState(() {
            _loadingAlertas = false;
            _loadingMoreAlertas = false;
            _alertasError = error.statusCode == 403
                ? 'No tienes permiso para consultar alertas.'
                : message;
          });
        },
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingAlertas = false;
        _loadingMoreAlertas = false;
        _alertasError = 'No se pudo cargar el listado de alertas.';
      });
    }
  }

  Future<void> _loadUmbrales() async {
    setState(() {
      _loadingUmbrales = true;
      _umbralesError = null;
    });
    try {
      final catalog = await _repository.umbrales();
      if (!mounted) return;
      setState(() {
        _umbrales = catalog;
        _loadingUmbrales = false;
      });
    } on GeoCruzApiException catch (error) {
      await _handleApiError(
        error,
        onMessage: (message) {
          if (!mounted) return;
          setState(() {
            _loadingUmbrales = false;
            _umbralesError = error.statusCode == 403
                ? 'No tienes permiso para consultar umbrales.'
                : message;
          });
        },
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingUmbrales = false;
        _umbralesError = 'No se pudo cargar el catálogo de umbrales.';
      });
    }
  }

  Future<void> _markRead(AvisoAmbiental aviso) async {
    if (aviso.leido || _markingRead.contains(aviso.id)) return;
    setState(() => _markingRead.add(aviso.id));
    try {
      await _repository.markAvisoRead(aviso.id);
      if (!mounted) return;
      setState(() {
        final index = _avisos.indexWhere((item) => item.id == aviso.id);
        if (index >= 0) {
          _avisos[index] = _avisos[index].copyWith(leido: true);
        }
        if (_sinLeer > 0) _sinLeer -= 1;
        _markingRead.remove(aviso.id);
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      setState(() => _markingRead.remove(aviso.id));
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _markingRead.remove(aviso.id));
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo marcar el aviso como leído.')),
      );
    }
  }

  Future<void> _acknowledge(AlertAmbiental alerta) async {
    if (!widget.user.canAcknowledgeAlerts ||
        alerta.reconocida ||
        _acknowledging.contains(alerta.id)) {
      return;
    }
    setState(() => _acknowledging.add(alerta.id));
    try {
      final updated = await _repository.acknowledge(alerta.id);
      if (!mounted) return;
      setState(() {
        final index = _alertas.indexWhere((item) => item.id == alerta.id);
        if (index >= 0) {
          _alertas[index] = updated;
        }
        _acknowledging.remove(alerta.id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Alerta reconocida.')),
      );
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      setState(() => _acknowledging.remove(alerta.id));
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.statusCode == 403
                ? 'No tienes permiso «alertas.gestionar» para reconocer alertas.'
                : error.message,
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _acknowledging.remove(alerta.id));
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo reconocer la alerta.')),
      );
    }
  }

  void _setSeveridad(String? value) {
    if (_severidadFiltro == value) return;
    setState(() => _severidadFiltro = value);
    _loadAlertas(reset: true);
  }

  Future<void> _refreshCurrent() {
    return switch (_tabs.index) {
      0 => _loadAvisos(reset: true),
      1 => _loadAlertas(reset: true),
      _ => _loadUmbrales(),
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Alertas / avisos'),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Mis avisos'),
                  if (_sinLeer > 0) ...[
                    const SizedBox(width: 8),
                    Badge(label: Text('$_sinLeer')),
                  ],
                ],
              ),
            ),
            const Tab(text: 'Alertas'),
            const Tab(text: 'Umbrales'),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Notificaciones del teléfono',
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              final enabled = await AvisoLocalNotifications.instance.enabled;
              final next = !enabled;
              await AvisoLocalNotifications.instance.setEnabled(next);
              messenger.showSnackBar(
                SnackBar(
                  content: Text(
                    next
                        ? 'Notificaciones del sistema activadas.'
                        : 'Notificaciones del sistema desactivadas.',
                  ),
                ),
              );
              if (next && mounted) {
                await AvisoLocalNotifications.instance.clearSeenCache();
                await _loadAvisos(reset: true);
              }
            },
            icon: const Icon(Icons.notifications_outlined),
          ),
          IconButton(
            tooltip: 'Actualizar',
            onPressed: _refreshCurrent,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _AvisosTab(
            loading: _loadingAvisos && _avisos.isEmpty,
            loadingMore: _loadingMoreAvisos,
            error: _avisosError,
            items: _avisos,
            unread: _sinLeer,
            hasMore: _avisosPage < _avisosLastPage,
            markingRead: _markingRead,
            onRefresh: () => _loadAvisos(reset: true),
            onLoadMore: () => _loadAvisos(reset: false),
            onMarkRead: _markRead,
          ),
          _AlertasTab(
            loading: _loadingAlertas && _alertas.isEmpty,
            loadingMore: _loadingMoreAlertas,
            error: _alertasError,
            items: _alertas,
            total: _alertasTotal,
            hasMore: _alertasPage < _alertasLastPage,
            severidad: _severidadFiltro,
            canAcknowledge: widget.user.canAcknowledgeAlerts,
            acknowledging: _acknowledging,
            onSeveridadChanged: _setSeveridad,
            onRefresh: () => _loadAlertas(reset: true),
            onLoadMore: () => _loadAlertas(reset: false),
            onAcknowledge: _acknowledge,
          ),
          _UmbralesTab(
            loading: _loadingUmbrales,
            error: _umbralesError,
            catalog: _umbrales,
            onRefresh: _loadUmbrales,
          ),
        ],
      ),
    );
  }
}

class _AvisosTab extends StatelessWidget {
  const _AvisosTab({
    required this.loading,
    required this.loadingMore,
    required this.error,
    required this.items,
    required this.unread,
    required this.hasMore,
    required this.markingRead,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onMarkRead,
  });

  final bool loading;
  final bool loadingMore;
  final String? error;
  final List<AvisoAmbiental> items;
  final int unread;
  final bool hasMore;
  final Set<String> markingRead;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<AvisoAmbiental> onMarkRead;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          UiKitSectionCard(
            title: 'Bandeja personal',
            subtitle: unread > 0
                ? '$unread sin leer'
                : 'Sin avisos pendientes',
            icon: Icons.notifications_outlined,
            child: Text(
              unread > 0
                  ? 'Marca como leídos los avisos que ya revisaste.'
                  : 'Cuando se generen avisos para tu cuenta, aparecerán aquí.',
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            UiKitSectionCard(
              title: 'No se pudo cargar',
              icon: Icons.error_outline,
              child: Text(error!),
            ),
          ],
          const SizedBox(height: 16),
          if (items.isEmpty && error == null)
            const UiKitSectionCard(
              title: 'Sin avisos',
              subtitle: 'Tu bandeja está vacía',
              icon: Icons.inbox_outlined,
              child: Text(
                'No hay avisos registrados para esta cuenta. '
                'Activa preferencias de ubicación y canales en Cuenta si quieres recibirlos.',
              ),
            )
          else
            for (final item in items) ...[
              UiKitSectionCard(
                title: item.alerta.titulo,
                subtitle: [
                  item.leido ? 'Leído' : 'Sin leer',
                  item.alerta.severidadEtiqueta,
                  if (item.alerta.instante != null)
                    BoliviaTime.dateTime(item.alerta.instante!),
                ].join(' · '),
                icon: item.leido
                    ? Icons.mark_email_read_outlined
                    : Icons.mark_email_unread_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(item.alerta.resumen),
                    if (!item.leido) ...[
                      const SizedBox(height: 12),
                      FilledButton.tonal(
                        onPressed: markingRead.contains(item.id)
                            ? null
                            : () => onMarkRead(item),
                        child: markingRead.contains(item.id)
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Marcar como leído'),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          if (hasMore) ...[
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: loadingMore ? null : onLoadMore,
              child: loadingMore
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Cargar más'),
            ),
          ],
        ],
      ),
    );
  }
}

class _AlertasTab extends StatelessWidget {
  const _AlertasTab({
    required this.loading,
    required this.loadingMore,
    required this.error,
    required this.items,
    required this.total,
    required this.hasMore,
    required this.severidad,
    required this.canAcknowledge,
    required this.acknowledging,
    required this.onSeveridadChanged,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onAcknowledge,
  });

  final bool loading;
  final bool loadingMore;
  final String? error;
  final List<AlertAmbiental> items;
  final int total;
  final bool hasMore;
  final String? severidad;
  final bool canAcknowledge;
  final Set<String> acknowledging;
  final ValueChanged<String?> onSeveridadChanged;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<AlertAmbiental> onAcknowledge;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          UiKitSectionCard(
            title: 'Filtro de severidad',
            subtitle: total > 0 ? '$total alertas' : 'Historado ambiental',
            icon: Icons.filter_list,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('Todas'),
                  selected: severidad == null,
                  onSelected: (_) => onSeveridadChanged(null),
                ),
                ChoiceChip(
                  label: const Text('Crítica'),
                  selected: severidad == 'critica',
                  onSelected: (_) => onSeveridadChanged('critica'),
                ),
                ChoiceChip(
                  label: const Text('Emergencia'),
                  selected: severidad == 'emergencia',
                  onSelected: (_) => onSeveridadChanged('emergencia'),
                ),
              ],
            ),
          ),
          if (!canAcknowledge) ...[
            const SizedBox(height: 12),
            const UiKitSectionCard(
              title: 'Reconocer no disponible',
              subtitle: 'Permiso alertas.gestionar',
              icon: Icons.lock_outline,
              child: Text(
                'Puedes consultar el historial de alertas. '
                'Reconocer una alerta requiere el permiso «alertas.gestionar». '
                'Pide a un administrador que te habilite esta función.',
              ),
            ),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            UiKitSectionCard(
              title: 'No se pudo cargar',
              icon: Icons.error_outline,
              child: Text(error!),
            ),
          ],
          const SizedBox(height: 16),
          if (items.isEmpty && error == null)
            UiKitSectionCard(
              title: 'Sin alertas',
              subtitle: severidad == null
                  ? 'No hay registros en el historial'
                  : 'Sin coincidencias para este filtro',
              icon: Icons.campaign_outlined,
              child: Text(
                severidad == null
                    ? 'Cuando el sistema detecte umbrales críticos o de emergencia, aparecerán aquí.'
                    : 'Prueba con «Todas» o con la otra severidad.',
              ),
            )
          else
            for (final item in items) ...[
              UiKitSectionCard(
                title: item.titulo,
                subtitle: [
                  item.severidadEtiqueta,
                  item.reconocida ? 'Reconocida' : 'Pendiente',
                  if (item.instante != null)
                    BoliviaTime.dateTime(item.instante!),
                ].join(' · '),
                icon: item.reconocida
                    ? Icons.verified_outlined
                    : Icons.priority_high,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(item.resumen),
                    if (item.reconocida && item.reconocidaEl != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Reconocida el ${BoliviaTime.dateTime(item.reconocidaEl!)}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                    if (!item.reconocida && canAcknowledge) ...[
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: acknowledging.contains(item.id)
                            ? null
                            : () => onAcknowledge(item),
                        child: acknowledging.contains(item.id)
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Reconocer'),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          if (hasMore) ...[
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: loadingMore ? null : onLoadMore,
              child: loadingMore
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Cargar más'),
            ),
          ],
        ],
      ),
    );
  }
}

class _UmbralesTab extends StatelessWidget {
  const _UmbralesTab({
    required this.loading,
    required this.error,
    required this.catalog,
    required this.onRefresh,
  });

  final bool loading;
  final String? error;
  final UmbralesCatalog? catalog;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (loading && catalog == null) {
      return const Center(child: CircularProgressIndicator());
    }
    final metricas = catalog?.metricas ?? const <UmbralMetrica>[];
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          UiKitSectionCard(
            title: 'Catálogo de umbrales',
            subtitle: 'Solo lectura',
            icon: Icons.tune,
            child: Text(
              catalog?.nota ??
                  'Rangos de referencia usados para clasificar lecturas ambientales.',
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            UiKitSectionCard(
              title: 'No se pudo cargar',
              icon: Icons.error_outline,
              child: Text(error!),
            ),
          ],
          const SizedBox(height: 16),
          if (metricas.isEmpty && error == null)
            const UiKitSectionCard(
              title: 'Sin umbrales',
              subtitle: 'Catálogo vacío',
              icon: Icons.inbox_outlined,
              child: Text(
                'La API no devolvió métricas en el catálogo de umbrales.',
              ),
            )
          else
            for (final metrica in metricas) ...[
              UiKitSectionCard(
                title: metrica.titulo,
                subtitle: '${metrica.niveles.length} niveles · ${metrica.key}',
                icon: Icons.layers_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var i = 0; i < metrica.niveles.length; i++) ...[
                      if (i > 0) const Divider(height: 20),
                      Text(
                        metrica.niveles[i].nombre,
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Nivel ${metrica.niveles[i].nivel} · '
                        '${metrica.niveles[i].minimo} – ${metrica.niveles[i].maximo} · '
                        '${metrica.niveles[i].severidadEtiqueta}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}
