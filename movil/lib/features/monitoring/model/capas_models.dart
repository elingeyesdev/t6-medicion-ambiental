import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

Map<String, dynamic> _map(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((key, value) => MapEntry('$key', value));
  return const {};
}

String? _string(Object? value) =>
    value is String && value.isNotEmpty ? value : null;

num? _num(Object? value) => value is num ? value : null;

class CapasCatalog {
  const CapasCatalog({
    required this.version,
    required this.estado,
    required this.revision,
    required this.capas,
    required this.faltantes,
  });

  final int version;
  final String estado;
  final String revision;
  final List<CapaVariable> capas;
  final int faltantes;

  bool get disponible => estado == 'disponible' && capas.any((c) => c.cuadros.isNotEmpty);

  factory CapasCatalog.fromJson(Object? json) {
    final value = _map(json);
    final capas = <CapaVariable>[];
    final raw = value['capas'];
    if (raw is List) {
      for (final item in raw) {
        capas.add(CapaVariable.fromJson(item));
      }
    }
    return CapasCatalog(
      version: _num(value['version'])?.toInt() ?? 1,
      estado: _string(value['estado']) ?? 'sin_insumos',
      revision: _string(value['revision']) ?? '',
      capas: capas,
      faltantes: _num(value['faltantes'])?.toInt() ?? 0,
    );
  }
}

class CapaVariable {
  const CapaVariable({
    required this.id,
    required this.nombre,
    required this.unidad,
    required this.cuadros,
  });

  final String id;
  final String nombre;
  final String unidad;
  final List<CapaCuadro> cuadros;

  List<CapaCuadro> get ordenados {
    final copy = [...cuadros];
    copy.sort((a, b) => a.instante.compareTo(b.instante));
    return copy;
  }

  factory CapaVariable.fromJson(Object? json) {
    final value = _map(json);
    final cuadros = <CapaCuadro>[];
    final raw = value['cuadros'];
    if (raw is List) {
      for (final item in raw) {
        final cuadro = CapaCuadro.fromJson(item, capaId: _string(value['id']) ?? '');
        if (cuadro.id.isNotEmpty && cuadro.url.isNotEmpty) cuadros.add(cuadro);
      }
    }
    return CapaVariable(
      id: _string(value['id']) ?? '',
      nombre: _string(value['nombre']) ?? '',
      unidad: _string(value['unidad']) ?? '',
      cuadros: cuadros,
    );
  }
}

class CapaCuadro {
  const CapaCuadro({
    required this.id,
    required this.capaId,
    required this.instante,
    required this.ancho,
    required this.alto,
    required this.codificacion,
    required this.url,
    required this.rejilla,
    this.fuente,
    this.producto,
    this.origen,
  });

  final String id;
  final String capaId;
  final String instante;
  final int ancho;
  final int alto;
  final String codificacion;
  final String url;
  final CapaRejilla rejilla;
  final String? fuente;
  final String? producto;
  final String? origen;

  LatLngBounds get bounds => LatLngBounds(
        LatLng(rejilla.sur, rejilla.oeste),
        LatLng(rejilla.norte, rejilla.este),
      );

  factory CapaCuadro.fromJson(Object? json, {required String capaId}) {
    final value = _map(json);
    return CapaCuadro(
      id: _string(value['id']) ?? '',
      capaId: capaId.isNotEmpty ? capaId : (_string(value['capa']) ?? ''),
      instante: _string(value['instante']) ?? '',
      ancho: _num(value['ancho'])?.toInt() ?? 0,
      alto: _num(value['alto'])?.toInt() ?? 0,
      codificacion: _string(value['codificacion']) ?? '',
      url: _string(value['url']) ?? '',
      rejilla: CapaRejilla.fromJson(value['rejilla']),
      fuente: _string(value['fuente']),
      producto: _string(value['producto']),
      origen: _string(value['origen']),
    );
  }
}

class CapaRejilla {
  const CapaRejilla({
    required this.oeste,
    required this.este,
    required this.sur,
    required this.norte,
    required this.filas,
    required this.registro,
  });

  final double oeste;
  final double este;
  final double sur;
  final double norte;
  final String filas;
  final String registro;

  factory CapaRejilla.fromJson(Object? json) {
    final value = _map(json);
    return CapaRejilla(
      oeste: _num(value['oeste'])?.toDouble() ?? -180,
      este: _num(value['este'])?.toDouble() ?? 180,
      sur: _num(value['sur'])?.toDouble() ?? -90,
      norte: _num(value['norte'])?.toDouble() ?? 90,
      filas: _string(value['filas']) ?? 'sur-norte',
      registro: _string(value['registro']) ?? 'centros',
    );
  }
}
