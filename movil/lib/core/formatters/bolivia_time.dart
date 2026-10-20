class BoliviaTime {
  const BoliviaTime._();

  static String interval(DateTime? from, DateTime? untilExclusive) {
    if (from == null || untilExclusive == null) return 'Período no aplicado';
    return '${dateTime(from)} – ${dateTime(untilExclusive)} (fin excluido)';
  }

  static String dateTime(DateTime value) {
    final bolivia = value.toUtc().subtract(const Duration(hours: 4));
    String twoDigits(int number) => number.toString().padLeft(2, '0');
    return '${twoDigits(bolivia.day)}/${twoDigits(bolivia.month)}/'
        '${bolivia.year} ${twoDigits(bolivia.hour)}:'
        '${twoDigits(bolivia.minute)}';
  }
}
