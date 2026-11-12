import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/models/nav_menu.dart';
import '../../drought/view/drought_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';
import '../../inundations/view/inundations_screen.dart';

/// Consultas con selector móvil; ambas vistas viven en IndexedStack para
/// no recargar al cambiar de Focos ↔ Sequías.
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

  static const _sections = <UiKitNavMenu>[
    UiKitNavMenu(
      'Focos',
      Icons.local_fire_department_outlined,
      Icons.local_fire_department,
    ),
    UiKitNavMenu('Sequías', Icons.water_drop_outlined, Icons.water_drop),
    UiKitNavMenu('Agua', Icons.water_drop_outlined, Icons.water_drop),
  ];

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Consultas'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: SegmentedButton<int>(
              segments: [
                for (var i = 0; i < _sections.length; i++)
                  ButtonSegment<int>(
                    value: i,
                    icon: Icon(
                      _index == i
                          ? _sections[i].selectedIcon
                          : _sections[i].icon,
                    ),
              label: Text(
                _sections[i].title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
                  ),
              ],
              selected: {_index},
              onSelectionChanged: (values) => setState(() {
                _index = values.first;
                _visitedSections.add(_index);
              }),
              style: ButtonStyle(
                visualDensity: VisualDensity.compact,
                backgroundColor: WidgetStateProperty.resolveWith((states) {
                  if (states.contains(WidgetState.selected)) {
                    return scheme.primaryContainer;
                  }
                  return scheme.surfaceContainerHighest;
                }),
              ),
            ),
          ),
        ),
      ),
      body: IndexedStack(
        index: _index,
        sizing: StackFit.expand,
        children: [
          _visitedSections.contains(0)
              ? HotspotsScreen(
                  key: const PageStorageKey('env-hotspots'),
                  apiClient: widget.apiClient,
                  showAppBar: false,
                )
              : const SizedBox.shrink(),
          _visitedSections.contains(1)
              ? DroughtScreen(
                  key: const PageStorageKey('env-drought'),
                  apiClient: widget.apiClient,
                  showAppBar: false,
                )
              : const SizedBox.shrink(),
          _visitedSections.contains(2)
              ? InundationsScreen(
                  key: const PageStorageKey('env-inundations'),
                  api: widget.apiClient,
                  onUnauthorized: widget.onUnauthorized,
                  showAppBar: false,
                )
              : const SizedBox.shrink(),
        ],
      ),
    );
  }
}
