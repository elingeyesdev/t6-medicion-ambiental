class ConsultationPeriod {
  const ConsultationPeriod._();

  static ({DateTime from, DateTime until}) lastSevenDays({DateTime? now}) {
    final until = (now ?? DateTime.now()).toUtc();
    return (from: until.subtract(const Duration(days: 7)), until: until);
  }

  /// Calendar date in Bolivia, independent of the phone's time zone.
  static DateTime boliviaDay(DateTime instant) {
    final wall = instant.toUtc().subtract(const Duration(hours: 4));
    return DateTime(wall.year, wall.month, wall.day);
  }

  static ({DateTime from, DateTime until}) selectedDays(
    DateTime first,
    DateTime last, {
    DateTime? now,
  }) {
    final current = (now ?? DateTime.now()).toUtc();
    if (DateTime.utc(last.year, last.month, last.day, 4).isAfter(current)) {
      throw const FormatException('No se pueden consultar fechas futuras.');
    }
    final from = DateTime.utc(first.year, first.month, first.day, 4);
    final nextDay = DateTime.utc(last.year, last.month, last.day + 1, 4);
    final until = nextDay.isAfter(current) ? current : nextDay;
    validate(from, until, now: current);
    return (from: from, until: until);
  }

  static void validate(DateTime from, DateTime until, {DateTime? now}) {
    final current = (now ?? DateTime.now()).toUtc();
    final duration = until.toUtc().difference(from.toUtc());
    if (duration <= Duration.zero) {
      throw const FormatException('“Hasta” debe ser posterior a “Desde”.');
    }
    if (duration > const Duration(hours: 168)) {
      throw const FormatException('El período máximo es de 7 días.');
    }
    if (from.toUtc().isAfter(current) || until.toUtc().isAfter(current)) {
      throw const FormatException('No se pueden consultar fechas futuras.');
    }
  }
}
