import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../auth/model/auth_repository.dart';
import '../../account/view/account_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';
import '../../monitoring/view/monitoring_screen.dart';

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
          ? HotspotsScreen(
              key: const PageStorageKey('geosur-hotspots'),
              apiClient: widget.apiClient,
            )
          : const SizedBox.shrink(),
      _visitedTabs.contains(2)
          ? AccountScreen(
              key: const PageStorageKey('geosur-account'),
              auth: widget.auth,
              user: widget.user,
              onSignOut: widget.onSignOut,
              onUserChanged: widget.onUserChanged,
            )
          : const SizedBox.shrink(),
    ];
    return Scaffold(
      body: SafeArea(
        top: false,
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) => setState(() {
          _selectedIndex = index;
          _visitedTabs.add(index);
        }),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.public_outlined),
            selectedIcon: Icon(Icons.public),
            label: 'Mapa',
          ),
          NavigationDestination(
            icon: Icon(Icons.local_fire_department_outlined),
            selectedIcon: Icon(Icons.local_fire_department),
            label: 'Focos',
          ),
          NavigationDestination(
            icon: Icon(Icons.account_circle_outlined),
            selectedIcon: Icon(Icons.account_circle),
            label: 'Cuenta',
          ),
        ],
      ),
    );
  }
}
