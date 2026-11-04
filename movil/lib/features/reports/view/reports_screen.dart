import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/uikits.dart';
import '../../auth/model/auth_repository.dart';
import '../model/report_models.dart';
import '../model/report_repository.dart';
import 'report_detail_screen.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({
    required this.api,
    required this.user,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final GeoSurUser user;
  final VoidCallback onUnauthorized;

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen>
    with SingleTickerProviderStateMixin {
  late final ReportRepository _repository;
  late final TabController _tabs;
  late final TextEditingController _filter;

  final List<ReportSummary> _reports = [];
  final List<ReportTemplate> _templates = [];
  int _reportPage = 0;
  int _reportLastPage = 1;
  bool _loadingReports = false;
  bool _loadingMoreReports = false;
  bool _loadingTemplates = false;
  String? _reportsError;
  String? _templatesError;

  @override
  void initState() {
    super.initState();
    _repository = ReportRepository(widget.api);
    _filter = TextEditingController();
    _tabs = TabController(length: widget.user.canViewReports ? 2 : 1, vsync: this);
    if (widget.user.canViewReports) {
      _loadReports(reset: true);
      _loadTemplates();
    }
  }

  @override
  void dispose() {
    _filter.dispose();
    _tabs.dispose();
    super.dispose();
  }

  List<ReportSummary> get _filteredReports {
    final query = _filter.text.trim().toLowerCase();
    if (query.isEmpty) return _reports;
    return _reports
        .where((item) => item.title.toLowerCase().contains(query))
        .toList(growable: false);
  }

  Future<void> _loadReports({required bool reset}) async {
    if (!widget.user.canViewReports) return;
    if (_loadingReports || _loadingMoreReports) return;
    final nextPage = reset ? 1 : _reportPage + 1;
    if (!reset && nextPage > _reportLastPage) return;

    setState(() {
      if (reset) {
        _loadingReports = true;
        _reportsError = null;
      } else {
        _loadingMoreReports = true;
      }
    });

    try {
      final page = await _repository.list(page: nextPage);
      if (!mounted) return;
      setState(() {
        if (reset) _reports.clear();
        _reports.addAll(page.items);
        _reportPage = page.page;
        _reportLastPage = page.lastPage;
        _loadingReports = false;
        _loadingMoreReports = false;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _loadingReports = false;
        _loadingMoreReports = false;
        _reportsError = error.statusCode == 403
            ? 'No tienes permiso para ver reportes.'
            : error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingReports = false;
        _loadingMoreReports = false;
        _reportsError = 'No se pudo cargar el listado de reportes.';
      });
    }
  }

  Future<void> _loadTemplates() async {
    if (!widget.user.canViewReports) return;
    setState(() {
      _loadingTemplates = true;
      _templatesError = null;
    });
    try {
      final page = await _repository.templates();
      if (!mounted) return;
      setState(() {
        _templates
          ..clear()
          ..addAll(page.items);
        _loadingTemplates = false;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _loadingTemplates = false;
        _templatesError = error.statusCode == 403
            ? 'No tienes permiso para ver plantillas.'
            : error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingTemplates = false;
        _templatesError = 'No se pudieron cargar las plantillas.';
      });
    }
  }

  Future<void> _openDetail(ReportSummary item) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ReportDetailScreen(
          api: widget.api,
          reportId: item.id,
          canDownload: widget.user.canViewReports,
          onUnauthorized: widget.onUnauthorized,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.user.canViewReports) {
      return Scaffold(
        appBar: AppBar(title: const Text('Reportes')),
        body: const Padding(
          padding: EdgeInsets.all(20),
          child: UiKitSectionCard(
            title: 'Reportes bloqueados',
            subtitle: 'Acceso restringido',
            icon: Icons.lock_outline,
            child: Text(
              'Tu cuenta no tiene el permiso «reportes.ver». '
              'Pide a un administrador que te habilite esta función.',
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reportes'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Mis reportes'),
            Tab(text: 'Plantillas'),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Actualizar',
            onPressed: () {
              _loadReports(reset: true);
              _loadTemplates();
            },
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _ReportsTab(
            filter: _filter,
            loading: _loadingReports && _reports.isEmpty,
            loadingMore: _loadingMoreReports,
            error: _reportsError,
            items: _filteredReports,
            hasMore: _reportPage < _reportLastPage,
            canCreate: widget.user.canCreateReports,
            onFilterChanged: () => setState(() {}),
            onRefresh: () => _loadReports(reset: true),
            onLoadMore: () => _loadReports(reset: false),
            onOpen: _openDetail,
          ),
          _TemplatesTab(
            loading: _loadingTemplates,
            error: _templatesError,
            items: _templates,
            onRefresh: _loadTemplates,
          ),
        ],
      ),
    );
  }
}

