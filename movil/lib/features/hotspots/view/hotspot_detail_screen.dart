import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/theme/geocruz_theme.dart';
import '../model/hotspot_models.dart';

class HotspotDetailScreen extends StatelessWidget {
  const HotspotDetailScreen({
    required this.record,
    required this.response,
    required this.territoryName,
    super.key,
  });

  final HotspotRecord record;
  final HotspotResponse response;
  final String Function(String?) territoryName;

  static final _number = NumberFormat('###,##0.##', 'es_BO');

  String _value(Object? value) {
    if (value == null || value is String && value.isEmpty) return 'Sin dato';
    if (value is num) return _number.format(value);
    return '$value';
  }

  String _date(Object? value) {
    final parsed = value is String ? DateTime.tryParse(value) : null;
    return parsed == null
        ? 'No informada'
        : '${BoliviaTime.dateTime(parsed)} · Bolivia';
  }

  String _nature(String? value) => switch (value) {
    'observacion' => 'Observación',
    'estimacion_modelada' => 'Estimación modelada',
    'derivado' => 'Derivado',
    'simulado' => 'Simulado',
    _ => value ?? 'No informada',
  };

  @override
  Widget build(BuildContext context) {
    final attrs = record.attributes;
    final territoryId =
        record.territory['municipio'] ??
        record.territory['provincia'] ??
        record.territory['departamento'] ??
        response.territoryId;
    final sourceItems = response.sources
        .whereType<Map>()
        .where((source) => record.sourceRefs.contains(source['identificador']))
        .toList(growable: false);
    final power = record.radiantPower;
    final missing = record.missing;

    return Scaffold(
      appBar: AppBar(title: const Text('Detalle de detección')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'IDENTIFICADOR',
                    style: TextStyle(
                      color: GeoCruzColors.green,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    record.identifier,
                    key: const Key('hotspot-identifier'),
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _InfoCard(
            title: 'Resumen',
            children: [
              _InfoRow(
                label: 'Territorio',
                value: territoryName('$territoryId'),
              ),
              _InfoRow(
                label: 'Fecha observada',
                value: _date(record.observedAt),
              ),
              _InfoRow(
                label: 'Potencia radiativa',
                value: power == null
                    ? 'Sin dato'
                    : '${_value(power)} ${record.radiantPowerUnit ?? 'MW'}',
              ),
              _InfoRow(
                label: 'Confianza',
                value: _confidence(record.confidence),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Card(
            child: ExpansionTile(
              leading: const Icon(Icons.manage_search_rounded),
              title: const Text('Consulta y fuente'),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              children: [
                _InfoRow(
                  label: 'Período',
                  value: BoliviaTime.interval(
                    response.periodFrom,
                    response.periodUntil,
                  ),
                ),
                _InfoRow(
                  label: 'Consulta',
                  value:
                      response.raw['consulta']?['identificador']?.toString() ??
                      'Sin dato',
                ),
                if (sourceItems.isEmpty)
                  const _InfoRow(
                    label: 'Fuente',
                    value: 'No informada por la respuesta',
                  )
                else
                  for (final source in sourceItems) ...[
                    _InfoRow(
                      label: 'Proveedor',
                      value: _value(source['proveedor']),
                    ),
                    _InfoRow(
                      label: 'Producto',
                      value: _value(source['producto']),
                    ),
                    _InfoRow(
                      label: 'Versión del conjunto',
                      value: _value(source['version']),
                    ),
                  ],
                _InfoRow(
                  label: 'Tipo de obtención',
                  value: _fetchType(response.fetchType),
                ),
                _InfoRow(
                  label: 'Recuperada',
                  value: _date(response.fetchedAt?.toIso8601String()),
                ),
                if (response.ageSeconds != null)
                  _InfoRow(
                    label: 'Antigüedad al responder',
                    value: '${response.ageSeconds} segundos',
                  ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Card(
            child: ExpansionTile(
              leading: const Icon(Icons.data_object_rounded),
              title: const Text('Datos técnicos'),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              children: [
                _InfoRow(
                  label: 'Coordenadas · latitud, longitud',
                  value: record.coordinates == null
                      ? 'Sin geometría disponible'
                      : '${record.coordinates!.latitude.toStringAsFixed(5)}, ${record.coordinates!.longitude.toStringAsFixed(5)}',
                ),
                _InfoRow(label: 'Satélite', value: _value(attrs['satelite'])),
                _InfoRow(
                  label: 'Instrumento',
                  value: _value(attrs['instrumento']),
                ),
                _InfoRow(
                  label: 'Versión del producto',
                  value: _value(attrs['versionOriginal']),
                ),
                _InfoRow(label: 'Naturaleza', value: _nature(record.nature)),
                _InfoRow(label: 'Método', value: _value(record.method)),
                if (missing.isEmpty)
                  const _InfoRow(
                    label: 'Faltantes',
                    value: 'Sin faltantes declarados para este registro.',
                  )
                else
                  for (final item in missing)
                    _InfoRow(
                      label: 'Faltante',
                      value: _missingDescription(item),
                    ),
              ],
            ),
          ),
          const SizedBox(height: 10),
        ],
      ),
    );
  }

  String _confidence(String? value) => switch (value) {
    'alta' => 'Alta',
    'nominal' => 'Nominal',
    'baja' => 'Baja',
    'desconocida' => 'Desconocida',
    _ => 'No informada',
  };

  String _fetchType(String value) => switch (value) {
    'cache' => 'Caché de API',
    'consulta_proveedor' => 'Consulta al proveedor',
    _ => value,
  };

  String _missingDescription(Object? value) {
    if (value is Map) {
      final field = value['campo'] ?? value['atributo'] ?? value['codigo'];
      final reason = value['motivo'] ?? value['mensaje'];
      return [
        field,
        reason,
      ].where((part) => part != null && '$part'.isNotEmpty).join(' · ');
    }
    return '$value';
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 132,
          child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
        ),
        Expanded(
          child: SelectableText(
            value.isEmpty ? 'Sin dato' : value,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
        ),
      ],
    ),
  );
}
