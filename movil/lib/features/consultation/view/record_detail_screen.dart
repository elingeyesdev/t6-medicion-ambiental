import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../modules/model/ambient_module.dart';
import '../model/consulta_context.dart';

class RecordDetailScreen extends StatelessWidget {
  const RecordDetailScreen({
    required this.module,
    required this.consulta,
    required this.record,
    super.key,
  });

  final AmbientModule module;
  final ConsultaContext consulta;
  final EnvironmentalRecord record;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Detalle · ${module.name}')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'IDENTIFICADOR DEL REGISTRO',
                    style: TextStyle(
                      color: GeoCruzColors.green,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 6),
                  SelectableText(
                    record.identifier,
                    key: const Key('record-identifier'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _ContextCard(consulta: consulta),
          const SizedBox(height: 18),
          Text('Procedencia', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          _AttributeRow(label: 'Fuente', value: record.source),
          _AttributeRow(label: 'Naturaleza', value: record.nature),
          _AttributeRow(
            label: 'Fecha observada',
            value: record.observedAt?.toIso8601String(),
          ),
          const SizedBox(height: 14),
          if (record.attributes.isEmpty)
            Text(
              'No hay atributos adicionales disponibles.',
              style: Theme.of(context).textTheme.bodyMedium,
            )
          else ...[
            Text('Atributos', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            for (final entry in record.attributes.entries)
              _AttributeRow(label: entry.key, value: entry.value?.toString()),
          ],
        ],
      ),
    );
  }
}

class _ContextCard extends StatelessWidget {
  const _ContextCard({required this.consulta});

  final ConsultaContext consulta;

  @override
  Widget build(BuildContext context) {
    final period = BoliviaTime.interval(consulta.from, consulta.untilExclusive);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'CONTEXTO CONSERVADO DESDE LA LISTA',
              style: TextStyle(
                color: GeoCruzColors.green,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.6,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 8),
            _AttributeRow(label: 'Territorio', value: consulta.territoryName),
            _AttributeRow(label: 'Período', value: period),
            _AttributeRow(
              label: 'Identificador',
              value: consulta.recordId,
              key: const Key('context-record-identifier'),
            ),
          ],
        ),
      ),
    );
  }
}

class _AttributeRow extends StatelessWidget {
  const _AttributeRow({required this.label, required this.value, super.key});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 126,
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ),
          Expanded(
            child: SelectableText(
              value == null || value!.isEmpty ? 'Sin dato' : value!,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ),
        ],
      ),
    );
  }
}
