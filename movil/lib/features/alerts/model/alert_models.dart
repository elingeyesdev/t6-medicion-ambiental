class AlertAmbiental {
  const AlertAmbiental({
    required this.id,
    required this.metrica,
    required this.origen,
    required this.severidad,
    required this.reconocida,
    required this.contenido,
    this.instante,
    this.reconocidaEl,
  });

  final String id;
  final String metrica;
  final String origen;
  final String severidad;
  final bool reconocida;
  final Map<String, dynamic> contenido;
  final DateTime? instante;
  final DateTime? reconocidaEl;

  String get titulo {
    final nombre = contenido['nombre'];
    final umbral = contenido['umbral'];
    final umbralNombre = umbral is Map ? umbral['nombre'] : null;
    if (nombre is String &&
        nombre.isNotEmpty &&
        umbralNombre is String &&
        umbralNombre.isNotEmpty) {
      return '$nombre · $umbralNombre';
    }
    if (nombre is String && nombre.isNotEmpty) return nombre;
    return 'Alerta ambiental';
  }

  String get valorTexto {
    final valor = contenido['valor'];
    if (valor == null) return 'Sin valor';
    return '$valor';
  }

  String get severidadEtiqueta => switch (severidad) {
    'emergencia' => 'Emergencia',
    'critica' => 'Crítica',
    'advertencia' => 'Advertencia',
    'informativa' => 'Informativa',
    _ => severidad.isEmpty ? 'Sin severidad' : severidad,
  };

  String get resumen {
    final parts = <String>[
      if (metrica.isNotEmpty) 'Métrica: $metrica',
      if (origen.isNotEmpty) 'Origen: $origen',
      'Valor: $valorTexto',
    ];
    return parts.join(' · ');
  }

  factory AlertAmbiental.fromJson(Object? value) {
    final map = _asMap(value);
    return AlertAmbiental(
      id: map['id']?.toString() ?? '',
      metrica: map['metrica'] is String ? map['metrica'] as String : '',
      origen: map['origen'] is String ? map['origen'] as String : '',
      severidad: map['severidad'] is String ? map['severidad'] as String : '',
      reconocida: map['reconocida'] == true || map['reconocida_el'] != null,
      contenido: _asMap(map['contenido']),
      instante: _asDate(map['instante']),
      reconocidaEl: _asDate(map['reconocida_el']),
    );
  }

  AlertAmbiental copyWith({
    bool? reconocida,
    DateTime? reconocidaEl,
  }) {
    return AlertAmbiental(
      id: id,
      metrica: metrica,
      origen: origen,
      severidad: severidad,
      reconocida: reconocida ?? this.reconocida,
      contenido: contenido,
      instante: instante,
      reconocidaEl: reconocidaEl ?? this.reconocidaEl,
    );
  }
}

class AlertPage {
  const AlertPage({
    required this.items,
    required this.page,
    required this.total,
    required this.lastPage,
  });

  final List<AlertAmbiental> items;
  final int page;
  final int total;
  final int lastPage;

  bool get hasMore => page < lastPage;

  factory AlertPage.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['datos'];
    final items = raw is List
        ? raw.map(AlertAmbiental.fromJson).toList(growable: false)
        : const <AlertAmbiental>[];
    return AlertPage(
      items: items,
      page: _asInt(map['pagina'], 1),
      total: _asInt(map['total'], items.length),
      lastPage: _asInt(map['ultimaPagina'], 1),
    );
  }
}

class AvisoAmbiental {
  const AvisoAmbiental({
    required this.id,
    required this.leido,
    required this.alerta,
  });

  final String id;
  final bool leido;
  final AlertAmbiental alerta;

  factory AvisoAmbiental.fromJson(Object? value) {
    final map = _asMap(value);
    return AvisoAmbiental(
      id: map['id']?.toString() ?? '',
      leido: map['leido'] == true,
      alerta: AlertAmbiental.fromJson(map['alerta']),
    );
  }

  AvisoAmbiental copyWith({bool? leido}) {
    return AvisoAmbiental(
      id: id,
      leido: leido ?? this.leido,
      alerta: alerta,
    );
  }
}

class AvisosPage {
  const AvisosPage({
    required this.items,
    required this.page,
    required this.lastPage,
    required this.unread,
    required this.preferences,
  });

  final List<AvisoAmbiental> items;
  final int page;
  final int lastPage;
  final int unread;
  final Map<String, dynamic> preferences;

  bool get hasMore => page < lastPage;

  factory AvisosPage.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['datos'];
    final items = raw is List
        ? raw.map(AvisoAmbiental.fromJson).toList(growable: false)
        : const <AvisoAmbiental>[];
    return AvisosPage(
      items: items,
      page: _asInt(map['pagina'], 1),
      lastPage: _asInt(map['ultimaPagina'], 1),
      unread: _asInt(map['sinLeer'], 0),
      preferences: _asMap(map['preferencias']),
    );
  }
}

class UmbralNivel {
  const UmbralNivel({
    required this.nivel,
    required this.nombre,
    required this.minimo,
    required this.maximo,
    required this.severidad,
  });

  final int nivel;
  final String nombre;
  final num minimo;
  final num maximo;
  final String severidad;

  String get severidadEtiqueta => switch (severidad) {
    'emergencia' => 'Emergencia',
    'critica' => 'Crítica',
    'advertencia' => 'Advertencia',
    'informativa' => 'Informativa',
    _ => severidad.isEmpty ? '—' : severidad,
  };

  factory UmbralNivel.fromJson(Object? value) {
    final map = _asMap(value);
    return UmbralNivel(
      nivel: _asInt(map['nivel'], 0),
      nombre: map['nombre'] is String ? map['nombre'] as String : 'Nivel',
      minimo: map['minimo'] is num ? map['minimo'] as num : 0,
      maximo: map['maximo'] is num ? map['maximo'] as num : 0,
      severidad: map['severidad'] is String ? map['severidad'] as String : '',
    );
  }
}

class UmbralMetrica {
  const UmbralMetrica({
    required this.key,
    required this.niveles,
  });

  final String key;
  final List<UmbralNivel> niveles;

  String get titulo {
    return switch (key) {
      'aqi' => 'Índice de calidad del aire (AQI)',
      'temperatura' => 'Temperatura',
      'humedad' => 'Humedad',
      'pm25' => 'PM2.5',
      'pm10' => 'PM10',
      'co' => 'Monóxido de carbono',
      'no2' => 'Dióxido de nitrógeno',
      'o3' => 'Ozono',
      'so2' => 'Dióxido de azufre',
      _ => key,
    };
  }

  factory UmbralMetrica.fromJson(String key, Object? value) {
    final niveles = value is List
        ? value.map(UmbralNivel.fromJson).toList(growable: false)
        : const <UmbralNivel>[];
    return UmbralMetrica(key: key, niveles: niveles);
  }
}

class UmbralesCatalog {
  const UmbralesCatalog({
    required this.metricas,
    this.nota,
  });

  final List<UmbralMetrica> metricas;
  final String? nota;

  factory UmbralesCatalog.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['metricas'];
    final metricas = <UmbralMetrica>[];
    if (raw is Map) {
      for (final entry in raw.entries) {
        metricas.add(UmbralMetrica.fromJson('${entry.key}', entry.value));
      }
    }
    return UmbralesCatalog(
      metricas: metricas,
      nota: map['nota'] is String ? map['nota'] as String : null,
    );
  }
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

int _asInt(Object? value, int fallback) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? fallback;
  return fallback;
}

DateTime? _asDate(Object? value) {
  if (value is! String || value.isEmpty) return null;
  return DateTime.tryParse(value);
}
