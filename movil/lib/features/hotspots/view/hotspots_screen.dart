import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../view_model/hotspots_view_model.dart';
import '../model/hotspot_models.dart';
import '../model/hotspot_repository.dart';
import 'hotspot_detail_screen.dart';
import 'hotspot_filters_sheet.dart';
import 'hotspot_map_card.dart';

class HotspotsScreen extends StatefulWidget {
  const HotspotsScreen({required this.apiClient, super.key});

  final GeoCruzApiClient apiClient;

  @override
  State<HotspotsScreen> createState() => _HotspotsScreenState();
}

class _HotspotsScreenState extends State<HotspotsScreen> {
  late final HotspotsViewModel _controller;
  bool _showMap = true;

  @override
  void initState() {
    super.initState();
    _controller = HotspotsViewModel(HotspotRepository(widget.apiClient));
    _controller.initialize();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _openFilters() async {
    final selected = await showModalBottomSheet<HotspotFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: false,
      builder: (_) => HotspotFiltersSheet(
        initial: _controller.filters,
        provinces: _controller.provinces,
        municipalities: _controller.municipalitiesFor(null),
        nameFor: _controller.territoryName,
      ),
    );
    if (!mounted || selected == null) return;
    try {
      await _controller.apply(selected);
    } on FormatException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  void _openRecord(HotspotRecord record, HotspotResponse response) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => HotspotDetailScreen(
          record: record,
          response: response,
          territoryName: (id) => _controller.territoryName(id),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _controller,
    builder: (context, _) {
      final response = _controller.response;
      return Scaffold(
        appBar: AppBar(
          title: const Text('Focos de calor'),
          actions: [
            IconButton(
              tooltip: 'Filtros',
              onPressed: _controller.loadingCatalog ? null : _openFilters,
              icon: const Icon(Icons.tune_rounded),
            ),
          ],
        ),
        body: _controller.loadingCatalog && _controller.territories.isEmpty
            ? _catalogLoading(context)
            : _controller.catalogError != null &&
                  _controller.territories.isEmpty
            ? _catalogFailure(context)
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 30),
                children: [
                  _queryStatus(context, response),
                  if (response != null) ...[
                    const SizedBox(height: 10),
                    _appliedContext(context, response),
                    const SizedBox(height: 10),
                    _viewSelector(context),
                    const SizedBox(height: 10),
                    if (_showMap)
                      HotspotMapCard(
                        key: ValueKey(
                          _controller.boundary?.identifier ??
                              response.territoryId,
                        ),
                        records: response.records,
                        boundary: _controller.boundary,
                        territoryName: _controller.territoryName(
                          response.territoryId,
                        ),
                        onOpenRecord: (record) => _openRecord(record, response),
                      ),
                    if (!_showMap) _results(context, response),
                    if (_controller.mapError != null && _showMap)
                      Padding(
                        padding: const EdgeInsets.only(left: 4, top: 6),
                        child: Text(
                          'Límite territorial no disponible.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    const SizedBox(height: 10),
                    _summary(context, response),
                  ],
                ],
              ),
      );
    },
  );

  Widget _catalogLoading(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 14),
          Text(
            'Cargando territorios de Santa Cruz…',
            style: Theme.of(context).textTheme.bodyLarge,
          ),
        ],
      ),
    ),
  );

  Widget _catalogFailure(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 38),
          const SizedBox(height: 12),
          Text(_controller.catalogError!, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: () => _controller.loadCatalog(runInitialQuery: true),
            icon: const Icon(Icons.refresh),
            label: const Text('Reintentar catálogo'),
          ),
        ],
      ),
    ),
  );

  Widget _queryStatus(BuildContext context, HotspotResponse? response) {
    if (_controller.loading) {
      return _StatusCard(
        icon: Icons.sync_rounded,
        title: response == null
            ? 'Consultando focos de calor…'
            : 'Actualizando consulta…',
        message: response == null
            ? 'La consulta se procesa desde GeoCruz con NASA FIRMS.'
            : 'Se conservan los resultados anteriores y su contexto hasta recibir la nueva respuesta.',
        tone: _StatusTone.loading,
        progress: true,
      );
    }
    if (_controller.queryError != null) {
      return _StatusCard(
        icon: _controller.queryExpired
            ? Icons.hourglass_disabled_rounded
            : Icons.error_outline_rounded,
        title: _controller.queryExpired
            ? 'El conjunto de resultados expiró'
            : 'No se pudo completar la consulta',
        message:
            '${_controller.queryError!}${response == null ? ' No se sustituyeron por datos de ejemplo.' : ' Se mantienen abajo los resultados anteriores con su período y filtros originales.'}',
        tone: _StatusTone.error,
        action: _controller.queryExpired
            ? () => _controller.restartExpiredQuery()
            : () => _controller.retry(),
        actionLabel: _controller.queryExpired
            ? 'Reiniciar consulta'
            : 'Reintentar consulta',
      );
    }
    if (response == null) {
      return const _StatusCard(
        icon: Icons.info_outline_rounded,
        title: 'Define una consulta',
        message:
            'Elige territorio, período y confianza para consultar los datos disponibles.',
        tone: _StatusTone.neutral,
      );
    }

    final coverage = response.coverageState;
    if (response.total > 0 &&
        coverage != 'sin_cobertura' &&
        coverage != 'parcial') {
      return const SizedBox.shrink();
    }
    final title = response.total == 0
        ? 'Sin detecciones para estos filtros'
        : switch (coverage) {
            'sin_cobertura' => 'Sin cobertura comprobada',
            'parcial' => 'Resultados con cobertura parcial',
            _ => 'Consulta lista',
          };
    final message = response.total == 0
        ? 'No hay detecciones en el período consultado.'
        : response.coverage['fundamento']?.toString() ??
              'La fuente informó cobertura parcial para este período.';
    final tone = coverage == 'sin_cobertura' || coverage == 'parcial'
        ? _StatusTone.warning
        : _StatusTone.success;
    return _StatusCard(
      icon: Icons.check_circle_outline_rounded,
      title: title,
      message: message,
      tone: tone,
    );
  }

  Widget _appliedContext(BuildContext context, HotspotResponse response) {
    final oldResults = _controller.loading || _controller.queryError != null;
    final obtainAt = response.fetchedAt;
    final ageMinutes = response.ageSeconds == null
        ? null
        : response.ageSeconds! ~/ 60;
    final period = BoliviaTime.interval(
      response.periodFrom,
      response.periodUntil,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            const Icon(Icons.place_outlined, color: GeoCruzColors.green),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _controller.territoryName(response.territoryId),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    '$period${oldResults ? ' · datos anteriores' : ''}',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (response.fetchType == 'cache' || obtainAt != null)
                    Text(
                      response.fetchType == 'cache'
                          ? 'Guardado · ${ageMinutes ?? 0} min'
                          : 'Actualizado · ${BoliviaTime.dateTime(obtainAt!)}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _viewSelector(BuildContext context) {
    return SegmentedButton<bool>(
      segments: const [
        ButtonSegment(
          value: true,
          icon: Icon(Icons.map_outlined),
          label: Text('Mapa'),
        ),
        ButtonSegment(
          value: false,
          icon: Icon(Icons.list_alt_rounded),
          label: Text('Lista'),
        ),
      ],
      selected: {_showMap},
      onSelectionChanged: (selection) =>
          setState(() => _showMap = selection.first),
      showSelectedIcon: false,
    );
  }

  Widget _summary(BuildContext context, HotspotResponse response) {
    final totalIndicator = response.indicator('totalFocos');
    final highIndicator = response.indicator('focosConfianzaAlta');
    final maxFrp = response.indicator('potenciaMaxima');
    return Card(
      child: ExpansionTile(
        leading: const Icon(Icons.info_outline_rounded),
        title: const Text('Datos de la consulta'),
        subtitle: Text('${_formatNumber(response.total)} detecciones'),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
        children: [
          Row(
            children: [
              Expanded(
                child: _MetricCard(
                  label: 'Detecciones',
                  value: _formatNumber(
                    totalIndicator?['valor'] ?? response.total,
                  ),
                  unit: totalIndicator?['unidad']?.toString() ?? 'registros',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MetricCard(
                  label: 'Confianza alta',
                  value: _formatNumber(highIndicator?['valor']),
                  unit: highIndicator?['unidad']?.toString() ?? 'registros',
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _MetricCard(
                  label: 'FRP máxima',
                  value: _formatNumber(maxFrp?['valor']),
                  unit: maxFrp?['unidad']?.toString() ?? 'MW',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MetricCard(
                  label: 'Con faltantes',
                  value: _formatNumber(
                    response.summary['registrosConFaltantes'],
                  ),
                  unit: 'registros',
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            'Resumen de ${_formatNumber(response.summary['registrosValidos'])} registros válidos · FRP disponible en ${_formatNumber(maxFrp?['registrosValidos'])} registros. La potencia radiativa en MW no es superficie quemada.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 8),
          _CoverageCard(response: response),
          const SizedBox(height: 10),
          Text(
            'NASA FIRMS · ${response.sources.map((source) => source is Map ? source['producto'] : null).whereType<String>().join(', ')} · Observación.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 4),
          Text(
            'Una detección térmica no confirma un incendio.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  Widget _results(BuildContext context, HotspotResponse response) {
    final pageCount = (response.total / response.pageSize).ceil();
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Detecciones',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                Text(
                  '${response.records.length} en esta página',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
            const SizedBox(height: 3),
            const SizedBox(height: 12),
            if (response.records.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Text(
                  response.total > 0
                      ? 'Esta página no contiene registros. El total del conjunto se conserva en el resumen.'
                      : 'La consulta respondió correctamente sin registros. Revisa el período y la cobertura publicada.',
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
              )
            else
              for (final record in response.records) ...[
                _RecordTile(
                  record: record,
                  territoryName: _controller.territoryName(
                    record.territory['municipio']?.toString(),
                  ),
                  onTap: () => _openRecord(record, response),
                ),
                const Divider(height: 1),
              ],
            const SizedBox(height: 8),
            Row(
              children: [
                OutlinedButton.icon(
                  onPressed: !_controller.loading && response.page > 1
                      ? _controller.previousPage
                      : null,
                  icon: const Icon(Icons.chevron_left_rounded),
                  label: const Text('Anterior'),
                ),
                Expanded(
                  child: Text(
                    'Página ${response.page} de ${pageCount == 0 ? 1 : pageCount}',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: !_controller.loading && response.nextPage != null
                      ? _controller.nextPage
                      : null,
                  icon: const Icon(Icons.chevron_right_rounded),
                  label: const Text('Siguiente'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatNumber(Object? value) {
    if (value == null) return 'Sin dato';
    if (value is num) return NumberFormat('###,##0.##', 'es_BO').format(value);
    return '$value';
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.unit,
  });

  final String label;
  final String value;
  final String unit;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 6),
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          Text(unit, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    ),
  );
}

class _CoverageCard extends StatelessWidget {
  const _CoverageCard({required this.response});

  final HotspotResponse response;

  @override
  Widget build(BuildContext context) {
    final (icon, color) = switch (response.coverageState) {
      'parcial' => (Icons.warning_amber_rounded, GeoCruzColors.amber),
      'sin_cobertura' => (
        Icons.signal_wifi_statusbar_null_rounded,
        GeoCruzColors.amber,
      ),
      'disponible' => (Icons.check_circle_outline_rounded, GeoCruzColors.green),
      _ => (Icons.help_outline_rounded, GeoCruzColors.textSoft),
    };
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 19),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '${switch (response.coverageState) {
                'disponible' => 'Datos disponibles con límites del producto',
                'parcial' => 'Cobertura parcial',
                'sin_cobertura' => 'Sin cobertura comprobada',
                'desconocida' => 'Cobertura desconocida',
                _ => 'Cobertura no informada',
              }}. ${response.coverage['fundamento'] ?? ''}',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}

class _RecordTile extends StatelessWidget {
  const _RecordTile({
    required this.record,
    required this.territoryName,
    required this.onTap,
  });

  final HotspotRecord record;
  final String territoryName;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final confidence = switch (record.confidence) {
      'alta' => 'Alta',
      'nominal' => 'Nominal',
      'baja' => 'Baja',
      'desconocida' => 'Desconocida',
      _ => 'No informada',
    };
    final date = record.observedAt == null
        ? 'Fecha no informada'
        : '${BoliviaTime.dateTime(DateTime.parse(record.observedAt!))} · Bolivia';
    final power = record.radiantPower == null
        ? 'FRP sin dato'
        : 'FRP ${NumberFormat('###,##0.##', 'es_BO').format(record.radiantPower)} ${record.radiantPowerUnit ?? 'MW'}';
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 3),
      onTap: onTap,
      title: Text(territoryName, style: Theme.of(context).textTheme.titleLarge),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$date · Confianza $confidence'),
            Text(power),
            Text(
              record.identifier,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
      trailing: const Icon(Icons.chevron_right_rounded),
    );
  }
}

enum _StatusTone { neutral, loading, success, warning, error }

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.tone,
    this.progress = false,
    this.action,
    this.actionLabel,
  });

  final IconData icon;
  final String title;
  final String message;
  final _StatusTone tone;
  final bool progress;
  final VoidCallback? action;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    final color = switch (tone) {
      _StatusTone.success => GeoCruzColors.green,
      _StatusTone.warning => GeoCruzColors.amber,
      _StatusTone.error => Theme.of(context).colorScheme.error,
      _StatusTone.loading => GeoCruzColors.green,
      _StatusTone.neutral => GeoCruzColors.textSoft,
    };
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, color: color),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        message,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (progress) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
            ],
            if (action != null) ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: action,
                  icon: const Icon(Icons.refresh_rounded),
                  label: Text(actionLabel ?? 'Reintentar'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
