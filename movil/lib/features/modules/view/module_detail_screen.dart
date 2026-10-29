import 'package:flutter/material.dart';

import '../../../core/theme/geocruz_theme.dart';
import '../model/ambient_module.dart';

class ModuleDetailScreen extends StatelessWidget {
  const ModuleDetailScreen({required this.module, super.key});

  final AmbientModule module;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('GeoSur')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 30),
        children: [
          Text(
            'SANTA CRUZ · INFORMACIÓN AMBIENTAL',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: GeoCruzColors.green,
              fontWeight: FontWeight.w800,
              letterSpacing: 1,
            ),
          ),
          const SizedBox(height: 12),
          Text(module.name, style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 8),
          Text(module.summary, style: Theme.of(context).textTheme.bodyLarge),
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: GeoCruzColors.greenSoft,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Icon(module.icon, color: GeoCruzColors.green),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    module.statusLabel,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    module.availability == ModuleAvailability.mobileInProgress
                        ? 'Este módulo todavía no está disponible en móvil. '
                              'No se muestran datos de ejemplo.'
                        : 'La consulta todavía no está integrada. Esto no '
                              'significa que no haya datos ni que el fenómeno '
                              'esté ausente.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    _moduleCaveat(module.id),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(Icons.arrow_back_rounded),
            label: const Text('Volver a módulos'),
            style: OutlinedButton.styleFrom(
              foregroundColor: GeoCruzColors.green,
              side: const BorderSide(color: GeoCruzColors.green),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _moduleCaveat(String id) => switch (id) {
    'focos-calor' =>
      'Una detección térmica no confirma un incendio. La confianza no '
          'representa gravedad y la potencia radiativa no es superficie quemada.',
    'sequias' =>
      'Las variables, unidades, período y modelo deben acompañar cada '
          'resultado. Una categoría de sequía requiere método y umbrales validados.',
    'inundaciones' =>
      'La presencia o cambio de agua no confirma por sí solo una inundación. '
          'El análisis y sus zonas no analizables deben identificarse.',
    'deforestacion' =>
      'La pérdida forestal depende del producto, su definición y período. '
          'No se infieren causas ni ilegalidad.',
    'calidad-aire' =>
      'Las observaciones y estimaciones modeladas deben distinguirse; una '
          'estimación no representa una estación local.',
    _ => 'La fuente y las limitaciones se informarán al integrar la consulta.',
  };
}
