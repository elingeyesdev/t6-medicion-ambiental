import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../model/air_repository.dart';

/// Ficha de calidad del aire (API `/aire`) — punto 2 #89 / #62.
class AirSheet extends StatefulWidget {
  const AirSheet({
    required this.api,
    required this.point,
    this.title,
    super.key,
  });

  final GeoCruzApiClient api;
  final LatLng point;
  final String? title;

  @override
  State<AirSheet> createState() => _AirSheetState();
}

class _AirSheetState extends State<AirSheet> {
  late final Future<AirSnapshot> _future;

  @override
  void initState() {
    super.initState();
    _future = AirRepository(widget.api).quality(
      latitude: widget.point.latitude,
      longitude: widget.point.longitude,
    );
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.68,
      minChildSize: 0.4,
      maxChildSize: 0.92,
      builder: (context, controller) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: FutureBuilder<AirSnapshot>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                final message = snapshot.error is GeoCruzApiException
                    ? (snapshot.error as GeoCruzApiException).message
                    : 'No se pudo cargar la calidad del aire.';
                return Center(child: Text(message, textAlign: TextAlign.center));
              }
              final air = snapshot.data!;
              final current = air.current;
              final fmt = DateFormat('dd/MM HH:mm');
              String valueOf(String key) {
                final raw = current[key];
                final unit = air.unitOf(key) ?? '';
                if (raw is! num) return '—';
                return '$raw $unit'.trim();
              }

              return ListView(
                controller: controller,
                children: [
                  Text(
                    widget.title ?? 'Calidad del aire',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Estimado · CAMS global · Open-Meteo',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (current['instante'] is String) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Actualizado ${fmt.format(DateTime.tryParse(current['instante'] as String)?.toLocal() ?? DateTime.now())}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                  const SizedBox(height: 16),
                  _metric(context, 'Índice europeo (EAQI)', valueOf('indice')),
                  _metric(context, air.labelOf('pm25') ?? 'PM2,5', valueOf('pm25')),
                  _metric(context, air.labelOf('pm10') ?? 'PM10', valueOf('pm10')),
                  _metric(context, air.labelOf('no2') ?? 'NO₂', valueOf('no2')),
                  _metric(context, air.labelOf('ozono') ?? 'Ozono', valueOf('ozono')),
                  _metric(context, air.labelOf('so2') ?? 'SO₂', valueOf('so2')),
                  if (air.hours.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Últimas horas',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    ...air.hours.reversed.take(8).map((hour) {
                      final instante = hour['instante'];
                      final label = instante is String
                          ? fmt.format(
                              DateTime.tryParse(instante)?.toLocal() ??
                                  DateTime.now(),
                            )
                          : '—';
                      final indice = hour['indice'];
                      return ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text(label),
                        trailing: Text(
                          indice is num ? 'EAQI $indice' : '—',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      );
                    }),
                  ],
                ],
              );
            },
          ),
        );
      },
    );
  }

  Widget _metric(BuildContext context, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}
