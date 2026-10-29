import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/formatters/consultation_period.dart';
import 'package:geocruz_movil/features/hotspots/view_model/hotspots_view_model.dart';

void main() {
  final now = DateTime.utc(2026, 10, 29, 3, 30);

  test('el valor inicial conserva 168 horas hasta ahora, no hasta ayer', () {
    final period = ConsultationPeriod.lastSevenDays(now: now);
    expect(period.until, now);
    expect(period.until.difference(period.from), const Duration(hours: 168));
    final hotspots = HotspotsViewModel.defaultFilters(now: now);
    expect(hotspots.fromUtc, period.from);
    expect(hotspots.untilUtc, period.until);
  });

  test('hoy en Bolivia conserva la fecha correcta antes de las 04 UTC', () {
    final day = ConsultationPeriod.boliviaDay(now);
    expect([day.year, day.month, day.day], [2026, 10, 28]);
    final next = ConsultationPeriod.boliviaDay(DateTime.utc(2026, 10, 29, 4));
    expect([next.year, next.month, next.day], [2026, 10, 29]);
  });

  test('seleccionar hoy no envía la medianoche futura', () {
    final period = ConsultationPeriod.selectedDays(DateTime(2026, 10, 22), DateTime(2026, 10, 28), now: now);
    expect(period.from, DateTime.utc(2026, 10, 22, 4));
    expect(period.until, now);
    expect(period.until.difference(period.from) <= const Duration(hours: 168), isTrue);
  });

  test('un día pasado usa el final exclusivo de Bolivia', () {
    final period = ConsultationPeriod.selectedDays(DateTime(2026, 10, 20), DateTime(2026, 10, 20), now: now);
    expect(period.from, DateTime.utc(2026, 10, 20, 4));
    expect(period.until, DateTime.utc(2026, 10, 21, 4));
  });

  test('siete días completos se permiten y ocho se rechazan', () {
    final period = ConsultationPeriod.selectedDays(DateTime(2026, 10, 20), DateTime(2026, 10, 26), now: now);
    expect(period.until.difference(period.from), const Duration(hours: 168));
    expect(() => ConsultationPeriod.selectedDays(DateTime(2026, 10, 20), DateTime(2026, 10, 27), now: now), throwsFormatException);
  });

  test('futuro y período vacío se rechazan antes de consultar', () {
    expect(() => ConsultationPeriod.validate(now.subtract(const Duration(hours: 1)), now.add(const Duration(seconds: 1)), now: now), throwsFormatException);
    expect(() => ConsultationPeriod.validate(now, now, now: now), throwsFormatException);
    expect(() => ConsultationPeriod.selectedDays(DateTime(2026, 10, 28), DateTime(2026, 10, 29), now: now), throwsFormatException);
  });
}
