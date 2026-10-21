class ConsultaContext {
  const ConsultaContext({
    required this.moduleId,
    required this.territoryId,
    required this.territoryName,
    this.from,
    this.untilExclusive,
    this.recordId,
    this.querySetVersion,
  });

  final String moduleId;
  final String territoryId;
  final String territoryName;
  final DateTime? from;
  final DateTime? untilExclusive;
  final String? recordId;
  final String? querySetVersion;

  ConsultaContext withRecord({
    required String recordId,
    String? querySetVersion,
  }) {
    return ConsultaContext(
      moduleId: moduleId,
      territoryId: territoryId,
      territoryName: territoryName,
      from: from,
      untilExclusive: untilExclusive,
      recordId: recordId,
      querySetVersion: querySetVersion ?? this.querySetVersion,
    );
  }
}

class EnvironmentalRecord {
  const EnvironmentalRecord({
    required this.identifier,
    required this.attributes,
    this.observedAt,
    this.source,
    this.nature,
  });

  final String identifier;
  final Map<String, Object?> attributes;
  final DateTime? observedAt;
  final String? source;
  final String? nature;
}