class _ReportsTab extends StatelessWidget {
  const _ReportsTab({
    required this.filter,
    required this.loading,
    required this.loadingMore,
    required this.error,
    required this.items,
    required this.hasMore,
    required this.canCreate,
    required this.onFilterChanged,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final TextEditingController filter;
  final bool loading;
  final bool loadingMore;
  final String? error;
  final List<ReportSummary> items;
  final bool hasMore;
  final bool canCreate;
  final VoidCallback onFilterChanged;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<ReportSummary> onOpen;

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
          TextField(
            controller: filter,
            onChanged: (_) => onFilterChanged(),
            decoration: const InputDecoration(
              labelText: 'Filtrar por título',
              prefixIcon: Icon(Icons.search),
            ),
          ),
          if (!canCreate) ...[
            const SizedBox(height: 12),
            const UiKitSectionCard(
              title: 'Solo lectura',
              subtitle: 'Permiso reportes.ver',
              icon: Icons.info_outline,
              child: Text(
                'Puedes ver y descargar reportes. Crear nuevos requiere «reportes.crear» (disponible en la web).',
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
              title: filter.text.trim().isEmpty
                  ? 'Sin reportes'
                  : 'Sin coincidencias',
              subtitle: filter.text.trim().isEmpty
                  ? 'Aún no hay reportes propios'
                  : 'Prueba otro texto de búsqueda',
              icon: Icons.description_outlined,
              child: Text(
                filter.text.trim().isEmpty
                    ? 'Genera un reporte desde la web GeoSur y aparecerá aquí.'
                    : 'Ningún título contiene «${filter.text.trim()}».',
              ),
            )
          else
            for (final item in items) ...[
              Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(20),
                  onTap: () => onOpen(item),
                  child: UiKitSectionCard(
                    title: item.title,
                    subtitle: item.createdAt != null
                        ? BoliviaTime.dateTime(item.createdAt!)
                        : 'Sin fecha',
                    icon: Icons.description_outlined,
                    child: Align(
                      alignment: Alignment.centerRight,
                      child: Icon(
                        Icons.chevron_right,
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
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

class _TemplatesTab extends StatelessWidget {
  const _TemplatesTab({
    required this.loading,
    required this.error,
    required this.items,
    required this.onRefresh,
  });

  final bool loading;
  final String? error;
  final List<ReportTemplate> items;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (loading && items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          const UiKitSectionCard(
            title: 'Plantillas',
            subtitle: 'Se gestionan en la web',
            icon: Icons.info_outline,
            child: Text(
              'Aquí solo se listan las tuyas (API /plantillas).',
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
              title: 'Sin plantillas',
              subtitle: 'No hay plantillas propias',
              icon: Icons.dashboard_customize_outlined,
              child: Text(
                'Crea una plantilla en la web GeoSur para reutilizar filtros al generar reportes.',
              ),
            )
          else
            for (final item in items) ...[
              UiKitSectionCard(
                title: item.name,
                subtitle: item.isArchived
                    ? 'Archivada · ${item.configSummary}'
                    : item.configSummary,
                icon: Icons.dashboard_customize_outlined,
                child: const SizedBox.shrink(),
              ),
              const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}
