// Respuestas controladas exclusivas de las pruebas MOV-09.
Map<String, dynamic> hotspotCatalog() => {
  'datos': [
    {'identificador': 'BO07', 'tipo': 'departamento', 'nombre': 'Santa Cruz'},
    {
      'identificador': 'BO0701',
      'tipo': 'provincia',
      'nombre': 'Andrés Ibáñez',
      'padre': 'BO07',
    },
    {
      'identificador': 'BO070101',
      'tipo': 'municipio',
      'nombre': 'Santa Cruz de la Sierra',
      'padre': 'BO0701',
    },
  ],
  'paginacion': {
    'pagina': 1,
    'tamanioPagina': 500,
    'total': 3,
    'siguiente': null,
  },
};

Map<String, dynamic> hotspotResponse({
  int? nextPage,
  int total = 1,
  num? radiantPower = 0,
  String? observedAt = '2026-10-17T18:30:00Z',
}) => {
  'versionContrato': '1.0-m2-candidata',
  'datos': [
    {
      'identificador': 'firms-prueba-001',
      'geometria': {
        'type': 'Point',
        'coordinates': [-63.18, -16.5],
      },
      'fechas': {'observadoDesde': observedAt},
      'naturaleza': 'observacion',
      'fuentesRef': ['nasa-firms-snpp-nrt'],
      'metodo': null,
      'atributos': {
        'satelite': 'N',
        'instrumento': 'VIIRS',
        'confianzaOriginal': 'n',
        'confianza': 'nominal',
        'potenciaRadiativa': radiantPower,
        'unidadPotenciaRadiativa': 'MW',
        'versionOriginal': '2.0NRT',
      },
      'faltantes': [],
      'territorio': {
        'identificador': 'BO070101',
        'tipo': 'municipio',
        'departamento': 'BO07',
        'provincia': 'BO0701',
        'municipio': 'BO070101',
        'versionCatalogo': 'catalogo-prueba-v1',
      },
    },
  ],
  'consulta': {
    'identificador': 'consulta-prueba-001',
    'modulo': 'focos-calor',
    'modo': 'externo',
    'territorio': {
      'identificador': 'BO070101',
      'tipo': 'municipio',
      'departamento': 'BO07',
      'provincia': 'BO0701',
      'municipio': 'BO070101',
      'versionCatalogo': 'catalogo-prueba-v1',
    },
    'periodo': {
      'desde': '2026-10-17T04:00:00Z',
      'hasta': '2026-10-18T04:00:00Z',
      'regla': 'final_excluido',
    },
    'fechaConsulta': '2026-10-18T06:22:59Z',
    'obtencion': {
      'tipo': 'consulta_proveedor',
      'fechaRecuperacion': '2026-10-18T06:22:59Z',
      'fechaActualizacion': null,
      'antiguedadSegundos': 0,
      'expiraEn': '2026-10-18T06:37:59Z',
    },
  },
  'fuentes': [
    {
      'identificador': 'nasa-firms-snpp-nrt',
      'proveedor': 'NASA FIRMS',
      'producto': 'VIIRS_SNPP_NRT',
      'version': '2.0NRT',
      'naturaleza': 'observacion',
    },
  ],
  'resumen': {
    'totalRegistros': total,
    'registrosValidos': total,
    'registrosConFaltantes': 0,
    'indicadores': [
      {
        'codigo': 'totalFocos',
        'valor': total,
        'unidad': 'registros',
        'registrosValidos': total,
        'metodo': 'conteo',
        'naturaleza': 'observacion',
      },
      {
        'codigo': 'focosConfianzaAlta',
        'valor': 0,
        'unidad': 'registros',
        'registrosValidos': total,
        'metodo': 'conteo_confianza_alta',
        'naturaleza': 'observacion',
      },
      {
        'codigo': 'potenciaMaxima',
        'valor': radiantPower,
        'unidad': 'MW',
        'registrosValidos': total,
        'metodo': 'maximo',
        'naturaleza': 'observacion',
      },
    ],
  },
  'cobertura': {'estado': 'desconocida', 'fundamento': 'Prueba controlada.'},
  'advertencias': [],
  'paginacion': {
    'pagina': nextPage == null ? 1 : 1,
    'tamanioPagina': 25,
    'total': total,
    'siguiente': nextPage,
    'versionConjunto': 'version-conjunto-prueba-1',
  },
  'representacionMapa': {'alcance': 'pagina'},
};

