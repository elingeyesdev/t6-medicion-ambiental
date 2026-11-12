class SimulationPage {
  const SimulationPage({
    required this.items,
    required this.page,
    required this.lastPage,
  });

  final List<SimulationSummary> items;
  final int page;
  final int lastPage;

  bool get hasMore => page < lastPage;

  factory SimulationPage.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['datos'];
    final items = raw is List
        ? raw.map(SimulationSummary.fromJson).toList(growable: false)
        : const <SimulationSummary>[];
    return SimulationPage(
      items: items,
      page: _asInt(map['pagina'], 1),
      lastPage: _asInt(map['ultimaPagina'], 1),
    );
  }
}

class SimulationSummary {
  const SimulationSummary({
    required this.id,
    required this.name,
    required this.status,
    this.createdAt,
  });

  final String id;
  final String name;
  final String status;
  final DateTime? createdAt;

  factory SimulationSummary.fromJson(Object? value) {
    final map = _asMap(value);
    return SimulationSummary(
      id: map['id']?.toString() ?? '',
      name: map['nombre'] is String ? map['nombre'] as String : 'Sin nombre',
      status: map['estado'] is String ? map['estado'] as String : 'desconocido',
      createdAt: _asDate(map['created_at']),
    );
  }
}

class SimulationDetail {
  const SimulationDetail({
    required this.id,
    required this.name,
    required this.status,
    required this.position,
    required this.revision,
    required this.configuration,
    required this.result,
  });

  final String id;
  final String name;
  final String status;
  final int position;
  final int revision;
  final Map<String, dynamic> configuration;
  final Map<String, dynamic> result;

  int get lastIndex {
    final instants = result['instantes'];
    if (instants is! List || instants.isEmpty) return 0;
    return instants.length - 1;
  }

  String get metric {
    final value = configuration['metrica'];
    return value is String && value.isNotEmpty ? value : '—';
  }

  int get days {
    final value = configuration['dias'];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return 0;
  }

  int get zoneCount {
    final zones = configuration['zonas'];
    return zones is List ? zones.length : 0;
  }

  String get configSummary {
    final parts = <String>[
      if (metric != '—') 'Métrica: $metric',
      if (days > 0) 'Días: $days',
      if (zoneCount > 0) 'Zonas: $zoneCount',
    ];
    return parts.isEmpty ? 'Sin configuración resumible' : parts.join(' · ');
  }

  bool get canPlay =>
      status == 'lista' || status == 'pausada' || status == 'detenida';

  bool get canPause => status == 'activa';

  bool get canStop => status == 'activa';

  bool get canRestart =>
      status == 'activa' ||
      status == 'pausada' ||
      status == 'detenida' ||
      status == 'finalizada' ||
      status == 'lista';

  bool get canStep => status != 'activa' && lastIndex > 0;

  factory SimulationDetail.fromJson(Object? value) {
    final map = _asMap(value);
    return SimulationDetail(
      id: map['id']?.toString() ?? '',
      name: map['nombre'] is String ? map['nombre'] as String : 'Sin nombre',
      status: map['estado'] is String ? map['estado'] as String : 'desconocido',
      position: _asInt(map['posicion'], 0),
      revision: _asInt(map['revision'], 0),
      configuration: _asMap(map['configuracion']),
      result: _asMap(map['resultado']),
    );
  }
}

String simulationStatusLabel(String status) => switch (status) {
  'lista' => 'Lista',
  'activa' => 'Reproduciendo',
  'pausada' => 'Pausada',
  'detenida' => 'Detenida',
  'finalizada' => 'Finalizada',
  _ => status,
};

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
