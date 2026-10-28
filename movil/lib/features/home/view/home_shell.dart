import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../modules/view/module_detail_screen.dart';
import '../../modules/model/ambient_module.dart';
import '../../modules/view/modules_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';
import 'home_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({required this.apiClient, super.key});

  final GeoCruzApiClient apiClient;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _selectedIndex = 0;

  void _openModule(AmbientModule module) {
    if (module.id == 'focos-calor') {
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => HotspotsScreen(apiClient: widget.apiClient),
        ),
      );
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ModuleDetailScreen(module: module),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      HomeScreen(
        onExploreModules: () => setState(() => _selectedIndex = 1),
        onOpenHotspots: () => _openModule(ambientModules.first),
      ),
      ModulesScreen(onModuleSelected: _openModule),
    ];

    return Scaffold(
      body: SafeArea(
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) =>
            setState(() => _selectedIndex = index),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Inicio',
          ),
          NavigationDestination(
            icon: Icon(Icons.grid_view_outlined),
            selectedIcon: Icon(Icons.grid_view_rounded),
            label: 'Módulos',
          ),
        ],
      ),
    );
  }
}
