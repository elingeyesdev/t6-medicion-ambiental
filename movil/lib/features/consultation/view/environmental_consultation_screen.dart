import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../drought/view/drought_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';

class EnvironmentalConsultationScreen extends StatelessWidget {
  const EnvironmentalConsultationScreen({
    required this.apiClient,
    super.key,
  });

  final GeoCruzApiClient apiClient;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text(
            'Consultas ambientales',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
          ),
          bottom: const TabBar(
            indicatorColor: GeoCruzColors.green,
            labelColor: GeoCruzColors.green,
            unselectedLabelColor: Colors.black54,
            tabs: [
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
          children: [
            HotspotsScreen(
              key: const PageStorageKey('env-hotspots'),
              apiClient: apiClient,
              showAppBar: false,
            ),
            DroughtScreen(
              key: const PageStorageKey('env-drought'),
              apiClient: apiClient,
              showAppBar: false,
            ),
          ],
        ),
      ),
    );
  }
}
