import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/units/units_presentation.dart';
import '../../../core/widgets/units_scope.dart';
import '../model/monitoring_comparison.dart';
import '../model/monitoring_models.dart';

class MonitoringComparisonCard extends StatelessWidget {
  const MonitoringComparisonCard({
    required this.reference,
    required this.selection,
    required this.onShowLocations,
    super.key,
  });

  final MonitoringSelection reference;
  final MonitoringSelection selection;
  final void Function(MonitoringComparisonRow row) onShowLocations;

  @override
  Widget build(BuildContext context) {
    final rows = compareMonitoringReadings(reference, selection);
    final units =
        UnitsScope.maybeOf(context)?.presentation ??
        UnitsPresentation(catalog: UnitsCatalog.empty);
    String value(num number, {bool difference = false}) => units.format(
      selection.metric,
      number,
      selection.unit,
      difference: difference,
    );
    return Column(
      key: const Key('monitoring-comparison'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('${reference.point.name} → ${selection.point.name}'),
        if (rows == null)
          Text(
            'Para comparar, elige la misma variable y unidad de la referencia (${reference.metric}, ${reference.unit}).',
          )
        else if (rows.isEmpty)
          const Text('No hay lecturas para comparar.')
        else
          for (final row in rows) ...[
            const SizedBox(height: 12),
            Text(
              '${_origin((row.reference ?? row.selection)!.origin)} · ${(row.reference ?? row.selection)!.source}',
            ),
            _reading(context, 'Referencia', row.reference, value),
            _reading(context, 'Selección', row.selection, value),
            if (row.difference case final difference?) ...[
              Text(
                'Diferencia (selección − referencia): ${difference > 0 ? '+' : ''}${value(difference, difference: true)}',
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => onShowLocations(row),
                icon: const Icon(Icons.map_outlined),
                label: const Text('Ver ambas ubicaciones'),
              ),
            ] else
              const Text(
                'Sin diferencia disponible: falta una lectura de esta fuente y origen.',
              ),
          ],
      ],
    );
  }

  Widget _reading(
    BuildContext context,
    String label,
    MonitoringReading? reading,
    String Function(num, {bool difference}) format,
  ) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.labelLarge),
        Text(reading == null ? 'Sin lectura' : format(reading.value)),
        if (reading != null)
          Text(
            reading.at == null
                ? 'Fecha no informada'
                : '${BoliviaTime.dateTime(reading.at!)} · Bolivia',
          ),
      ],
    ),
  );

  String _origin(String origin) => switch (origin) {
    'estimado' => 'Estimado',
    'observado' => 'Registrado',
    'simulado' => 'Simulado',
    _ => origin,
  };
}
