import 'package:flutter/material.dart';

Map<String, dynamic> _toMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return const {};
}

/// Categorías del índice SPI-3 según el contrato sequias-1.0-m3-candidata.
class DroughtCategory {
  const DroughtCategory({
    required this.id,
    required this.label,
    required this.color,
  });

  final String id;
  final String label;
  final Color color;

  static const extremadamenteSeco = DroughtCategory(
    id: 'extremadamente_seco',
    label: 'Extremadamente seco',
    color: Color(0xFF9A382D),
  );

  static const muySeco = DroughtCategory(
    id: 'muy_seco',
    label: 'Muy seco',
    color: Color(0xFFBC673B),
  );

  static const moderadamenteSeco = DroughtCategory(
    id: 'moderadamente_seco',
    label: 'Moderadamente seco',
    color: Color(0xFFBD9842),
  );

  static const normal = DroughtCategory(
    id: 'normal',
    label: 'Normal',
    color: Color(0xFF547D6E),
  );

  static const moderadamenteHumedo = DroughtCategory(
    id: 'moderadamente_humedo',
    label: 'Moderadamente húmedo',
    color: Color(0xFF3C7C99),
  );

  static const muyHumedo = DroughtCategory(
    id: 'muy_humedo',
    label: 'Muy húmedo',
    color: Color(0xFF3C7C99),
  );

  static const extremadamenteHumedo = DroughtCategory(
    id: 'extremadamente_humedo',
    label: 'Extremadamente húmedo',
    color: Color(0xFF3C7C99),
  );

  static const sinIndice = DroughtCategory(
    id: 'sin_indice',
    label: 'Sin índice',
    color: Color(0xFF7D8183),
  );

  static const List<DroughtCategory> all = [
    extremadamenteSeco,
    muySeco,
    moderadamenteSeco,
    normal,
    moderadamenteHumedo,
    sinIndice,
  ];

  static DroughtCategory fromId(String? id) {
    if (id == null) return sinIndice;
    switch (id) {
      case 'extremadamente_seco':
        return extremadamenteSeco;
      case 'muy_seco':
        return muySeco;
      case 'moderadamente_seco':
        return moderadamenteSeco;
      case 'normal':
        return normal;
      case 'moderadamente_humedo':
        return moderadamenteHumedo;
      case 'muy_humedo':
        return muyHumedo;
      case 'extremadamente_humedo':
        return extremadamenteHumedo;
      default:
        return sinIndice;
    }
  }
}

/// Motivos de valor nulo en el índice SPI según el contrato.
class DroughtMotives {
  static const Map<String, String> descriptions = {
    'BASE_INCOMPLETA': 'La base de referencia no tiene todos los años necesarios.',
    'PERIODO_INCOMPLETO': 'Faltan datos en la ventana de tres meses.',
    'BASE_NO_AJUSTABLE': 'La base no permite ajustar la distribución del indicador.',
    'AJUSTE_FALLIDO': 'No se pudo obtener un ajuste estadístico válido.',
    'PROBABILIDAD_EXTREMA': 'La probabilidad está fuera del intervalo numérico admitido.',
  };

  static String describe(String? motivo) {
    if (motivo == null) return 'Sin motivo especificado';
    return descriptions[motivo] ?? motivo;
  }
}

/// Resultado del indicador SPI para una muestra.
class SpiResult {
  const SpiResult({
    required this.valor,
    required this.categoria,
    required this.motivo,
    required this.aniosValidos,
    this.parametrosAjuste,
    this.probabilidadAcumulada,
  });

  final double? valor;
  final String? categoria;
  final String? motivo;
  final int aniosValidos;
  final Map<String, dynamic>? parametrosAjuste;
  final double? probabilidadAcumulada;

  bool get hasValue => valor != null;

  DroughtCategory get categoryInfo =>
      hasValue ? DroughtCategory.fromId(categoria) : DroughtCategory.sinIndice;

  factory SpiResult.fromJson(Map<String, dynamic> json) {
    return SpiResult(
      valor: (json['valor'] as num?)?.toDouble(),
      categoria: json['categoria'] as String?,
      motivo: json['motivo'] as String?,
      aniosValidos: (json['aniosValidos'] as num?)?.toInt() ?? 0,
      parametrosAjuste: json['parametrosAjuste'] as Map<String, dynamic>?,
      probabilidadAcumulada:
          (json['probabilidadAcumulada'] as num?)?.toDouble(),
    );
  }
}

