class AccountPreferences {
  const AccountPreferences({
    this.latitude,
    this.longitude,
    this.notifyEmail = false,
    this.notifyWhatsapp = false,
    this.whatsappDestination,
    this.notifyTelegram = false,
    this.telegramDestination,
    this.units = const {},
  });

  final double? latitude;
  final double? longitude;
  final bool notifyEmail;
  final bool notifyWhatsapp;
  final String? whatsappDestination;
  final bool notifyTelegram;
  final String? telegramDestination;
  final Map<String, String> units;

  bool get hasLocation => latitude != null && longitude != null;

  factory AccountPreferences.fromJson(Object? value) {
    final json = _asMap(value);
    final unitsRaw = json['unidades'];
    final units = <String, String>{};
    if (unitsRaw is Map) {
      for (final entry in unitsRaw.entries) {
        if (entry.value is String) units['${entry.key}'] = entry.value as String;
      }
    }
    return AccountPreferences(
      latitude: (json['latitud'] as num?)?.toDouble(),
      longitude: (json['longitud'] as num?)?.toDouble(),
      notifyEmail: json['notif_email'] == true,
      notifyWhatsapp: json['notif_whatsapp'] == true,
      whatsappDestination: _optionalString(json['whatsapp_destino']),
      notifyTelegram: json['notif_telegram'] == true,
      telegramDestination: _optionalString(json['telegram_destino']),
      units: units,
    );
  }

  Map<String, Object?> toPreferencesBody() => {
    'latitud': latitude,
    'longitud': longitude,
    'notif_email': notifyEmail,
    'notif_whatsapp': notifyWhatsapp,
    'whatsapp_destino': whatsappDestination,
    'notif_telegram': notifyTelegram,
    'telegram_destino': telegramDestination,
  };

  AccountPreferences copyWith({
    double? latitude,
    double? longitude,
    bool clearLocation = false,
    bool? notifyEmail,
    bool? notifyWhatsapp,
    String? whatsappDestination,
    bool? notifyTelegram,
    String? telegramDestination,
    Map<String, String>? units,
  }) => AccountPreferences(
    latitude: clearLocation ? null : (latitude ?? this.latitude),
    longitude: clearLocation ? null : (longitude ?? this.longitude),
    notifyEmail: notifyEmail ?? this.notifyEmail,
    notifyWhatsapp: notifyWhatsapp ?? this.notifyWhatsapp,
    whatsappDestination: whatsappDestination ?? this.whatsappDestination,
    notifyTelegram: notifyTelegram ?? this.notifyTelegram,
    telegramDestination: telegramDestination ?? this.telegramDestination,
    units: units ?? this.units,
  );
}

String? _optionalString(Object? value) {
  if (value is! String) return null;
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

Map<String, dynamic> _asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return const {};
}
