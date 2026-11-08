import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/features/monitoring/model/raster_decode.dart';

void main() {
  group('Mercator + centro de píxel + registro', () {
    // Rejilla tipo GFS del comentario de revisión: 281 filas, 57°S–13°N.
    const alto = 281;
    const sur = -57.0;
    const norte = 13.0;

    test('usa el centro del píxel, no el borde y/(alto-1)', () {
      final latCentro = latitudCentroSalidaMercator(
        y: 50,
        alto: alto,
        sur: sur,
        norte: norte,
      );
      final tBorde = 50 / (alto - 1);
      final mercBorde =
          mercatorY(norte) + tBorde * (mercatorY(sur) - mercatorY(norte));
      final latBorde = latFromMercatorY(mercBorde);
      // El centro no debe coincidir con el cálculo por bordes de fila.
      expect(latCentro, isNot(closeTo(latBorde, 1e-6)));
      // Con centros, la lat intermedia queda más al sur que el borde superior.
      expect(latCentro, lessThan(latBorde));
    });

    test('extremos norte y sur quedan en la rejilla', () {
      final latN = latitudCentroSalidaMercator(
        y: 0,
        alto: alto,
        sur: sur,
        norte: norte,
      );
      final latS = latitudCentroSalidaMercator(
        y: alto - 1,
        alto: alto,
        sur: sur,
        norte: norte,
      );
      expect(latN, lessThan(norte));
      expect(latN, greaterThan(sur));
      expect(latS, greaterThan(sur));
      expect(latS, lessThan(norte));
      expect(latN, greaterThan(latS));
    });

    test('latitud intermedia: centro vs borde y centros vs nodos', () {
      // Cálculo por borde (viejo): y/(alto-1) → ~1,6766° S; el centro queda ~10 km más al sur.
      final tBorde = 50 / (alto - 1);
      final latBorde = latFromMercatorY(
        mercatorY(norte) + tBorde * (mercatorY(sur) - mercatorY(norte)),
      );
      final latCentro = latitudCentroSalidaMercator(
        y: 50,
        alto: alto,
        sur: sur,
        norte: norte,
      );
      expect(latCentro, closeTo(-1.7713, 0.02));
      expect(latBorde, closeTo(-1.6766, 0.02));
      // ~10,5 km a esta latitud (1° ≈ 111 km).
      expect((latCentro - latBorde).abs() * 111, closeTo(10.5, 1.5));

      final filaCentros = filaFuenteParaLatitud(
        lat: latCentro,
        alto: alto,
        sur: sur,
        norte: norte,
        registro: 'centros',
      );
      final filaNodos = filaFuenteParaLatitud(
        lat: latCentro,
        alto: alto,
        sur: sur,
        norte: norte,
        registro: 'nodos',
      );
      expect(filaCentros, isNot(closeTo(filaNodos, 1e-6)));
    });

    test('nodos y centros discrepan en la misma latitud', () {
      const lat = -1.7;
      final c = filaFuenteParaLatitud(
        lat: lat,
        alto: alto,
        sur: sur,
        norte: norte,
        registro: 'centros',
      );
      final n = filaFuenteParaLatitud(
        lat: lat,
        alto: alto,
        sur: sur,
        norte: norte,
        registro: 'nodos',
      );
      expect((c - n).abs(), greaterThan(0.2));
    });
  });
}
