import 'monitoring_models.dart';

class MonitoringSelection {
  MonitoringSelection(this.point, MonitoringResponse response)
    : metric = response.metric,
      unit = response.unit,
      from = response.from,
      until = response.until,
      readings = List.unmodifiable(point.readings);

  final MonitoringPoint point;
  final String metric;
  final String unit;
  final DateTime? from;
  final DateTime? until;
  final List<MonitoringReading> readings;
}

class MonitoringComparisonRow {
  const MonitoringComparisonRow(this.reference, this.selection);

  final MonitoringReading? reference;
  final MonitoringReading? selection;

  num? get difference => reference == null || selection == null
      ? null
      : ((selection!.value - reference!.value) * 10000).round() / 10000;
}

/// Conserva cada serie de origen y fuente, como la comparación web.
List<MonitoringComparisonRow>? compareMonitoringReadings(
  MonitoringSelection reference,
  MonitoringSelection selection,
) {
  if (reference.metric != selection.metric ||
      reference.unit != selection.unit) {
    return null;
  }
  final first = {
    for (final reading in reference.readings)
      (reading.origin, reading.source): reading,
  };
  final second = {
    for (final reading in selection.readings)
      (reading.origin, reading.source): reading,
  };
  return [
    for (final key in {...first.keys, ...second.keys})
      MonitoringComparisonRow(first[key], second[key]),
  ];
}
