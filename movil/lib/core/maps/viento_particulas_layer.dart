import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import '../../features/monitoring/model/capas_overlay_state.dart';
import '../../features/monitoring/model/raster_decode.dart';

/// Partículas de viento animadas (estilo simplificado de `particulas-viento.js`).
class VientoParticulasOverlay extends StatefulWidget {
  const VientoParticulasOverlay({
    required this.mapController,
    required this.state,
    super.key,
  });

  final MapController mapController;
  final CapasOverlayState state;

  @override
  State<VientoParticulasOverlay> createState() =>
      _VientoParticulasOverlayState();
}

class _VientoParticulasOverlayState extends State<VientoParticulasOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  StreamSubscription<MapEvent>? _mapSub;
  final List<_Particula> _particulas = [];
  Size _size = Size.zero;
  final _rng = math.Random(7);
  DateTime _ultimo = DateTime.now();

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 33),
    )..addListener(_tick);
    _mapSub = widget.mapController.mapEventStream.listen((_) {
      if (!mounted) return;
      _reponer();
    });
  }

  @override
  void didUpdateWidget(covariant VientoParticulasOverlay oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.state.sampleA?.cuadro.id != widget.state.sampleA?.cuadro.id ||
        oldWidget.state.sampleB?.cuadro.id != widget.state.sampleB?.cuadro.id ||
        oldWidget.state.animarViento != widget.state.animarViento ||
        oldWidget.state.visible != widget.state.visible) {
      _reponer();
    }
  }

  @override
  void dispose() {
    _mapSub?.cancel();
    _controller.dispose();
    super.dispose();
  }

  CapaCanalSample? _canalEn(double x) {
    final a = widget.state.sampleA;
    final b = widget.state.sampleB;
    if (widget.state.comparing && b != null) {
      final divisionX = _size.width * widget.state.division;
      final ladoB = x >= divisionX;
      final canal = ladoB ? b : a;
      return canal != null && canal.esViento ? canal : null;
    }
    if (a != null && a.esViento) return a;
    return null;
  }

  bool get _activo {
    if (!widget.state.visible || !widget.state.animarViento) return false;
    final a = widget.state.sampleA;
    final b = widget.state.sampleB;
    if (a != null && a.esViento) return true;
    return widget.state.comparing && b != null && b.esViento;
  }

  void _reponer() {
    if (!_activo || _size.isEmpty) {
      _particulas.clear();
      _controller.stop();
      if (mounted) setState(() {});
      return;
    }
    final paso = math.max(46.0, math.sqrt(_size.width * _size.height / 300));
    _particulas.clear();
    for (var y = paso / 2; y < _size.height; y += paso) {
      for (var x = paso / 2; x < _size.width; x += paso) {
        final px = x + (_rng.nextDouble() - .5) * paso * .7;
        final py = y + (_rng.nextDouble() - .5) * paso * .7;
        final vector = _vectorEn(px, py);
        if (_pasoViento(vector, 0.01) != null) {
          _particulas.add(_Particula.nacer(px, py, _rng, repartir: true));
        }
      }
    }
    _ultimo = DateTime.now();
    if (_particulas.isNotEmpty) {
      _controller.repeat();
    } else {
      _controller.stop();
    }
    if (mounted) setState(() {});
  }

  VientoVector? _vectorEn(double x, double y) {
    if (x < 0 || y < 0 || x > _size.width || y > _size.height) {
      return null;
    }
    final canal = _canalEn(x);
    if (canal == null) return null;
    try {
      final punto =
          widget.mapController.camera.screenOffsetToLatLng(Offset(x, y));
      return muestraVector(
        canal.cuadro,
        canal.pixels,
        punto.latitude,
        punto.longitude,
      );
    } catch (_) {
      return null;
    }
  }

  ({double x, double y})? _pasoViento(VientoVector? vector, double segundos) {
    if (vector == null || segundos <= 0) return null;
    final rapidez = vector.valor;
    if (rapidez < 1) return null;
    final distancia =
        math.min(60.0, 8 + rapidez * .6) * math.min(segundos, .05);
    return (
      x: vector.u / rapidez * distancia,
      y: -vector.v / rapidez * distancia,
    );
  }

  void _tick() {
    if (!_activo || _particulas.isEmpty) return;
    final ahora = DateTime.now();
    final dt = (ahora.difference(_ultimo).inMicroseconds / 1e6).clamp(0.0, 0.05);
    _ultimo = ahora;
    if (dt <= 0) return;
    for (var i = 0; i < _particulas.length; i++) {
      final p = _particulas[i];
      final paso = _pasoViento(_vectorEn(p.x, p.y), dt);
      if (paso == null || p.edad >= p.duracion) {
        _particulas[i] = _Particula.nacer(p.origenX, p.origenY, _rng);
        continue;
      }
      p.dx = paso.x;
      p.dy = paso.y;
      p.x += paso.x;
      p.y += paso.y;
      p.edad += dt;
    }
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    if (!_activo) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (context, constraints) {
        final next = Size(constraints.maxWidth, constraints.maxHeight);
        if (next != _size) {
          _size = next;
          WidgetsBinding.instance.addPostFrameCallback((_) => _reponer());
        }
        return IgnorePointer(
          child: Opacity(
            opacity: widget.state.opacity.clamp(0.2, 1.0),
            child: CustomPaint(
              size: next,
              painter: _ParticulasPainter(List.of(_particulas)),
            ),
          ),
        );
      },
    );
  }
}

class _Particula {
  _Particula({
    required this.x,
    required this.y,
    required this.origenX,
    required this.origenY,
    required this.duracion,
    required this.edad,
  });

  factory _Particula.nacer(
    double x,
    double y,
    math.Random rng, {
    bool repartir = false,
  }) {
    final duracion = 2 + rng.nextDouble() * 2;
    return _Particula(
      x: x,
      y: y,
      origenX: x,
      origenY: y,
      duracion: duracion,
      edad: repartir ? rng.nextDouble() * duracion : 0,
    );
  }

  double x;
  double y;
  final double origenX;
  final double origenY;
  final double duracion;
  double edad;
  double dx = 0;
  double dy = 0;
}

class _ParticulasPainter extends CustomPainter {
  _ParticulasPainter(this.particulas);

  final List<_Particula> particulas;

  @override
  void paint(Canvas canvas, Size size) {
    final dark = Paint()
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    final light = Paint()
      ..strokeWidth = 1.5
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    for (final p in particulas) {
      final norma = math.sqrt(p.dx * p.dx + p.dy * p.dy);
      if (norma < 1e-6) continue;
      final largo = 7 + math.min(5, norma * 3);
      final colaX = p.x - p.dx / norma * largo;
      final colaY = p.y - p.dy / norma * largo;
      final alpha =
          .3 + .7 * math.sin(math.pi * math.min(1, p.edad / p.duracion));
      dark.color = const Color(0xff173849).withValues(alpha: alpha);
      light.color = Colors.white.withValues(alpha: alpha);
      final cola = Offset(colaX, colaY);
      final punta = Offset(p.x, p.y);
      canvas.drawLine(cola, punta, dark);
      canvas.drawLine(cola, punta, light);
    }
  }

  @override
  bool shouldRepaint(covariant _ParticulasPainter oldDelegate) => true;
}
