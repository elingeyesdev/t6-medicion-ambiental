import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/units/units_presentation.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_comparison.dart';
import 'package:geocruz_movil/features/monitoring/model/monitoring_models.dart';
import 'package:geocruz_movil/features/monitoring/view/monitoring_comparison_card.dart';

MonitoringReading reading(num value, {String source = 'open-meteo-modelo'}) =>
    MonitoringReading(
      value: value,
      at: DateTime.utc(2026, 11, 26, 21, 30),
      origin: 'estimado',
      source: source,
    );

MonitoringSelection select(
  String name,
  List<MonitoringReading> readings, {
  String metric = 'temperatura',
  String unit = '°C',
}) {
  final point = MonitoringPoint(
    code: name,
    name: name,
    country: 'BO',
    latitude: -17.78,
    longitude: -63.18,
    type: 'referencia',
    readings: readings,
  );
  return MonitoringSelection(
    point,
    MonitoringResponse(
      metric: metric,
      unit: unit,
      from: DateTime.utc(2026, 11, 20),
      until: DateTime.utc(2026, 11, 27),
      origin: 'estimado',
      points: [point],
      boundary: null,
      page: 1,
      lastPage: 1,
      total: 1,
      notice: '',
    ),
  );
}

void main() {
  test('coincide con la web y conserva el cero informado', () {
    final result = compareMonitoringReadings(
      select('Cuiabá', [reading(29.4)]),
      select('Santa Cruz', [reading(25.7)]),
    )!;
    expect(result.single.difference, -3.7);
    expect(
      compareMonitoringReadings(
        select('A', [reading(0)]),
        select('B', [reading(0)]),
      )!.single.difference,
      0,
    );
  });

  test('separa fuentes y no calcula con faltantes ni variables distintas', () {
    final reference = select('A', [reading(10)]);
    final rows = compareMonitoringReadings(
      reference,
      select('B', [reading(20, source: 'otro-modelo')]),
    )!;
    expect(rows, hasLength(2));
    expect(rows.every((r) => r.difference == null), isTrue);
    expect(
      compareMonitoringReadings(
        reference,
        select('B', [reading(20)], metric: 'humedad', unit: '%'),
      ),
      isNull,
    );
  });

  test('referencia conserva una copia de las lecturas', () {
    final original = [reading(10)];
    final reference = select('A', original);
    original.clear();
    expect(reference.readings.single.value, 10);
  });

  test(
    'la diferencia en Fahrenheit no suma el desplazamiento de 32 grados',
    () {
      final units = UnitsPresentation(
        catalog: UnitsCatalog.fromJson({
          'temperatura': {
            'base': '°C',
            'variables': ['temperatura'],
            'defecto': 'f',
            'opciones': {
              'f': {
                'simbolo': '°F',
                'factor': 9,
                'divisor': 5,
                'desplazamiento': 32,
                'decimales': 2,
              },
            },
          },
        }),
      );
      expect(
        units.format('temperatura', -3.7, '°C', difference: true),
        '-6,66 °F',
      );
    },
  );

  testWidgets('muestra valores, fecha de Bolivia y permite ver ubicaciones', (
    tester,
  ) async {
    var opened = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MonitoringComparisonCard(
              reference: select('Cuiabá', [reading(29.4)]),
              selection: select('Santa Cruz', [reading(25.7)]),
              onShowLocations: (_) => opened = true,
            ),
          ),
        ),
      ),
    );
    expect(find.text('Cuiabá → Santa Cruz'), findsOneWidget);
    expect(find.text('26/11/2026 17:30 · Bolivia'), findsNWidgets(2));
    expect(find.textContaining('-3,7 °C'), findsOneWidget);
    await tester.tap(find.text('Ver ambas ubicaciones'));
    expect(opened, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('explica fuentes sin pareja y cambio de variable', (
    tester,
  ) async {
    Future<void> show(MonitoringSelection selection) => tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MonitoringComparisonCard(
            reference: select('A', [reading(0)]),
            selection: selection,
            onShowLocations: (_) {},
          ),
        ),
      ),
    );
    await show(select('B', []));
    expect(find.text('Sin lectura'), findsOneWidget);
    expect(find.text('Ver ambas ubicaciones'), findsNothing);
    await show(select('B', [reading(65)], metric: 'humedad', unit: '%'));
    expect(
      find.textContaining('Para comparar, elige la misma variable'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}
