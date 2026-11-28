import 'package:flutter/material.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/models/nav_menu.dart';
import '../../drought/view/drought_screen.dart';
import '../../forestal/view/forestal_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';
import '../../inundations/view/inundations_screen.dart';

/// Consultas ambientales en una única navegación. Las pantallas visitadas se
/// conservan para no volver a cargar sus datos al cambiar de módulo.
class EnvironmentalConsultationScreen extends StatefulWidget {
  const EnvironmentalConsultationScreen({
    required this.apiClient,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient apiClient;
  final VoidCallback onUnauthorized;

  @override
  State<EnvironmentalConsultationScreen> createState() =>
      _EnvironmentalConsultationScreenState();
}

class _EnvironmentalConsultationScreenState
    extends State<EnvironmentalConsultationScreen> {
  int _index = 0;
  final Set<int> _visitedSections = {0};
  String _territoryId = 'BO07';
  MapBaseStyle _mapStyle = MapBaseStyle.relieve;

  void _rememberTerritory(String id) {
    if (_territoryId != id) setState(() => _territoryId = id);
  }

  void _rememberMapStyle(MapBaseStyle style) {
    if (_mapStyle != style) setState(() => _mapStyle = style);
  }

  static const _publishedSections = <UiKitNavMenu>[
    UiKitNavMenu(
      'Focos de calor',
      Icons.local_fire_department_outlined,
      Icons.local_fire_department,
    ),
    UiKitNavMenu('Sequías', Icons.water_drop_outlined, Icons.water_drop),
    UiKitNavMenu('Inundaciones', Icons.flood_outlined, Icons.flood),
  ];
  static const _forestalPilot = UiKitNavMenu(
    'Forestal · piloto',
    Icons.forest_outlined,
    Icons.forest,
  );

  static const _forestalEnabled = bool.fromEnvironment(
    'GEOSUR_FORESTAL_PILOTO',
  );

  List<UiKitNavMenu> get _sections => [
    ..._publishedSections,
    if (_forestalEnabled) _forestalPilot,
  ];

  void _selectSection(int index) {
    if (index == _index) return;
    setState(() {
      _index = index;
      _visitedSections.add(index);
    });
  }

  String? get _scopeNotice => switch (_index) {
    1 =>
      'Sequías usa meses completos; conserva su propio mes final. El lugar se comparte con Focos.',
    2 =>
      'Inundaciones solo permite lugares y pares de fechas publicados. Revisa la selección antes de consultar.',
    3 =>
      'El piloto forestal solo cubre Santa Rosa y usa años; los filtros de otros módulos no se aplican.',
    _ => null,
  };

  Widget _sectionButton(BuildContext context, int index) {
    final selected = _index == index;
    final section = _sections[index];
    final scheme = Theme.of(context).colorScheme;
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.all(2),
        child: TextButton.icon(
          onPressed: () => _selectSection(index),
          icon: Icon(selected ? section.selectedIcon : section.icon, size: 20),
          label: Text(
            section.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          style: TextButton.styleFrom(
            foregroundColor: selected
                ? scheme.primary
                : scheme.onSurfaceVariant,
            backgroundColor: selected ? scheme.primaryContainer : null,
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Consultas ambientales'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(96),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
            child: Column(
              children: [
                Row(
                  children: [
                    _sectionButton(context, 0),
                    _sectionButton(context, 1),
                  ],
                ),
                Row(
                  children: [
                    _sectionButton(context, 2),
                    if (_forestalEnabled) _sectionButton(context, 3),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          if (_scopeNotice case final notice?)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      notice,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: IndexedStack(
              index: _index,
              sizing: StackFit.expand,
              children: [
                _visitedSections.contains(0)
                    ? HotspotsScreen(
                        onUnauthorized: widget.onUnauthorized,
                        key: const PageStorageKey('env-hotspots'),
                        apiClient: widget.apiClient,
                        showAppBar: false,
                        active: _index == 0,
                        sharedTerritoryId: _territoryId,
                        onTerritoryChanged: _rememberTerritory,
                        sharedMapStyle: _mapStyle,
                        onMapStyleChanged: _rememberMapStyle,
                      )
                    : const SizedBox.shrink(),
                _visitedSections.contains(1)
                    ? DroughtScreen(
                        onUnauthorized: widget.onUnauthorized,
                        key: const PageStorageKey('env-drought'),
                        apiClient: widget.apiClient,
                        showAppBar: false,
                        active: _index == 1,
                        sharedTerritoryId: _territoryId,
                        onTerritoryChanged: _rememberTerritory,
                        sharedMapStyle: _mapStyle,
                        onMapStyleChanged: _rememberMapStyle,
                      )
                    : const SizedBox.shrink(),
                _visitedSections.contains(2)
                    ? InundationsScreen(
                        key: const PageStorageKey('env-inundations'),
                        api: widget.apiClient,
                        onUnauthorized: widget.onUnauthorized,
                        showAppBar: false,
                        active: _index == 2,
                        sharedTerritoryId: _territoryId,
                        onTerritoryChanged: _rememberTerritory,
                        sharedMapStyle: _mapStyle,
                        onMapStyleChanged: _rememberMapStyle,
                      )
                    : const SizedBox.shrink(),
                if (_forestalEnabled)
                  _visitedSections.contains(3)
                      ? ForestalScreen(
                          key: const PageStorageKey('env-forestal'),
                          api: widget.apiClient,
                          onUnauthorized: widget.onUnauthorized,
                          showAppBar: false,
                          sharedMapStyle: _mapStyle,
                          onMapStyleChanged: _rememberMapStyle,
                        )
                      : const SizedBox.shrink(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
