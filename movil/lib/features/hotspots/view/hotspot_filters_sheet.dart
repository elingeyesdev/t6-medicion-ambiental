import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/formatters/consultation_period.dart';
import '../model/hotspot_models.dart';

class HotspotFiltersSheet extends StatefulWidget {
  const HotspotFiltersSheet({
    required this.initial,
    required this.provinces,
    required this.municipalities,
    required this.nameFor,
    super.key,
  });

  final HotspotFilters initial;
  final List<TerritoryOption> provinces;
  final List<TerritoryOption> municipalities;
  final String Function(String?) nameFor;

  @override
  State<HotspotFiltersSheet> createState() => _HotspotFiltersSheetState();
}

class _HotspotFiltersSheetState extends State<HotspotFiltersSheet> {
  late DateTime _from;
  late DateTime _until;
  String? _provinceId;
  String? _municipalityId;
  String? _confidence;
  String? _validationError;

  TerritoryOption? _municipality(String? id) {
    for (final territory in widget.municipalities) {
      if (territory.identifier == id) return territory;
    }
    return null;
  }

  @override
  void initState() {
    super.initState();
    _from = widget.initial.fromUtc;
    _until = widget.initial.untilUtc;
    _provinceId = widget.initial.provinceId;
    _municipalityId = widget.initial.municipalityId;
    _confidence = widget.initial.confidence;
  }

  DateTime _boliviaWall(DateTime instant) =>
      instant.toUtc().subtract(const Duration(hours: 4));

