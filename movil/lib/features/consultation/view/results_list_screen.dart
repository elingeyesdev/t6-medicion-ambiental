import 'package:flutter/material.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../../modules/model/ambient_module.dart';
import '../model/consulta_context.dart';
import 'record_detail_screen.dart';

enum ResultsAvailability { notIntegrated, ready }

class ResultsListScreen extends StatelessWidget {
  const ResultsListScreen({
    required this.module,
    required this.consulta,
    required this.records,
    required this.availability,
    super.key,
  });

  final AmbientModule module;
  final ConsultaContext consulta;
  final List<EnvironmentalRecord> records;
  final ResultsAvailability availability;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(module.name)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        children: [
          _QueryContextCard(consulta: consulta),
          const SizedBox(height: 18),
          if (availability == ResultsAvailability.notIntegrated)
            const _NotIntegratedMessage()
          else if (records.isEmpty)
            const _NoResultsMessage()
          else ...[
            Text('Resultados', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 10),
            for (final record in records) ...[
              _RecordListTile(
                record: record,
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => RecordDetailScreen(
                        module: module,
                        consulta: consulta.withRecord(
                          recordId: record.identifier,
                        ),
                        record: record,
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: 10),
            ],
          ],
        ],
      ),
    );
  }
}

class _QueryContextCard extends StatelessWidget {
  const _QueryContextCard({required this.consulta});

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
              'CONTEXTO DE CONSULTA',
              style: TextStyle(
                color: GeoCruzColors.green,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.8,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 12),
            _ContextRow(label: 'Territorio', value: consulta.territoryName),
            _ContextRow(label: 'Período · hora Bolivia', value: period),
            if (consulta.recordId case final id?)
              _ContextRow(label: 'Identificador', value: id),
          ],
        ),
      ),
    );
  }
}

class _ContextRow extends StatelessWidget {
  const _ContextRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 104,
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ),
        ],
      ),
    );
  }
}

class _RecordListTile extends StatelessWidget {
  const _RecordListTile({required this.record, required this.onTap});

  final EnvironmentalRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        onTap: onTap,
        title: Text(record.identifier),
        subtitle: Text(record.source ?? 'Fuente no informada'),
        trailing: const Icon(Icons.chevron_right_rounded),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      ),
    );
  }
}

class _NotIntegratedMessage extends StatelessWidget {
  const _NotIntegratedMessage();

  @override
  Widget build(BuildContext context) {
    return const _MessageCard(
      icon: Icons.hourglass_empty_rounded,
      title: 'Próximamente',
      message:
          'Todavía no hay una consulta integrada para este módulo. '
          'Este estado no representa un resultado vacío ni falta de cobertura.',
    );
  }
}

class _NoResultsMessage extends StatelessWidget {
  const _NoResultsMessage();

  @override
  Widget build(BuildContext context) {
    return const _MessageCard(
      icon: Icons.search_off_rounded,
      title: 'Sin resultados para estos filtros',
      message:
          'La consulta respondió correctamente sin registros. Esto no '
          'confirma que el fenómeno esté ausente.',
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: GeoCruzColors.green, size: 28),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(message, style: Theme.of(context).textTheme.bodyMedium),
          ],
        ),
      ),
    );
  }
}