Map<String, dynamic> hotspotBoundary() => {
  'territorio': {'identificador': 'BO070101'},
  'limite': {
    'type': 'Feature',
    'geometry': {
      'type': 'Polygon',
      'coordinates': [
        [
          [-63.3, -16.6],
          [-63.0, -16.6],
          [-63.0, -16.4],
          [-63.3, -16.4],
          [-63.3, -16.6],
        ],
      ],
    },
  },
  'procedencia': {'version': 'catalogo-prueba-v1'},
};

Map<String, Object?> waterProduct({
  required String id,
  required String territoryId,
  required String territoryName,
  required String referenceDate,
  required String comparisonDate,
}) => {
  'id': id,
  'territorio': {
    'identificador': territoryId,
    'nombre': territoryName,
    'alcance': 'recorte_parcial',
  },
  'comparacion': {'referencia': referenceDate, 'comparacion': comparisonDate},
  'estado': 'sin_cambios',
};

Map<String, Object?> waterResult({
  required String territoryId,
  required String territoryName,
  required String referenceDate,
  required String comparisonDate,
  bool withFeature = false,
}) => {
  'versionContrato': 'agua-0.1-candidata',
  'tipoEvidencia': 'resultado_validado',
  'estado': 'sin_cambios',
  'comparacion': {'referencia': referenceDate, 'comparacion': comparisonDate},
  'territorio': {
    'identificador': territoryId,
    'nombre': territoryName,
    'alcance': 'recorte_parcial',
  },
  'fuente': {
    'nombre': 'Prueba local',
    'producto': 'Resultado controlado',
    'metodo': 'Solo prueba',
    'resolucionMetros': 20,
  },
  'cobertura': {
    'areaEstudiadaKm2': 1,
    'areaComparableKm2': 1,
    'fraccionComparable': 1,
  },
  'resumen': {
    'aguaPersistenteKm2': 1,
    'aguaNuevaKm2': 0,
    'aguaRetiradaKm2': 0,
    'inundacionConfirmada': null,
  },
  'capa': {
    'type': 'FeatureCollection',
    'features': withFeature
        ? <Object?>[
            {
              'id': 'zona-1',
              'properties': {'clase': 'agua_persistente'},
              'geometry': {
                'type': 'Polygon',
                'coordinates': [
                  [
                    [-63.20, -17.80],
                    [-63.19, -17.80],
                    [-63.19, -17.79],
                    [-63.20, -17.80],
                  ],
                ],
              },
            },
          ]
        : <Object?>[],
  },
  'advertencias': <String>[],
};
Map<String, Object?> forestResult() => {
  'revision': true,
  'resumen': {
    'consulta': {'desde': 2024, 'hasta': 2025},
    'bbox': [-63.20, -17.80, -63.19, -17.79],
    'total_periodo_ha': '0.07364891',
    'cobertura_observaciones_fraccion': null,
    'fuente': {'version': 'GFC-2025-v1.13'},
  },
  'capa': {
    'type': 'FeatureCollection',
    'features': [
      {
        'id': 'forestal-2025-1',
        'properties': {'anio': 2025, 'area_ha': '0.07364891'},
        'geometry': {
          'type': 'Polygon',
          'coordinates': [
            [
              [-63.20, -17.80],
              [-63.19, -17.80],
              [-63.19, -17.79],
              [-63.20, -17.80],
            ],
          ],
        },
      },
    ],
  },
};
