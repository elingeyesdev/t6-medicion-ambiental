import 'package:flutter/material.dart';

import '../model/raster_decode.dart';

/// Leyenda de rampa de colores de la capa activa (paridad web).
class CapasLeyendaBar extends StatelessWidget {
  const CapasLeyendaBar({
    required this.capaId,
    required this.nombre,
    this.compact = false,
    super.key,
  });

  final String capaId;
  final String nombre;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final stops = rampaDeCapa(capaId);
    if (stops.isEmpty) return const SizedBox.shrink();
    final min = stops.first.$1;
    final max = stops.last.$1;
    final span = (max - min).abs() < 1e-9 ? 1.0 : max - min;
    final scheme = Theme.of(context).colorScheme;
    final gradient = LinearGradient(
      colors: [
        for (final stop in stops) _hexColor(stop.$2),
      ],
      stops: [
        for (final stop in stops) ((stop.$1 - min) / span).clamp(0.0, 1.0),
      ],
    );

    return Material(
      color: scheme.surface.withValues(alpha: compact ? 0.92 : 1),
      borderRadius: BorderRadius.circular(compact ? 12 : 14),
      elevation: compact ? 2 : 0,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          compact ? 10 : 0,
          compact ? 8 : 0,
          compact ? 10 : 0,
          compact ? 8 : 0,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              nombre,
              style: Theme.of(context).textTheme.labelLarge,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 6),
            Container(
              height: 10,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(6),
                gradient: gradient,
              ),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Text(
                  _fmt(stops.first.$1),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                const Spacer(),
                if (stops.length > 2)
                  Text(
                    _fmt(stops[stops.length ~/ 2].$1),
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                const Spacer(),
                Text(
                  _fmt(stops.last.$1),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
            ),
            if (!compact) ...[
              const SizedBox(height: 4),
              Text(
                'Transparente: sin dato. Escala limitada en los extremos.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }

  static String _fmt(double value) {
    if (value.abs() >= 100) return value.round().toString();
    if (value == value.roundToDouble()) return value.round().toString();
    return value.toStringAsFixed(1).replaceAll('.', ',');
  }

  static Color _hexColor(String hex) {
    final h = hex.replaceFirst('#', '');
    return Color(int.parse('FF$h', radix: 16));
  }
}