/// Muestra de cálculo de sequías devuelta por la API.
class DroughtSample {
  const DroughtSample({
    required this.idMuestra,
    required this.territorioContexto,
    required this.municipioDelCentroCelda,
    required this.coordenadaSolicitada,
    required this.coordenadaCelda,
    required this.naturaleza,
    required this.fuentesRef,
    required this.metodo,
    required this.precipitacionAcumuladaMm,
    required this.spi,
  });

  final String idMuestra;
  final String territorioContexto;
  final String municipioDelCentroCelda;
  final List<double> coordenadaSolicitada;
  final List<double> coordenadaCelda;
  final String naturaleza;
  final List<String> fuentesRef;
  final String metodo;
  final double? precipitacionAcumuladaMm;
  final SpiResult spi;

  double get lon => coordenadaCelda[0];
  double get lat => coordenadaCelda[1];

  bool get centroDifiere => municipioDelCentroCelda != territorioContexto;

  factory DroughtSample.fromJson(Map<String, dynamic> json) {
    final sol = (json['coordenadaSolicitada'] as List<dynamic>)
        .map((e) => (e as num).toDouble())
        .toList();
    final cel = (json['coordenadaCelda'] as List<dynamic>)
        .map((e) => (e as num).toDouble())
        .toList();
    final fuentes = (json['fuentesRef'] as List<dynamic>?)
            ?.map((e) => e.toString())
            .toList() ??
        const [];

    return DroughtSample(
      idMuestra: json['idMuestra'] as String,
      territorioContexto: json['territorioContexto'] as String,
      municipioDelCentroCelda: json['municipioDelCentroCelda'] as String,
      coordenadaSolicitada: sol,
      coordenadaCelda: cel,
      naturaleza: json['naturaleza'] as String? ?? 'derivado_de_reanalisis',
      fuentesRef: fuentes,
      metodo: json['metodo'] as String? ?? '',
      precipitacionAcumuladaMm:
          (json['precipitacionAcumuladaMm'] as num?)?.toDouble(),
      spi: SpiResult.fromJson(_toMap(json['spi'])),
    );
  }
}

/// Resumen numérico devuelto por la API.
class DroughtSummary {
  const DroughtSummary({
    required this.totalMuestras,
    required this.muestrasConIndice,
    required this.muestrasSinIndice,
    required this.muestrasConDeficit,
    required this.spiMinimo,
    required this.unidadConteo,
  });

  final int totalMuestras;
  final int muestrasConIndice;
  final int muestrasSinIndice;
  final int muestrasConDeficit;
  final double? spiMinimo;
  final String unidadConteo;

  factory DroughtSummary.fromJson(Map<String, dynamic> json) {
    return DroughtSummary(
      totalMuestras: (json['totalMuestras'] as num?)?.toInt() ?? 0,
      muestrasConIndice: (json['muestrasConIndice'] as num?)?.toInt() ?? 0,
      muestrasSinIndice: (json['muestrasSinIndice'] as num?)?.toInt() ?? 0,
      muestrasConDeficit: (json['muestrasConDeficit'] as num?)?.toInt() ?? 0,
      spiMinimo: (json['spiMinimo'] as num?)?.toDouble(),
      unidadConteo:
          json['unidadConteo'] as String? ?? 'muestras_no_superficie',
    );
  }
}

/// Cobertura territorial y completitud devuelta por la API.
class DroughtCoverage {
  const DroughtCoverage({
    required this.estadoEspacial,
    required this.municipiosEnFiltro,
    required this.municipiosConMuestra,
    required this.municipiosConDatos,
    required this.municipiosConIndice,
    required this.celdasUnicas,
    required this.muestrasSeleccionadas,
    required this.muestrasSinPreparar,
    required this.estadoDatos,
  });

  final String estadoEspacial;
  final int municipiosEnFiltro;
  final int municipiosConMuestra;
  final int municipiosConDatos;
  final int municipiosConIndice;
  final int celdasUnicas;
  final int muestrasSeleccionadas;
  final List<String> muestrasSinPreparar;
  final String estadoDatos;

  bool get esParcial => muestrasSinPreparar.isNotEmpty;

