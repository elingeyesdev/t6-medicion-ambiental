import 'package:flutter/material.dart';

import '../../../core/theme/geocruz_theme.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({
    required this.onExploreModules,
    required this.onOpenHotspots,
    super.key,
  });

  final VoidCallback onExploreModules;
  final VoidCallback onOpenHotspots;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      key: const PageStorageKey<String>('home-scroll'),
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
          sliver: SliverList.list(
            children: [
              const _BrandHeader(),
              const SizedBox(height: 28),
              const _Eyebrow('SANTA CRUZ · BOLIVIA'),
              const SizedBox(height: 12),
              Text(
                'Información ambiental\nde Santa Cruz.',
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: 12),
              Text(
                'Consulta los datos disponibles con su fuente, período y '
                'territorio.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onOpenHotspots,
                icon: const Icon(Icons.local_fire_department_outlined),
                label: const Text('Consultar focos de calor'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
              ),
              const SizedBox(height: 18),
              TextButton.icon(
                onPressed: onExploreModules,
                icon: const Icon(Icons.grid_view_rounded),
                label: const Text('Ver los cinco módulos'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: GeoCruzColors.green,
            borderRadius: BorderRadius.circular(13),
          ),
          alignment: Alignment.center,
          child: const Text(
            'G+',
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 20,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('GeoCruz', style: Theme.of(context).textTheme.titleLarge),
            Text(
              'Información ambiental',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ],
    );
  }
}

class _Eyebrow extends StatelessWidget {
  const _Eyebrow(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: const TextStyle(
        color: GeoCruzColors.green,
        fontWeight: FontWeight.w800,
        fontSize: 11,
        letterSpacing: 1.1,
      ),
    );
  }
}