  Future<DateTime?> _chooseInstant(DateTime current) async {
    final currentWall = _boliviaWall(current);
    final today = ConsultationPeriod.boliviaDay(DateTime.now());
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime(
        currentWall.year,
        currentWall.month,
        currentWall.day,
      ),
      firstDate: DateTime(2000),
      lastDate: today,
      helpText: 'Fecha · hora de Bolivia',
    );
    if (!mounted || date == null) return null;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: currentWall.hour,
        minute: currentWall.minute,
      ),
      helpText: 'Hora de Bolivia',
    );
    if (!mounted || time == null) return null;
    return DateTime.utc(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    ).add(const Duration(hours: 4));
  }

  Future<void> _selectFrom() async {
    final selected = await _chooseInstant(_from);
    if (selected != null) {
      setState(() {
        _from = selected;
        _validationError = null;
      });
    }
  }

  Future<void> _selectUntil() async {
    final selected = await _chooseInstant(_until);
    if (selected != null) {
      setState(() {
        _until = selected;
        _validationError = null;
      });
    }
  }

  void _apply() {
    try {
      ConsultationPeriod.validate(_from, _until);
    } on FormatException catch (error) {
      setState(() => _validationError = error.message);
      return;
    }
    if (_provinceId != null &&
        _municipalityId != null &&
        _municipality(_municipalityId)?.parentId != _provinceId) {
      setState(
        () => _validationError =
            'El municipio debe pertenecer a la provincia seleccionada.',
      );
      return;
    }
    Navigator.of(context).pop(
      HotspotFilters(
        fromUtc: _from.toUtc(),
        untilUtc: _until.toUtc(),
        provinceId: _provinceId,
        municipalityId: _municipalityId,
        confidence: _confidence,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final municipalities = widget.municipalities
        .where(
          (territory) =>
              _provinceId == null || territory.parentId == _provinceId,
        )
        .toList(growable: false);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          8,
          16,
          12 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.outlineVariant,
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Filtrar focos',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 6),
              Text(
                'Elige el período y acota la consulta dentro de la cobertura disponible.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.secondaryContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Text(
                  'Por ahora, la API consulta focos solo en Santa Cruz, Bolivia. '
                  'Los demás países de Sudamérica todavía no están habilitados para este análisis.',
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Período · hora de Bolivia',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              _DateChoice(
                label: 'Desde · incluido',
                value: _from,
                onTap: _selectFrom,
              ),
              const SizedBox(height: 8),
              _DateChoice(
                label: 'Hasta · excluido',
                value: _until,
                onTap: _selectUntil,
              ),
              const SizedBox(height: 5),
              Text(
                'Máximo 7 días. La fecha final no se incluye.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 8),
              if (_validationError != null) ...[
                _ValidationMessage(message: _validationError!),
                const SizedBox(height: 8),
              ],
              Theme(
                data: Theme.of(
                  context,
                ).copyWith(dividerColor: Colors.transparent),
                child: ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: EdgeInsets.zero,
                  initiallyExpanded:
                      _provinceId != null ||
                      _municipalityId != null ||
                      _confidence != null,
                  title: const Text('Territorio y confianza'),
                  subtitle: Text(
                    _filterSummary(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  children: [
                    DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: _provinceId ?? '',
                      decoration: const InputDecoration(
                        labelText: 'Provincia de Santa Cruz',
                      ),
                      items: [
                        const DropdownMenuItem(
                          value: '',
                          child: Text('Todo el departamento'),
                        ),
                        for (final province in widget.provinces)
                          DropdownMenuItem(
                            value: province.identifier,
                            child: Text(
                              province.name,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: (value) => setState(() {
                        final selected = value == null || value.isEmpty
                            ? null
                            : value;
                        if (_municipality(_municipalityId)?.parentId !=
                            selected) {
                          _municipalityId = null;
                        }
                        _provinceId = selected;
                        _validationError = null;
                      }),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: _municipalityId ?? '',
                      decoration: const InputDecoration(labelText: 'Municipio'),
                      items: [
                        const DropdownMenuItem(
                          value: '',
                          child: Text('Todos los municipios'),
                        ),
                        for (final municipality in municipalities)
                          DropdownMenuItem(
                            value: municipality.identifier,
                            child: Text(
                              municipality.name,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: (value) => setState(() {
                        _municipalityId = value == null || value.isEmpty
                            ? null
                            : value;
                        if (_municipalityId != null && _provinceId == null) {
                          _provinceId = _municipality(
                            _municipalityId,
                          )?.parentId;
                        }
                        _validationError = null;
                      }),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: _confidence ?? '',
                      decoration: const InputDecoration(labelText: 'Confianza'),
                      items: const [
                        DropdownMenuItem(value: '', child: Text('Todas')),
                        DropdownMenuItem(value: 'alta', child: Text('Alta')),
                        DropdownMenuItem(
                          value: 'nominal',
                          child: Text('Nominal'),
                        ),
                        DropdownMenuItem(value: 'baja', child: Text('Baja')),
                        DropdownMenuItem(
                          value: 'desconocida',
                          child: Text('Desconocida'),
                        ),
                      ],
                      onChanged: (value) => setState(
                        () => _confidence = value == null || value.isEmpty
                            ? null
                            : value,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _apply,
                  icon: const Icon(Icons.search_rounded),
                  label: const Text('Aplicar filtros'),
                ),
              ),
              const SizedBox(height: 6),
              SizedBox(
                width: double.infinity,
                child: TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Cancelar'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _filterSummary() {
    final parts = <String>[];
    if (_provinceId == null) {
      parts.add('Todo Santa Cruz');
    } else {
      parts.add(widget.nameFor(_provinceId));
    }
    if (_municipalityId != null) {
      parts.add(widget.nameFor(_municipalityId));
    }
    if (_confidence != null) {
      parts.add('Confianza ${_confidence!}');
    }
    return parts.join(' · ');
  }
}

class _ValidationMessage extends StatelessWidget {
  const _ValidationMessage({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.error;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline_rounded, color: color, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message, style: TextStyle(color: color)),
          ),
        ],
      ),
    );
  }
}

class _DateChoice extends StatelessWidget {
  const _DateChoice({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => OutlinedButton(
    onPressed: onTap,
    style: OutlinedButton.styleFrom(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      alignment: Alignment.centerLeft,
    ),
    child: Row(
      children: [
        const Icon(Icons.calendar_month_outlined),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 3),
              Text(
                BoliviaTime.dateTime(value),
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            ],
          ),
        ),
        const Icon(Icons.edit_calendar_outlined, size: 18),
      ],
    ),
  );
}
