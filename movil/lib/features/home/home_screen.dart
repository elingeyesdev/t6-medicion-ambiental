import 'package:flutter/material.dart';

import '../../core/theme/geocruz_theme.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({required this.onExploreModules, super.key});

  final VoidCallback onExploreModules;

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
              const SizedBox(height: 30),
              const _Eyebrow('UN TERRITORIO · CINCO PERSPECTIVAS'),
              const SizedBox(height: 12),
              Text(
                'Conoce el entorno\nde Santa Cruz.',
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: 12),
              Text(
                'Información ambiental del departamento, sus provincias y '
                'municipios, con contexto para interpretar cada dato.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 24),
              const _AvailableOnWebNotice(),
              const SizedBox(height: 24),
              _TerritoryCard(onTap: onExploreModules),
              const SizedBox(height: 28),
              Text(
                'Explora GeoCruz',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 6),
              Text(
                'Elige un tema para ver su estado de desarrollo móvil.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: onExploreModules,
                icon: const Icon(Icons.grid_view_rounded),
                label: const Text('Ver los cinco módulos'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: GeoCruzColors.green,
                  side: const BorderSide(color: GeoCruzColors.green),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 28),
              const _FooterNote(),
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

class _AvailableOnWebNotice extends StatelessWidget {
  const _AvailableOnWebNotice();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: GeoCruzColors.amberSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF0D7A8)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: GeoCruzColors.amber),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Focos de calor ya tiene una consulta web. La experiencia móvil '
              'aún no está disponible; aquí no se muestran datos de ejemplo.',
              style: TextStyle(
                color: GeoCruzColors.greenDark,
                height: 1.4,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TerritoryCard extends StatelessWidget {
  const _TerritoryCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: GeoCruzColors.greenSoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.map_outlined,
                  color: GeoCruzColors.green,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Santa Cruz, Bolivia',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Departamento · provincias · municipios',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              const Icon(Icons.arrow_forward_ios_rounded, size: 16),
            ],
          ),
        ),
      ),
    );
  }
}

class _FooterNote extends StatelessWidget {
  const _FooterNote();

  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.fact_check_outlined, color: GeoCruzColors.green, size: 20),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'Cada consulta debe mostrar su fuente, período, cobertura y '
            'limitaciones. Un resultado vacío no confirma que el fenómeno '
            'esté ausente.',
            style: TextStyle(
              color: GeoCruzColors.textSoft,
              fontSize: 13,
              height: 1.45,
            ),
          ),
        ),
      ],
    );
  }
}
