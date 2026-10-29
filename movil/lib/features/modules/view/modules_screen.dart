import 'package:flutter/material.dart';

import '../../../core/theme/geocruz_theme.dart';
import '../model/ambient_module.dart';

class ModulesScreen extends StatelessWidget {
  const ModulesScreen({required this.onModuleSelected, super.key});

  final ValueChanged<AmbientModule> onModuleSelected;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      key: const PageStorageKey<String>('modules-scroll'),
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 26, 20, 30),
          sliver: SliverList.list(
            children: [
              Text(
                'Análisis disponibles',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              Text(
                'Solo aparecen recorridos que ya tienen una consulta móvil activa.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 16),
              for (final module in ambientModules) ...[
                _ModuleCard(
                  module: module,
                  onTap: () => onModuleSelected(module),
                ),
                const SizedBox(height: 12),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module, required this.onTap});

  final AmbientModule module;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final statusColor = switch (module.availability) {
      ModuleAvailability.available => GeoCruzColors.green,
      ModuleAvailability.mobileInProgress => GeoCruzColors.amber,
      ModuleAvailability.comingSoon => GeoCruzColors.textSoft,
    };
    final statusBackground = switch (module.availability) {
      ModuleAvailability.available => GeoCruzColors.greenSoft,
      ModuleAvailability.mobileInProgress => GeoCruzColors.amberSoft,
      ModuleAvailability.comingSoon => GeoCruzColors.background,
    };

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: GeoCruzColors.greenSoft,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(module.icon, color: GeoCruzColors.green),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            module.name,
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        const Icon(Icons.chevron_right_rounded),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      module.summary,
                      style: Theme.of(context).textTheme.bodyMedium,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 8),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: statusBackground,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        child: Text(
                          module.statusLabel,
                          style: TextStyle(
                            color: statusColor,
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
