Map<String, dynamic> droughtResponse({String month = '2024-09'}) => {
  'versionContrato': 'sequias-1.0-m3-candidata',
  'datos': [droughtSample()],
  'consulta': {'mesFinal': month},
  'fuentes': [],
  'resumen': {
    'totalMuestras': 1, 'muestrasConIndice': 1,
    'muestrasSinIndice': 0, 'muestrasConDeficit': 0,
    'spiMinimo': 0, 'unidadConteo': 'muestras_no_superficie',
  },
  'cobertura': {
    'estadoEspacial': 'muestras_puntuales', 'municipiosEnFiltro': 56,
    'municipiosConMuestra': 56, 'municipiosConDatos': 1,
    'municipiosConIndice': 1, 'celdasUnicas': 1,
    'muestrasSeleccionadas': 56, 'muestrasSinPreparar': ['pendiente'],
    'estadoDatos': 'parcial',
  },
  'advertencias': ['Cobertura parcial: faltan muestras para este mes.'],
};

Map<String, dynamic> droughtSample() => {
  'idMuestra': 'scz-piloto-01', 'territorioContexto': 'BO070101',
  'municipioDelCentroCelda': null,
  'coordenadaSolicitada': [-63.1821, -17.7833],
  'coordenadaCelda': [-63.25, -17.75],
  'naturaleza': 'derivado_de_reanalisis',
  'fuentesRef': ['open_meteo_era5'], 'metodo': 'spi3-era5',
  'precipitacionAcumuladaMm': 0,
  'spi': {'valor': 0, 'categoria': 'normal', 'motivo': null, 'aniosValidos': 30},
};
