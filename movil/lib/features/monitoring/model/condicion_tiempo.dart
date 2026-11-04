import 'package:flutter/material.dart';

/// Clasificaciones WMO de Open-Meteo (paridad con `condicion-tiempo.js`).
class CondicionTiempo {
  const CondicionTiempo({required this.nombre, required this.icono});

  final String nombre;
  final String icono;

  /// Icono Material aproximado al SVG de la web (sin assets externos).
  IconData get iconData => switch (icono) {
        'sol' => Icons.wb_sunny_rounded,
        'luna' => Icons.nightlight_round,
        'nube-sol' => Icons.wb_cloudy_rounded,
        'nube-luna' => Icons.nights_stay_rounded,
        'nube' => Icons.cloud_rounded,
        'niebla' => Icons.dehaze_rounded,
        'llovizna' => Icons.grain_rounded,
        'lluvia' => Icons.water_drop_rounded,
        'lluvia-helada' => Icons.ac_unit_rounded,
        'nieve' => Icons.severe_cold_rounded,
        'tormenta' => Icons.thunderstorm_rounded,
        'granizo' => Icons.grain_rounded,
        _ => Icons.help_outline_rounded,
      };
}

const _condiciones = <int, (String, String)>{
  0: ('Despejado', 'sol'),
  1: ('Mayormente despejado', 'nube-sol'),
  2: ('Parcialmente nublado', 'nube-sol'),
  3: ('Nublado', 'nube'),
  45: ('Niebla', 'niebla'),
  48: ('Niebla con escarcha', 'niebla'),
  51: ('Llovizna ligera', 'llovizna'),
  53: ('Llovizna moderada', 'llovizna'),
  55: ('Llovizna densa', 'llovizna'),
  56: ('Llovizna helada ligera', 'lluvia-helada'),
  57: ('Llovizna helada densa', 'lluvia-helada'),
  61: ('Lluvia ligera', 'lluvia'),
  63: ('Lluvia moderada', 'lluvia'),
  65: ('Lluvia fuerte', 'lluvia'),
  66: ('Lluvia helada ligera', 'lluvia-helada'),
  67: ('Lluvia helada fuerte', 'lluvia-helada'),
  71: ('Nieve ligera', 'nieve'),
  73: ('Nieve moderada', 'nieve'),
  75: ('Nieve fuerte', 'nieve'),
  77: ('Nieve granulada', 'nieve'),
  80: ('Chubascos ligeros', 'lluvia'),
  81: ('Chubascos moderados', 'lluvia'),
  82: ('Chubascos fuertes', 'lluvia'),
  85: ('Chubascos de nieve ligeros', 'nieve'),
  86: ('Chubascos de nieve fuertes', 'nieve'),
  95: ('Tormenta eléctrica', 'tormenta'),
  96: ('Tormenta con granizo ligero', 'granizo'),
  97: ('Tormenta fuerte', 'tormenta'),
  99: ('Tormenta con granizo fuerte', 'granizo'),
};

CondicionTiempo condicionTiempo(Object? codigo, {bool? esDia}) {
  final code = codigo is int
      ? codigo
      : (codigo is num && codigo == codigo.roundToDouble()
          ? codigo.toInt()
          : null);
  final valor = code == null ? null : _condiciones[code];
  if (valor == null) {
    return const CondicionTiempo(nombre: 'Sin dato', icono: 'sin-dato');
  }
  var icono = valor.$2;
  if (esDia == false && icono == 'sol') icono = 'luna';
  if (esDia == false && icono == 'nube-sol') icono = 'nube-luna';
  return CondicionTiempo(nombre: valor.$1, icono: icono);
}
