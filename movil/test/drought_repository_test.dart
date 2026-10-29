import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';

import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/drought/model/drought_models.dart';
import 'package:geocruz_movil/features/drought/model/drought_repository.dart';

class MockGeoCruzApiClient extends Fake implements GeoCruzApiClient {
  Object? nextResponse;
  String? lastPath;
  Map<String, String>? lastQueryParams;

  @override
  Future<Object?> getJson(
    String path, {
    bool authenticated = false,
    Map<String, String> queryParameters = const {},
    Duration? timeoutOverride,
  }) async {
    lastPath = path;
    lastQueryParams = queryParameters;
    if (nextResponse != null) return nextResponse!;
    throw const GeoCruzApiException(statusCode: 500, message: 'Mock error');
  }
}

void main() {
  group('DroughtFilters', () {
    test('valida correctamente mes final en rango', () {
      final nowUtc = DateTime.utc(2026, 10, 28);
      const valid = DroughtFilters(mesFinal: '2024-09');
      expect(() => valid.validate(nowUtc), returnsNormally);

      const invalidFormat = DroughtFilters(mesFinal: '2024-9');
      expect(() => invalidFormat.validate(nowUtc), throwsFormatException);

      const tooOld = DroughtFilters(mesFinal: '2020-12');
      expect(() => tooOld.validate(nowUtc), throwsFormatException);

      const currentMonth = DroughtFilters(mesFinal: '2026-10');
      expect(() => currentMonth.validate(nowUtc), throwsFormatException);

      const futureMonth = DroughtFilters(mesFinal: '2026-11');
      expect(() => futureMonth.validate(nowUtc), throwsFormatException);
    });

    test('genera queryParameters adecuados', () {
      const filters = DroughtFilters(
        mesFinal: '2024-09',
        provincia: 'BO0701',
        municipio: 'BO070101',
        muestra: 'scz-piloto-01',
      );
      final params = filters.toQueryParameters();
      expect(params['mesFinal'], '2024-09');
      expect(params['provincia'], 'BO0701');
      expect(params['municipio'], 'BO070101');
      expect(params['muestra'], 'scz-piloto-01');
    });
  });

  group('DroughtResponse y Modelos', () {
    test('parsea correctamente respuesta oficial de sequías', () {
      const jsonRaw = '''
      {
        "versionContrato": "sequias-1.0-m3-candidata",
        "datos": [
          {
            "idMuestra": "scz-piloto-01",
            "territorioContexto": "BO070101",
            "municipioDelCentroCelda": "BO070103",
            "coordenadaSolicitada": [-63.1821, -17.7833],
            "coordenadaCelda": [-63.25, -17.75],
            "naturaleza": "derivado_de_reanalisis",
            "fuentesRef": ["open_meteo_era5"],
            "metodo": "spi3-era5-gamma-mle-1991-2020-v0.1-propuesta",
            "precipitacionAcumuladaMm": 114.5,
            "spi": {
              "valor": -0.6410480784168531,
              "categoria": "normal",
              "motivo": null,
              "aniosValidos": 30,
              "parametrosAjuste": {
                "forma": 7.4759,
                "escalaMm": 20.5486,
                "probabilidadCero": 0,
                "positivos": 30
              },
              "probabilidadAcumulada": 0.2607
            }
          }
        ],
        "consulta": {
          "estado": "metodo_acordado",
          "mesFinal": "2024-09",
          "desde": "2024-07-01",
          "hastaIncluido": "2024-09-30",
          "zona": "UTC",
          "provincia": "BO0701",
          "municipio": "BO070101",
          "muestra": "scz-piloto-01"
        },
        "fuentes": [],
        "resumen": {
          "totalMuestras": 1,
          "muestrasConIndice": 1,
          "muestrasSinIndice": 0,
          "muestrasConDeficit": 0,
          "spiMinimo": -0.6410480784168531,
          "unidadConteo": "muestras_no_superficie"
        },
        "cobertura": {
          "estadoEspacial": "muestras_puntuales",
          "municipiosEnFiltro": 1,
          "municipiosConMuestra": 1,
          "municipiosConDatos": 1,
          "municipiosConIndice": 1,
          "celdasUnicas": 1,
          "muestrasSeleccionadas": 1,
          "muestrasSinPreparar": [],
          "estadoDatos": "completo_para_muestras_seleccionadas"
        },
        "advertencias": [
          "Índice meteorológico propio de GeoCruz, derivado de ERA5."
        ]
      }
      ''';

      final map = jsonDecode(jsonRaw) as Map<String, dynamic>;
      final response = DroughtResponse.fromJson(map);

      expect(response.versionContrato, 'sequias-1.0-m3-candidata');
      expect(response.datos.length, 1);

      final sample = response.datos.first;
      expect(sample.idMuestra, 'scz-piloto-01');
      expect(sample.territorioContexto, 'BO070101');
      expect(sample.municipioDelCentroCelda, 'BO070103');
      expect(sample.centroDifiere, isTrue);
      expect(sample.lon, -63.25);
      expect(sample.lat, -17.75);
      expect(sample.precipitacionAcumuladaMm, 114.5);
      expect(sample.spi.valor, closeTo(-0.64, 0.01));
      expect(sample.spi.categoria, 'normal');
      expect(sample.spi.categoryInfo.label, 'Normal');
      expect(sample.spi.hasValue, isTrue);

      expect(response.resumen.totalMuestras, 1);
      expect(response.resumen.muestrasConIndice, 1);
      expect(response.resumen.muestrasSinIndice, 0);
      expect(response.resumen.muestrasConDeficit, 0);
      expect(response.cobertura.esParcial, isFalse);
      expect(response.advertencias.isNotEmpty, isTrue);
    });

    test('no convierte valor null en cero ni normal y conserva motivo', () {
      const jsonRaw = '''
      {
        "idMuestra": "scz-incompleto",
        "territorioContexto": "BO070102",
        "municipioDelCentroCelda": "BO070102",
        "coordenadaSolicitada": [-62.89, -17.76],
        "coordenadaCelda": [-63.0, -17.75],
        "naturaleza": "derivado_de_reanalisis",
        "fuentesRef": ["open_meteo_era5"],
        "metodo": "spi3-era5-gamma-mle-1991-2020-v0.1-propuesta",
        "precipitacionAcumuladaMm": 50.0,
        "spi": {
          "valor": null,
          "categoria": null,
          "motivo": "PERIODO_INCOMPLETO",
          "aniosValidos": 28
        }
      }
      ''';

      final sample =
          DroughtSample.fromJson(jsonDecode(jsonRaw) as Map<String, dynamic>);
      expect(sample.spi.hasValue, isFalse);
      expect(sample.spi.valor, isNull);
      expect(sample.spi.categoria, isNull);
      expect(sample.spi.motivo, 'PERIODO_INCOMPLETO');
      expect(sample.spi.categoryInfo.id, 'sin_indice');
      expect(sample.spi.categoryInfo.label, 'Sin índice');
      expect(DroughtMotives.describe(sample.spi.motivo),
          contains('Faltan datos en la ventana de tres meses'));
    });
  });

  group('DroughtRepository query', () {
    test('ejecuta consulta al endpoint sequias con parámetros correctos',
        () async {
      final mock = MockGeoCruzApiClient();
      mock.nextResponse = {
        'versionContrato': 'sequias-1.0-m3-candidata',
        'datos': [],
        'consulta': {},
        'fuentes': [],
        'resumen': {},
        'cobertura': {},
        'advertencias': [],
      };

      final repo = DroughtRepository(mock);
      const filters = DroughtFilters(mesFinal: '2024-09', municipio: 'BO070101');
      final res = await repo.query(filters);

      expect(mock.lastPath, 'sequias');
      expect(mock.lastQueryParams?['mesFinal'], '2024-09');
      expect(mock.lastQueryParams?['municipio'], 'BO070101');
      expect(res.versionContrato, 'sequias-1.0-m3-candidata');
    });
  });
}
