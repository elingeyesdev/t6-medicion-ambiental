import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/units/units_presentation.dart';

void main() {
  final catalog = UnitsCatalog.fromJson({
    'temperatura': {
      'nombre': 'Temperatura',
      'base': '°C',
      'variables': ['temperatura'],
      'defecto': 'C',
      'opciones': {
        'C': {
          'nombre': 'Celsius (°C)',
          'simbolo': '°C',
          'factor': 1,
          'divisor': 1,
          'desplazamiento': 0,
          'decimales': 1,
        },
        'F': {
          'nombre': 'Fahrenheit (°F)',
          'simbolo': '°F',
          'factor': 1.8,
          'divisor': 1,
          'desplazamiento': 32,
          'decimales': 1,
        },
      },
    },
  });

  test('convierte temperatura a Fahrenheit como la web', () {
    final units = UnitsPresentation(
      catalog: catalog,
      preferences: const {'temperatura': 'F'},
    );
    expect(units.convert('temperatura', 0, '°C'), 32);
    expect(units.format('temperatura', 0, '°C'), '32 °F');
    expect(units.convert('temperatura', 2, '°C', difference: true), closeTo(3.6, 1e-9));
  });

  test('conserva faltantes', () {
    final units = UnitsPresentation(
      catalog: catalog,
      preferences: const {'temperatura': 'F'},
    );
    expect(units.convert('temperatura', null, '°C'), isNull);
    expect(units.format('temperatura', null, '°C'), 'No disponible');
  });
}
