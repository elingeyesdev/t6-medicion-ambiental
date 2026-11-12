class ReportPage {
  const ReportPage({
    required this.items,
    required this.page,
    required this.lastPage,
  });

  final List<ReportSummary> items;
  final int page;
  final int lastPage;

  bool get hasMore => page < lastPage;

  factory ReportPage.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['datos'];
    final items = raw is List
        ? raw.map(ReportSummary.fromJson).toList(growable: false)
        : const <ReportSummary>[];
    return ReportPage(
      items: items,
      page: _asInt(map['pagina'], 1),
      lastPage: _asInt(map['ultimaPagina'], 1),
    );
  }
}

class ReportSummary {
  const ReportSummary({
    required this.id,
    required this.title,
    this.createdAt,
  });

  final String id;
  final String title;
  final DateTime? createdAt;

  factory ReportSummary.fromJson(Object? value) {
    final map = _asMap(value);
    return ReportSummary(
      id: map['id']?.toString() ?? '',
      title: map['titulo'] is String ? map['titulo'] as String : 'Sin título',
      createdAt: _asDate(map['created_at']),
    );
  }
}

class ReportDetail {
  const ReportDetail({
    required this.id,
    required this.title,
    required this.parameters,
    required this.content,
    this.createdAt,
  });

  final String id;
  final String title;
  final Map<String, dynamic> parameters;
  final Map<String, dynamic> content;
  final DateTime? createdAt;

  String get baseLabel {
    final value = parameters['base'];
    return value is String && value.isNotEmpty ? value : '—';
  }

  String get metricLabel {
    final value = parameters['metrica'] ?? parameters['variable'];
    return value is String && value.isNotEmpty ? value : '—';
  }

  int get readingCount {
    final readings = content['lecturas'] ?? content['filas'];
    return readings is List ? readings.length : 0;
  }

  String get summaryLine {
    final parts = <String>[
      if (baseLabel != '—') 'Base: $baseLabel',
      if (metricLabel != '—') 'Métrica: $metricLabel',
      if (readingCount > 0) 'Filas: $readingCount',
    ];
    return parts.isEmpty ? 'Sin parámetros resumibles' : parts.join(' · ');
  }

  factory ReportDetail.fromJson(Object? value) {
    final map = _asMap(value);
    return ReportDetail(
      id: map['id']?.toString() ?? '',
      title: map['titulo'] is String ? map['titulo'] as String : 'Sin título',
      parameters: _asMap(map['parametros']),
      content: _asMap(map['contenido']),
      createdAt: _asDate(map['created_at']),
    );
  }
}

class ReportTemplate {
  const ReportTemplate({
    required this.id,
    required this.name,
    required this.configuration,
    this.deletedAt,
  });

  final String id;
  final String name;
  final Map<String, dynamic> configuration;
  final DateTime? deletedAt;

  bool get isArchived => deletedAt != null;

  String get configSummary {
    final tipo = configuration['tipo'];
    final columnas = configuration['columnas'];
    final parts = <String>[
      if (tipo is String && tipo.isNotEmpty) 'Tipo: $tipo',
      if (columnas is List) 'Columnas: ${columnas.length}',
    ];
    return parts.isEmpty ? 'Plantilla guardada' : parts.join(' · ');
  }

  factory ReportTemplate.fromJson(Object? value) {
    final map = _asMap(value);
    return ReportTemplate(
      id: map['id']?.toString() ?? '',
      name: map['nombre'] is String ? map['nombre'] as String : 'Sin nombre',
      configuration: _asMap(map['configuracion']),
      deletedAt: _asDate(map['deleted_at']),
    );
  }
}

class TemplatePage {
  const TemplatePage({
    required this.items,
    required this.page,
    required this.lastPage,
  });

  final List<ReportTemplate> items;
  final int page;
  final int lastPage;

  factory TemplatePage.fromJson(Object? value) {
    final map = _asMap(value);
    final raw = map['datos'];
    final items = raw is List
        ? raw.map(ReportTemplate.fromJson).toList(growable: false)
        : const <ReportTemplate>[];
    return TemplatePage(
      items: items,
      page: _asInt(map['pagina'], 1),
      lastPage: _asInt(map['ultimaPagina'], 1),
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
