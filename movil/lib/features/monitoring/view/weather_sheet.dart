import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/widgets/units_scope.dart';
import '../model/weather_repository.dart';

/// Tiempo y pronóstico (API `tiempo`) — mapa completo #62 / #89.
class WeatherSheet extends StatefulWidget {
  const WeatherSheet({
    required this.api,
    required this.point,
    this.title,
    super.key,
  });

  final GeoCruzApiClient api;
  final LatLng point;
  final String? title;

  @override
  State<WeatherSheet> createState() => _WeatherSheetState();
}

class _WeatherSheetState extends State<WeatherSheet> {
  late Future<WeatherSnapshot> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<WeatherSnapshot> _load() => WeatherRepository(widget.api).forecast(
        latitude: widget.point.latitude,
        longitude: widget.point.longitude,
      );

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.72,
      minChildSize: 0.45,
      maxChildSize: 0.92,
      builder: (context, controller) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: FutureBuilder<WeatherSnapshot>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                final message = snapshot.error is GeoCruzApiException
                    ? (snapshot.error as GeoCruzApiException).message
                    : 'No se pudo cargar el tiempo.';
                return ListView(
                  controller: controller,
                  children: [
                    Text(
                      widget.title ?? 'Tiempo y pronóstico',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 16),
                    Icon(
                      Icons.cloud_off_outlined,
                      size: 40,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    const SizedBox(height: 12),
                    Text(message, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _retry,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Reintentar'),
                    ),
                  ],
                );
              }
              final weather = snapshot.data!;
              final units = UnitsScope.maybeOf(context)?.presentation;
              final current = weather.current;
              final tempBase = weather.unitOf('temperatura') ?? '°C';

              String fmt(String key, {String? asMetric}) {
                final raw = current[key];
                final base = weather.unitOf(key) ?? '';
                if (raw is! num) return '—';
                final metric = asMetric ?? key;
                if (units != null &&
                    (metric == 'temperatura' ||
                        metric == 'viento' ||
                        key == 'sensacion')) {
                  return units.format(
                    key == 'sensacion' ? 'temperatura' : metric,
                    raw,
                    base.isEmpty ? tempBase : base,
                  );
                }
                return '$raw $base'.trim();
              }

              String dayTemp(Object? value) {
                if (value is! num) return '—';
                if (units == null) return '$value $tempBase'.trim();
                return units.format('temperatura', value, tempBase);
              }

              return ListView(
                controller: controller,
                children: [
                  Text(
                    widget.title ?? 'Tiempo y pronóstico',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    '${widget.point.latitude.toStringAsFixed(3)}, ${widget.point.longitude.toStringAsFixed(3)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _ChipMetric(label: 'Temp.', value: fmt('temperatura')),
                      _ChipMetric(
                        label: 'Sensación',
                        value: fmt('sensacion', asMetric: 'temperatura'),
                      ),
                      _ChipMetric(label: 'Lluvia', value: fmt('lluvia')),
                      _ChipMetric(label: 'Nieve', value: fmt('nieve')),
                      _ChipMetric(label: 'Viento', value: fmt('viento')),
                      _ChipMetric(label: 'Humedad', value: fmt('humedad')),
                      _ChipMetric(label: 'UV', value: fmt('uv')),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Text(
                    'Próximos días',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  if (weather.days.isEmpty)
                    Text(
                      'Sin días de pronóstico en la respuesta.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    )
                  else
                    for (final day in weather.days.take(7))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.calendar_today_outlined),
                        title: Text(_dayLabel(day['fecha'])),
                        subtitle: Text(
                          'Máx ${dayTemp(day['maxima'])} · '
                          'Mín ${dayTemp(day['minima'])} · '
                          'Lluvia ${_num(day['lluvia'])} mm',
                        ),
                      ),
                ],
              );
            },
          ),
        );
      },
    );
  }

  String _dayLabel(Object? value) {
    final parsed = DateTime.tryParse('$value');
    if (parsed == null) return '$value';
    return DateFormat('EEE d MMM').format(parsed);
  }

  String _num(Object? value) => value is num ? '$value' : '—';
}

class _ChipMetric extends StatelessWidget {
  const _ChipMetric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text('$label: $value'),
      visualDensity: VisualDensity.compact,
    );
  }
}
