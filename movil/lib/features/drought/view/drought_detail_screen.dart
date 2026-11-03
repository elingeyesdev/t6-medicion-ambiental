import 'package:flutter/material.dart';

import '../../../core/theme/geocruz_theme.dart';
import '../model/drought_models.dart';

class DroughtDetailScreen extends StatelessWidget {
  const DroughtDetailScreen({
    required this.sample,
    required this.response,
    required this.territoryName,
    super.key,
  });

  final DroughtSample sample;
  final DroughtResponse response;
  final String Function(String?) territoryName;

  @override
  Widget build(BuildContext context) {
    final cat = sample.spi.categoryInfo;
    final rainfall = sample.precipitacionAcumuladaMm != null
        ? '${sample.precipitacionAcumuladaMm!.toStringAsFixed(1)} mm'
        : 'Sin dato';
    final spiText = sample.spi.valor != null
        ? sample.spi.valor!.toStringAsFixed(2)
        : 'Sin índice';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detalle de muestra de sequía'),
      ),
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
                    'Muestra',
                    style: TextStyle(
                      color: GeoCruzColors.green,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: SelectableText(
                          sample.idMuestra,
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: cat.color.withValues(alpha: 0.15),
                          border: Border.all(color: cat.color),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          cat.label,
                          style: TextStyle(
                            color: cat.color,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _InfoCard(
            title: 'Resumen territorial',
            children: [
              _InfoRow(
                label: 'Territorio contexto',
                value: territoryName(sample.territorioContexto),
              ),
              _InfoRow(
                label: 'Centro celda ERA5',
                value: territoryName(sample.municipioDelCentroCelda),
              ),
              _InfoRow(
                label: 'Coordenada pedida',
                value:
                    '${sample.coordenadaSolicitada[0].toStringAsFixed(4)}, ${sample.coordenadaSolicitada[1].toStringAsFixed(4)}',
              ),
              _InfoRow(
                label: 'Coordenada celda',
                value:
                    '${sample.coordenadaCelda[0].toStringAsFixed(4)}, ${sample.coordenadaCelda[1].toStringAsFixed(4)}',
              ),
              if (sample.centroDifiere)
                const _InfoRow(
                  label: 'Discrepancia territorial',
                  value:
                      'El centro de celda cae en municipio vecino; se conserva el territorio solicitado.',
                ),
            ],
          ),
          const SizedBox(height: 12),
          _InfoCard(
            title: 'Indicador y precipitación',
            children: [
              _InfoRow(
                label: 'Índice SPI-3',
                value: spiText,
              ),
              _InfoRow(
                label: 'Precipitación acumulada (3m)',
                value: rainfall,
              ),
              _InfoRow(
                label: 'Período analizado',
                value: '${response.consulta['mesFinal'] ?? ''} (3 meses UTC)',
              ),
              _InfoRow(
                label: 'Años base de referencia',
                value: '${sample.spi.aniosValidos} años válidos (1991–2020)',
              ),
              if (!sample.spi.hasValue && sample.spi.motivo != null)
                _InfoRow(
                  label: 'Motivo sin índice',
                  value: DroughtMotives.describe(sample.spi.motivo),
                ),
            ],
          ),
          const SizedBox(height: 12),
          _InfoCard(
            title: 'Sobre estos datos',
            children: const [
              _InfoRow(
                label: 'Proveedor',
                value: 'Open-Meteo ERA5 Reanalysis',
              ),
              _InfoRow(
                label: 'Resolución espacial',
                value: '0.25° (~27 km)',
              ),
              _InfoRow(
                label: 'Variable primaria',
                value: 'precipitation_sum (mm)',
              ),
              _InfoRow(
                label: 'Licencia de datos',
                value: 'CC BY 4.0 / Copernicus ECMWF',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: 10),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: const TextStyle(fontSize: 14, color: Colors.black54),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            flex: 5,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
