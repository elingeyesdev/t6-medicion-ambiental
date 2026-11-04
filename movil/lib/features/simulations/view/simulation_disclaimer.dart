import 'package:flutter/material.dart';

/// Aviso breve: las simulaciones no son mediciones reales.
Future<void> showSimulationDisclaimer(BuildContext context) {
  return showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.info_outline),
      title: const Text('Solo escenarios'),
      content: const Text(
        'Los valores son escenarios generados. No los uses como observaciones '
        'ambientales.\n\n'
        'Crear o configurar una simulación se hace en la web GeoSur. En móvil '
        'puedes listar, abrir y reproducir las sesiones propias.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Entendido'),
        ),
      ],
    ),
  );
}
