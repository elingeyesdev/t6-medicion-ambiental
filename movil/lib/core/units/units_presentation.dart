/// Presentación de unidades alineada con `compartido/unidades` y la API.
/// La API sigue entregando valores en unidad base; Flutter solo convierte al mostrar.
class UnitOption {
  const UnitOption({
    required this.code,
    required this.name,
    required this.symbol,
    required this.factor,
    required this.divisor,
    required this.offset,
    required this.decimals,
  });

  final String code;
  final String name;
  final String symbol;
  final double factor;
  final double divisor;
  final double offset;
  final int decimals;

  factory UnitOption.fromJson(String code, Map<String, dynamic> json) =>
      UnitOption(
        code: code,
        name: '${json['nombre'] ?? code}',
        symbol: '${json['simbolo'] ?? code}',
        factor: (json['factor'] as num?)?.toDouble() ?? 1,
        divisor: (json['divisor'] as num?)?.toDouble() ?? 1,
        offset: (json['desplazamiento'] as num?)?.toDouble() ?? 0,
        decimals: (json['decimales'] as num?)?.toInt() ?? 2,
      );
}

class UnitGroup {
  const UnitGroup({
    required this.key,
    required this.name,
    required this.base,
    required this.variables,
    required this.defaultCode,
    required this.options,
  });

  final String key;
  final String name;
  final String base;
  final List<String> variables;
  final String defaultCode;
  final Map<String, UnitOption> options;

  factory UnitGroup.fromJson(String key, Map<String, dynamic> json) {
    final rawOptions = json['opciones'];
    final options = <String, UnitOption>{};
    if (rawOptions is Map) {
      for (final entry in rawOptions.entries) {
        final value = entry.value;
        if (value is Map) {
          options['${entry.key}'] = UnitOption.fromJson(
            '${entry.key}',
            value.map((k, v) => MapEntry('$k', v)),
          );
        }
      }
    }
    final variables = json['variables'];
    return UnitGroup(
      key: key,
      name: '${json['nombre'] ?? key}',
      base: '${json['base'] ?? ''}',
      variables: variables is List
          ? variables.map((e) => '$e').toList(growable: false)
          : const [],
      defaultCode: '${json['defecto'] ?? ''}',
      options: options,
    );
  }
}

class UnitsCatalog {
  const UnitsCatalog(this.groups);

  final Map<String, UnitGroup> groups;

  factory UnitsCatalog.fromJson(Object? value) {
    final map = _asMap(value);
    final groups = <String, UnitGroup>{};
    for (final entry in map.entries) {
      final group = entry.value;
      if (group is Map) {
        groups[entry.key] = UnitGroup.fromJson(
          entry.key,
          group.map((k, v) => MapEntry('$k', v)),
        );
      }
    }
    return UnitsCatalog(groups);
  }

  static const empty = UnitsCatalog({});
}

class UnitsPresentation {
  UnitsPresentation({
    required this.catalog,
    Map<String, String> preferences = const {},
  }) : _preferences = Map<String, String>.from(preferences);

  final UnitsCatalog catalog;
  Map<String, String> _preferences;

  Map<String, String> get preferences => Map.unmodifiable(_preferences);

  void setPreferences(Map<String, String> value) {
    _preferences = Map<String, String>.from(value);
  }

  Map<String, String> defaults() {
    final result = <String, String>{};
    for (final group in catalog.groups.values) {
      result[group.key] = group.defaultCode;
    }
    return result;
  }

  UnitOption? optionFor(String metric, String base) {
    for (final group in catalog.groups.values) {
      if (group.base == base && group.variables.contains(metric)) {
        final chosen = _preferences[group.key] ?? group.defaultCode;
        return group.options[chosen] ?? group.options[group.defaultCode];
      }
    }
    return null;
  }

  double? convert(
    String metric,
    Object? value,
    String base, {
    bool difference = false,
  }) {
    if (value is! num) return null;
    final number = value.toDouble();
    if (!number.isFinite) return null;
    final option = optionFor(metric, base);
    if (option == null) return number;
    return number * option.factor / option.divisor +
        (difference ? 0 : option.offset);
  }

  String symbol(String metric, String base) =>
      optionFor(metric, base)?.symbol ?? base;

  String format(
    String metric,
    Object? value,
    String base, {
    bool difference = false,
  }) {
    final converted = convert(metric, value, base, difference: difference);
    if (converted == null) return 'No disponible';
    final option = optionFor(metric, base);
    final decimals = option?.decimals ?? 2;
    final threshold = 0.5 * _pow10(-decimals);
    final normalized = converted.abs() < threshold ? 0.0 : converted;
    final text = _formatNumber(normalized, decimals);
    final unit = option?.symbol ?? base;
    return '$text $unit'.trim();
  }

  static double _pow10(int exp) {
    var result = 1.0;
    if (exp >= 0) {
      for (var i = 0; i < exp; i++) {
        result *= 10;
      }
    } else {
      for (var i = 0; i < -exp; i++) {
        result /= 10;
      }
    }
    return result;
  }

  static String _formatNumber(double value, int decimals) {
    final fixed = value.toStringAsFixed(decimals);
    if (!fixed.contains('.')) return fixed;
    var trimmed = fixed;
    while (trimmed.contains('.') &&
        (trimmed.endsWith('0') || trimmed.endsWith('.'))) {
      if (trimmed.endsWith('.')) {
        trimmed = trimmed.substring(0, trimmed.length - 1);
        break;
      }
      trimmed = trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed.replaceAll('.', ',');
  }
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return const {};
}
