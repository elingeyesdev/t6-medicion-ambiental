import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../drought/view/drought_screen.dart';
import '../../hotspots/view/hotspots_screen.dart';

enum EnvironmentalModuleType {
  hotspots,
  drought,
}

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
    extends State<EnvironmentalConsultationScreen> {
  EnvironmentalModuleType _currentModule = EnvironmentalModuleType.hotspots;

  void _showModuleSelectionDialog() {
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (ctx) {
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '¿Qué mapa deseas consultar?',
                style: Theme.of(ctx).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Selecciona el módulo de monitoreo ambiental para visualizar en el mapa:',
                style: TextStyle(color: Colors.black54, fontSize: 13),
              ),
              const SizedBox(height: 16),
              ListTile(
                leading: CircleAvatar(
                  backgroundColor: Colors.orange.shade100,
                  child: const Icon(Icons.local_fire_department,
                      color: Colors.deepOrange),
                ),
                title: const Text(
                  'Focos de calor',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: const Text(
                  'Detecciones satelitales NASA FIRMS (VIIRS_SNPP_NRT)',
                  style: TextStyle(fontSize: 12),
                ),
                trailing: _currentModule == EnvironmentalModuleType.hotspots
                    ? const Icon(Icons.check_circle, color: Colors.green)
                    : null,
                onTap: () {
                  Navigator.of(ctx).pop();
                  setState(() => _currentModule = EnvironmentalModuleType.hotspots);
                },
              ),
              const Divider(),
              ListTile(
                leading: CircleAvatar(
                  backgroundColor: Colors.blue.shade100,
                  child: const Icon(Icons.water_drop, color: Colors.blue),
                ),
                title: const Text(
                  'Sequías meteorológicas',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: const Text(
                  'Índice SPI-3 de precipitación acumulada (ERA5 / Open-Meteo)',
                  style: TextStyle(fontSize: 12),
                ),
                trailing: _currentModule == EnvironmentalModuleType.drought
                    ? const Icon(Icons.check_circle, color: Colors.green)
                    : null,
                onTap: () {
                  Navigator.of(ctx).pop();
                  setState(() => _currentModule = EnvironmentalModuleType.drought);
                },
              ),
              const SizedBox(height: 16),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Barra superior con selector rápido de módulo
        Container(
          color: Theme.of(context).colorScheme.surface,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: [
              Expanded(
                child: SegmentedButton<EnvironmentalModuleType>(
                  segments: const [
                    ButtonSegment<EnvironmentalModuleType>(
                      value: EnvironmentalModuleType.hotspots,
                      icon: Icon(Icons.local_fire_department, size: 18),
                      label: Text('Focos de calor',
                          style: TextStyle(fontSize: 12)),
                    ),
                    ButtonSegment<EnvironmentalModuleType>(
                      value: EnvironmentalModuleType.drought,
                      icon: Icon(Icons.water_drop, size: 18),
                      label: Text('Sequías',
                          style: TextStyle(fontSize: 12)),
                    ),
                  ],
                  selected: {_currentModule},
                  onSelectionChanged: (set) {
                    setState(() => _currentModule = set.first);
                  },
                ),
              ),
              const SizedBox(width: 8),
              IconButton(
                tooltip: 'Preguntar qué mapa consultar',
                icon: const Icon(Icons.help_outline, size: 20),
                onPressed: _showModuleSelectionDialog,
              ),
            ],
          ),
        ),
        // Contenido del mapa seleccionado
        Expanded(
          child: _currentModule == EnvironmentalModuleType.hotspots
              ? HotspotsScreen(
                  key: const PageStorageKey('env-hotspots'),
                  apiClient: widget.apiClient,
                )
              : DroughtScreen(
                  key: const PageStorageKey('env-drought'),
                  apiClient: widget.apiClient,
                ),
        ),
      ],
    );
  }
}
