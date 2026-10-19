import 'package:flutter/material.dart';

import '../../core/theme/geocruz_theme.dart';
import 'ambient_module.dart';

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
                'Módulos',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              Text(
                'Cinco perspectivas ambientales para Santa Cruz. La '
                'disponibilidad móvil se indica en cada módulo.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 22),
              for (final module in ambientModules) ...[
                _ModuleCard(
                  module: module,
                  onTap: () => onModuleSelected(module),
                ),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 8),
              const _InterpretationNote(),
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
    final inProgress =
        module.availability == ModuleAvailability.mobileInProgress;
    final statusColor = inProgress
        ? GeoCruzColors.amber
        : GeoCruzColors.textSoft;
    final statusBackground = inProgress
        ? GeoCruzColors.amberSoft
        : GeoCruzColors.background;

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
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
                    ),
                    const SizedBox(height: 10),
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

class _InterpretationNote extends StatelessWidget {
  const _InterpretationNote();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 8),
      child: Text(
        'La disponibilidad de una consulta y la cobertura de una fuente se '
        'mostrarán por separado cuando se integre cada módulo.',
        style: TextStyle(
          color: GeoCruzColors.textSoft,
          fontSize: 13,
          height: 1.45,
        ),
      ),
    );
  }
}
