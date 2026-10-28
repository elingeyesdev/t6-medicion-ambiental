import 'package:flutter/material.dart';

enum ModuleAvailability { available, mobileInProgress, comingSoon }

class AmbientModule {
  const AmbientModule({
    required this.id,
    required this.name,
    required this.summary,
    required this.icon,
    required this.availability,
  });

  final String id;
  final String name;
  final String summary;
  final IconData icon;
  final ModuleAvailability availability;

  String get statusLabel => switch (availability) {
    ModuleAvailability.available => 'Disponible',
    ModuleAvailability.mobileInProgress => 'Próximamente',
    ModuleAvailability.comingSoon => 'Próximamente',
  };
}

const ambientModules = <AmbientModule>[
  AmbientModule(
    id: 'focos-calor',
    name: 'Focos de calor',
    summary:
        'Consulta de detecciones térmicas con su fuente, período y contexto.',
    icon: Icons.local_fire_department_outlined,
    availability: ModuleAvailability.available,
  ),
  AmbientModule(
    id: 'sequias',
    name: 'Sequías',
    summary:
        'Variables ambientales y evaluación cuando exista un método acordado.',
    icon: Icons.water_drop_outlined,
    availability: ModuleAvailability.comingSoon,
  ),
  AmbientModule(
    id: 'inundaciones',
    name: 'Inundaciones',
    summary:
        'Presencia o cambio de agua, sin confundirlo con una inundación confirmada.',
    icon: Icons.waves_outlined,
    availability: ModuleAvailability.comingSoon,
  ),
  AmbientModule(
    id: 'deforestacion',
    name: 'Deforestación',
    summary:
        'Pérdida de cobertura forestal según el producto y los años disponibles.',
    icon: Icons.forest_outlined,
    availability: ModuleAvailability.comingSoon,
  ),
  AmbientModule(
    id: 'calidad-aire',
    name: 'Calidad del aire',
    summary:
        'Concentraciones disponibles, identificando si son observadas o modeladas.',
    icon: Icons.air_outlined,
    availability: ModuleAvailability.comingSoon,
  ),
];
