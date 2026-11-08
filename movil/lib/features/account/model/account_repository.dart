import '../../../core/network/geocruz_api_client.dart';
import '../../../core/units/units_presentation.dart';
import 'account_preferences.dart';

class AccountSnapshot {
  const AccountSnapshot({
    required this.preferences,
    required this.catalog,
  });

  final AccountPreferences preferences;
  final UnitsCatalog catalog;
}

class AccountRepository {
  AccountRepository(this.api);

  final GeoCruzApiClient api;

  Future<AccountSnapshot> load() async {
    final payload = await api.getJson('cuenta', authenticated: true);
    final map = _asMap(payload);
    final preferences = AccountPreferences.fromJson(map['preferencias']);

    UnitsCatalog catalog = UnitsCatalog.empty;
    try {
      final unitsPayload = await api.getJson(
        'cuenta/unidades',
        authenticated: true,
      );
      final unitsMap = _asMap(unitsPayload);
      catalog = UnitsCatalog.fromJson(unitsMap['catalogo']);
      final units = unitsMap['unidades'];
      if (units is Map) {
        final merged = Map<String, String>.from(preferences.units);
        for (final entry in units.entries) {
          if (entry.value is String) merged['${entry.key}'] = entry.value as String;
        }
        return AccountSnapshot(
          preferences: preferences.copyWith(units: merged),
          catalog: catalog,
        );
      }
    } catch (_) {
      // Si falla el catálogo, igual devolvemos preferencias de cuenta.
    }

    return AccountSnapshot(preferences: preferences, catalog: catalog);
  }

  Future<AccountPreferences> savePreferences(AccountPreferences prefs) async {
    final payload = await api.putJson(
      'cuenta/preferencias',
      body: prefs.toPreferencesBody(),
      authenticated: true,
    );
    return AccountPreferences.fromJson(_asMap(payload)['preferencias']);
  }

  Future<Map<String, String>> saveUnits(Map<String, String> units) async {
    final payload = await api.putJson(
      'cuenta/unidades',
      body: {'unidades': units},
      authenticated: true,
    );
    final raw = _asMap(payload)['unidades'];
    final result = <String, String>{};
    if (raw is Map) {
      for (final entry in raw.entries) {
        if (entry.value is String) result['${entry.key}'] = entry.value as String;
      }
    }
    return result;
  }

  static Map<String, dynamic> _asMap(Object? value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry('$k', v));
    return const {};
  }
}
