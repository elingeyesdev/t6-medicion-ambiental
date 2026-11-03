import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../drought/view/drought_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';

class EnvironmentalConsultationScreen extends StatefulWidget {
  const EnvironmentalConsultationScreen({
    required this.apiClient,
    super.key,
  });

  final GeoCruzApiClient apiClient;

  @override
  State<EnvironmentalConsultationScreen> createState() =>
      _EnvironmentalConsultationScreenState();
}

class _EnvironmentalConsultationScreenState
    extends State<EnvironmentalConsultationScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  bool _openedDrought = false;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _tabs.addListener(_onTab);
  }

  void _onTab() {
    if (_tabs.index == 1 && !_openedDrought) {
      setState(() => _openedDrought = true);
    }
  }

  @override
  void dispose() {
    _tabs.removeListener(_onTab);
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Consultas ambientales',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        bottom: TabBar(
          controller: _tabs,
          indicatorColor: GeoCruzColors.green,
          labelColor: GeoCruzColors.green,
          unselectedLabelColor: Colors.black54,
          tabs: const [
            Tab(
              icon: Icon(Icons.local_fire_department_outlined),
              text: 'Focos de calor',
            ),
            Tab(
              icon: Icon(Icons.water_drop_outlined),
              text: 'Sequías',
            ),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          HotspotsScreen(
            key: const PageStorageKey('env-hotspots'),
            apiClient: widget.apiClient,
            showAppBar: false,
          ),
          _openedDrought
              ? DroughtScreen(
                  key: const PageStorageKey('env-drought'),
                  apiClient: widget.apiClient,
                  showAppBar: false,
                )
              : const SizedBox.shrink(),
        ],
      ),
    );
  }
}
