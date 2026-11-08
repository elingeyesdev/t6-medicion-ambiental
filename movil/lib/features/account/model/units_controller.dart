import 'package:flutter/foundation.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/units/units_presentation.dart';
import 'account_repository.dart';

/// Estado compartido de unidades (UIKits cuenta + conversión en mapa).
class UnitsController extends ChangeNotifier {
  UnitsController(this._repository);

  final AccountRepository _repository;
  UnitsPresentation _presentation = UnitsPresentation(
    catalog: UnitsCatalog.empty,
  );
  bool _loading = false;
  bool _ready = false;
  String? _error;

  UnitsPresentation get presentation => _presentation;
  bool get loading => _loading;
  bool get ready => _ready;
  String? get error => _error;

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final snapshot = await _repository.load();
      final prefs = snapshot.preferences.units.isEmpty
          ? UnitsPresentation(catalog: snapshot.catalog).defaults()
          : snapshot.preferences.units;
      _presentation = UnitsPresentation(
        catalog: snapshot.catalog,
        preferences: prefs,
      );
      _ready = true;
    } on GeoCruzApiException catch (error) {
      _error = error.message;
    } catch (_) {
      _error = 'No se pudieron cargar las unidades.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  void applySnapshot({
    required UnitsCatalog catalog,
    required Map<String, String> units,
  }) {
    _presentation = UnitsPresentation(catalog: catalog, preferences: units);
    _ready = true;
    notifyListeners();
  }

  void applyPreferences(Map<String, String> units) {
    _presentation.setPreferences(units);
    notifyListeners();
  }

  Future<Map<String, String>> saveUnits(Map<String, String> units) async {
    final saved = await _repository.saveUnits(units);
    applyPreferences(saved);
    return saved;
  }
}