  factory DroughtCoverage.fromJson(Map<String, dynamic> json) {
    final pendientes = (json['muestrasSinPreparar'] as List<dynamic>?)
            ?.map((e) => e.toString())
            .toList() ??
        const [];
    return DroughtCoverage(
      estadoEspacial: json['estadoEspacial'] as String? ?? 'muestras_puntuales',
      municipiosEnFiltro:
          (json['municipiosEnFiltro'] as num?)?.toInt() ?? 0,
      municipiosConMuestra:
          (json['municipiosConMuestra'] as num?)?.toInt() ?? 0,
      municipiosConDatos:
          (json['municipiosConDatos'] as num?)?.toInt() ?? 0,
      municipiosConIndice:
          (json['municipiosConIndice'] as num?)?.toInt() ?? 0,
      celdasUnicas: (json['celdasUnicas'] as num?)?.toInt() ?? 0,
      muestrasSeleccionadas:
          (json['muestrasSeleccionadas'] as num?)?.toInt() ?? 0,
      muestrasSinPreparar: pendientes,
      estadoDatos: json['estadoDatos'] as String? ?? 'completo',
    );
  }
}

/// Respuesta completa del endpoint /api/v1/sequias.
class DroughtResponse {
  const DroughtResponse({
    required this.versionContrato,
    required this.datos,
    required this.consulta,
    required this.fuentes,
    required this.resumen,
    required this.cobertura,
    required this.advertencias,
  });

  final String versionContrato;
  final List<DroughtSample> datos;
  final Map<String, dynamic> consulta;
  final List<dynamic> fuentes;
  final DroughtSummary resumen;
  final DroughtCoverage cobertura;
  final List<String> advertencias;

  factory DroughtResponse.fromJson(Object? raw) {
    final json = _toMap(raw);
    final datosList = (json['datos'] as List<dynamic>?)
            ?.map((e) => DroughtSample.fromJson(_toMap(e)))
            .toList() ??
        const [];
    final advertenciasList = (json['advertencias'] as List<dynamic>?)
            ?.map((e) => e.toString())
            .toList() ??
        const [];

    return DroughtResponse(
      versionContrato: json['versionContrato'] as String? ?? '',
      datos: datosList,
      consulta: _toMap(json['consulta']),
      fuentes: json['fuentes'] as List<dynamic>? ?? const [],
      resumen: DroughtSummary.fromJson(_toMap(json['resumen'])),
      cobertura: DroughtCoverage.fromJson(_toMap(json['cobertura'])),
      advertencias: advertenciasList,
    );
  }
}

/// Filtros de consulta para sequías.
class DroughtFilters {
  const DroughtFilters({
    required this.mesFinal,
    this.provincia,
    this.municipio,
    this.muestra,
  });

  final String mesFinal;
  final String? provincia;
  final String? municipio;
  final String? muestra;

  Map<String, String> toQueryParameters() {
    final params = <String, String>{'mesFinal': mesFinal};
    if (provincia != null && provincia!.isNotEmpty) {
      params['provincia'] = provincia!;
    }
    if (municipio != null && municipio!.isNotEmpty) {
      params['municipio'] = municipio!;
    }
    if (muestra != null && muestra!.isNotEmpty) {
      params['muestra'] = muestra!;
    }
    return params;
  }

  DroughtFilters copyWith({
    String? mesFinal,
    String? provincia,
    String? municipio,
    String? muestra,
  }) {
    return DroughtFilters(
      mesFinal: mesFinal ?? this.mesFinal,
      provincia: provincia ?? this.provincia,
      municipio: municipio ?? this.municipio,
      muestra: muestra ?? this.muestra,
    );
  }

  void validate(DateTime nowUtc) {
    if (!RegExp(r'^\d{4}-(0[1-9]|1[0-2])$').hasMatch(mesFinal)) {
      throw const FormatException(
          'El mes final debe tener el formato AAAA-MM (ej. 2024-09).');
    }
    if (mesFinal.compareTo('2021-01') < 0) {
      throw const FormatException(
          'La consulta debe ser posterior o igual a enero de 2021.');
    }
    final currentMonth =
        '${nowUtc.year.toString().padLeft(4, '0')}-${nowUtc.month.toString().padLeft(2, '0')}';
    if (mesFinal.compareTo(currentMonth) >= 0) {
      throw const FormatException(
          'El mes final debe ser anterior al mes UTC actual.');
    }
  }
}
