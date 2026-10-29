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

// Solo se anuncian recorridos móviles que ya consultan datos reales.
const ambientModules = <AmbientModule>[
  AmbientModule(
    id: 'focos-calor',
    name: 'Focos de calor',
    summary:
        'Consulta de detecciones térmicas con su fuente, período y contexto.',
    icon: Icons.local_fire_department_outlined,
    availability: ModuleAvailability.available,
  ),
];
