import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/models/nav_menu.dart';
import '../../account/view/account_screen.dart';
import '../../alerts/model/aviso_local_notifications.dart';
import '../../alerts/model/avisos_poller.dart';
import '../../alerts/view/alerts_screen.dart';
import '../../auth/model/auth_repository.dart';
import '../../consultation/view/environmental_consultation_screen.dart';
import '../../monitoring/view/monitoring_screen.dart';

/// Shell móvil según UIKits Navigation / Material U (`NavigationBar` + IndexedStack).
class HomeShell extends StatefulWidget {
  const HomeShell({
    required this.apiClient,
    required this.auth,
    required this.user,
    required this.onSignOut,
    required this.onUnauthorized,
    required this.onUserChanged,
    super.key,
  });

  final GeoCruzApiClient apiClient;
  final AuthRepository auth;
  final GeoSurUser user;
  final Future<void> Function() onSignOut;
  final VoidCallback onUnauthorized;
  final ValueChanged<GeoSurUser> onUserChanged;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _selectedIndex = 0;
  final Set<int> _visitedTabs = {0};
  late final AvisosPoller _avisosPoller;

  static const _destinations = <UiKitNavMenu>[
    UiKitNavMenu('Mapa', Icons.public_outlined, Icons.public),
    UiKitNavMenu('Consultas', Icons.travel_explore_outlined, Icons.travel_explore),
    UiKitNavMenu('Cuenta', Icons.account_circle_outlined, Icons.account_circle),
  ];

  @override
  void initState() {
    super.initState();
    _avisosPoller = AvisosPoller(api: widget.apiClient);
    _bootstrapNotifications();
  }

  Future<void> _bootstrapNotifications() async {
    await AvisoLocalNotifications.instance.init(
      onOpenAviso: (_) {
        if (!mounted) return;
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => AlertsScreen(
              api: widget.apiClient,
              user: widget.user,
              onUnauthorized: widget.onUnauthorized,
            ),
          ),
        );
      },
    );
    _avisosPoller.start();
  }

  @override
  void dispose() {
    _avisosPoller.stop();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      _visitedTabs.contains(0)
          ? MonitoringScreen(
              key: const PageStorageKey('geosur-map'),
              api: widget.apiClient,
              onUnauthorized: widget.onUnauthorized,
            )
          : const SizedBox.shrink(),
      _visitedTabs.contains(1)
          ? EnvironmentalConsultationScreen(
              key: const PageStorageKey('geosur-consultas'),
              apiClient: widget.apiClient,
            )
          : const SizedBox.shrink(),
      _visitedTabs.contains(2)
          ? AccountScreen(
              key: const PageStorageKey('geosur-account'),
              auth: widget.auth,
              api: widget.apiClient,
              user: widget.user,
              onSignOut: widget.onSignOut,
              onUnauthorized: widget.onUnauthorized,
              onUserChanged: widget.onUserChanged,
            )
          : const SizedBox.shrink(),
    ];

    return Scaffold(
      body: SafeArea(
        top: true,
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: NavigationBar(
        height: 64,
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) => setState(() {
          _selectedIndex = index;
          _visitedTabs.add(index);
        }),
        destinations: [
          for (final item in _destinations)
            NavigationDestination(
              icon: Icon(item.icon),
              selectedIcon: Icon(item.selectedIcon),
              label: item.title,
              tooltip: item.title,
            ),
        ],
      ),
    );
  }
}
