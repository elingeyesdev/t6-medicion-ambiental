import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';

import 'capas_models.dart';

/// Decodifica y colorea PNG GFS como en `web/public/js/mapas/raster.js`.
const _lluviaEscalones = <double>[
  0, .2, .5, 1, 2, 3, 4, 5, 7.5, 10, 15, 20, 25, 30, 35, 40, 50, 60, 70, 85, 100, 150,
];

const _rampas = <String, List<(double, String)>>{
  'temperatura': [(-60, '#101060'), (-30, '#5969dc'), (0, '#3ad8db'), (15, '#42bb51'), (30, '#ffe132'), (45, '#e6361d'), (60, '#800000')],
  'lluvia': [(0, '#e5f8ff'), (.2, '#64d9fa'), (2, '#297cd9'), (10, '#2349c2'), (30, '#9d37ae'), (150, '#f2beff')],
  'lluvia_intensidad': [(0, '#e5f8ff'), (.2, '#64d9fa'), (2, '#297cd9'), (10, '#2349c2'), (30, '#9d37ae'), (150, '#f2beff')],
  'viento': [(0, '#afdcff'), (30, '#44c954'), (60, '#ffc928'), (100, '#d74726'), (150, '#b962a9')],
  'visibilidad': [(0, '#883c21'), (1, '#c87934'), (5, '#e3bb76'), (10, '#eee4bc'), (24.14, '#eff7ff')],
  'nieve': [(0, '#f8fcff'), (15, '#adefff'), (50, '#45badf'), (100, '#246eae'), (300, '#601c8c')],
  'aqi': [(0, '#d4f1e0'), (50, '#59b633'), (100, '#edda30'), (150, '#ef8a35'), (200, '#cc3a3f'), (300, '#9d4a98'), (510, '#70152e')],
  'humedad': [(0, '#dec08b'), (25, '#eadca8'), (50, '#b2e5dc'), (75, '#4fabbf'), (100, '#265ba5')],
  'presion': [(900, '#684ab0'), (950, '#71a6d4'), (1000, '#bce5be'), (1050, '#f5bc67'), (1100, '#a33634')],
  'radiacion_solar': [(0, '#fff6d5'), (200, '#ffdf72'), (500, '#f5a33b'), (800, '#df592c'), (1200, '#a32131'), (1600, '#641d44')],
  'evaporacion_potencial': [(-500, '#3657a6'), (0, '#d7edf2'), (250, '#a9cf81'), (500, '#f4d36a'), (1000, '#dc7738'), (1500, '#a6352c')],
};

/// Rampa de colores de la capa (paridad web `rampa`).
List<(double, String)> rampaDeCapa(String capaId) =>
    List<(double, String)>.unmodifiable(_rampas[capaId] ?? const []);

bool esCodificacionViento(String? codigo) => const {
      'viento-uv',
      'viento-uv127',
      'viento-direccion',
    }.contains(codigo);

double? decodificarValor(String codigo, int r, int g, int b, int a) {
  final vector = decodificarVector(codigo, r, g, b, a);
  if (vector != null) return vector.valor;
  if (a == 0) return null;
  switch (codigo) {
    case 'temp-era5':
      return r / 255 * 100 - 50;
    case 'temp-textura':
      return r / 255 * 120 - 60;
    case 'lluvia-escalones':
      final i = r / 255 * 21;
      final inferior = i.floor().clamp(0, 20);
      return _lluviaEscalones[inferior] +
          (i - inferior) *
              (_lluviaEscalones[inferior + 1] - _lluviaEscalones[inferior]);
    case 'vis-etl':
      return r / 255 * 24.140;
    case 'vis-textura':
      return r / 255 * 24.135;
    case 'nieve-etl':
      return r / 255 * 150;
    case 'nieve-fresca-textura':
      return g / 255 * 300;
    case 'aqi-textura':
      return r * 2.0;
    case 'humedad-etl':
      return r / 255 * 100;
    case 'presion-etl':
      return r / 255 * 200 + 900;
    case 'solar-wm2':
      return r / 255 * 1600;
    case 'evap-pot-wm2':
      return r <= 64 ? (r - 64) / 64 * 500 : (r - 64) / 191 * 1500;
    default:
      return null;
  }
}

class VientoVector {
  const VientoVector({required this.u, required this.v, required this.valor});

  final double u;
  final double v;
  final double valor;
}

VientoVector? decodificarVector(String codigo, int r, int g, int b, int a) {
  if (a == 0) return null;
  switch (codigo) {
    case 'viento-uv':
      final u = (r / 255 * 200 - 100) * 3.6;
      final v = (g / 255 * 200 - 100) * 3.6;
      return VientoVector(u: u, v: v, valor: _hypot(u, v));
    case 'viento-uv127':
      final u = (r - 127) / 1.27 * 3.6;
      final v = (g - 127) / 1.27 * 3.6;
      return VientoVector(u: u, v: v, valor: _hypot(u, v));
    case 'viento-direccion':
      final velocidad = r / 255 * 150;
      final angulo = (g / 255 * 360 + 180) * math.pi / 180;
      final u = velocidad * math.sin(angulo);
      final v = velocidad * math.cos(angulo);
      return VientoVector(u: u, v: v, valor: velocidad);
    default:
      return null;
  }
}

({double x, double y})? coordenadaPixel(
  CapaCuadro cuadro,
  double latitud,
  double longitud,
) {
  if (!latitud.isFinite || !longitud.isFinite) return null;
  final r = cuadro.rejilla;
  final ancho = r.este - r.oeste;
  var lon = ((longitud - r.oeste) % 360 + 360) % 360 + r.oeste;
  if (lon > r.este || latitud < r.sur || latitud > r.norte) return null;
  if (ancho == 360 && lon == r.este) lon = r.oeste;
  final centros = r.registro == 'centros';
  final x = (lon - r.oeste) / ancho * (cuadro.ancho - (centros ? 0 : 1)) -
      (centros ? .5 : 0);
  var y = (latitud - r.sur) / (r.norte - r.sur);
  if (r.filas == 'norte-sur') y = 1 - y;
  return (
    x: x,
    y: y * (cuadro.alto - (centros ? 0 : 1)) - (centros ? .5 : 0),
  );
}

Object? _leerPixel(
  CapaCuadro cuadro,
  Uint8List pixeles,
  int x,
  int y, {
  required bool vector,
}) {
  final global = cuadro.rejilla.este - cuadro.rejilla.oeste == 360;
  final periodo =
      cuadro.ancho - (cuadro.rejilla.registro == 'nodos' ? 1 : 0);
  var xx = x;
  var yy = y;
  if (global) {
    xx = ((xx % periodo) + periodo) % periodo;
  } else {
    xx = xx.clamp(0, cuadro.ancho - 1);
  }
  yy = yy.clamp(0, cuadro.alto - 1);
  final i = (yy * cuadro.ancho + xx) * 4;
  if (i + 3 >= pixeles.length) return null;
  final r = pixeles[i];
  final g = pixeles[i + 1];
  final b = pixeles[i + 2];
  final a = pixeles[i + 3];
  if (vector) return decodificarVector(cuadro.codificacion, r, g, b, a);
  return decodificarValor(cuadro.codificacion, r, g, b, a);
}

Object? _muestrear(
  CapaCuadro cuadro,
  Uint8List pixeles,
  double latitud,
  double longitud, {
  bool interpolar = false,
  required bool vector,
}) {
  final coordenada = coordenadaPixel(cuadro, latitud, longitud);
  if (coordenada == null ||
      pixeles.length != cuadro.ancho * cuadro.alto * 4) {
    return null;
  }
  final x = coordenada.x;
  final y = coordenada.y;
  if (!interpolar || cuadro.codificacion == 'aqi-textura') {
    return _leerPixel(
      cuadro,
      pixeles,
      x.round(),
      y.round(),
      vector: vector,
    );
  }
  final x0 = x.floor();
  final y0 = y.floor();
  final fx = x - x0;
  final fy = y - y0;
  final vecinos = <(int, int, double)>[
    (x0, y0, (1 - fx) * (1 - fy)),
    (x0 + 1, y0, fx * (1 - fy)),
    (x0, y0 + 1, (1 - fx) * fy),
    (x0 + 1, y0 + 1, fx * fy),
  ].where((v) => v.$3 > 1e-10);
  var suma = 0.0;
  var u = 0.0;
  var v = 0.0;
  var esVector = false;
  for (final (cx, cy, peso) in vecinos) {
    final valor = _leerPixel(cuadro, pixeles, cx, cy, vector: vector);
    if (valor == null) return null;
    if (valor is VientoVector) {
      esVector = true;
      u += valor.u * peso;
      v += valor.v * peso;
    } else if (valor is num) {
      suma += valor * peso;
    } else {
      return null;
    }
  }
  if (esVector) {
    return VientoVector(u: u, v: v, valor: _hypot(u, v));
  }
  return suma;
}

/// Valor escalar muestreado (paridad web `muestra`).
double? muestraValor(
  CapaCuadro cuadro,
  Uint8List pixeles,
  double latitud,
  double longitud, {
  bool interpolar = false,
}) {
  final valor = _muestrear(
    cuadro,
    pixeles,
    latitud,
    longitud,
    interpolar: interpolar,
    vector: esCodificacionViento(cuadro.codificacion),
  );
  if (valor is VientoVector) return valor.valor;
  if (valor is num) return valor.toDouble();
  return null;
}

/// Vector de viento interpolado (paridad web `muestraVector`).
VientoVector? muestraVector(
  CapaCuadro cuadro,
  Uint8List pixeles,
  double latitud,
  double longitud,
) {
  if (!esCodificacionViento(cuadro.codificacion)) return null;
  final valor = _muestrear(
    cuadro,
    pixeles,
    latitud,
    longitud,
    interpolar: true,
    vector: true,
  );
  return valor is VientoVector ? valor : null;
}

List<int> colorDeCapa(String capa, double? valor) {
  if (valor == null || valor.isNaN || valor.isInfinite) return const [0, 0, 0, 0];
  final stops = _rampas[capa];
  if (stops == null || stops.isEmpty) return const [0, 0, 0, 0];
  var i = stops.indexWhere((s) => s.$1 >= valor);
  if (i < 0) i = stops.length - 1;
  final a = stops[i == 0 ? 0 : i - 1];
  final b = stops[i];
  final t = a.$1 == b.$1 ? 0.0 : ((valor - a.$1) / (b.$1 - a.$1)).clamp(0.0, 1.0);
  final primero = _hexRgb(a.$2);
  final segundo = _hexRgb(b.$2);
  return [
    (primero[0] + (segundo[0] - primero[0]) * t).round(),
    (primero[1] + (segundo[1] - primero[1]) * t).round(),
    (primero[2] + (segundo[2] - primero[2]) * t).round(),
    255,
  ];
}

class CuadroRasterDecoded {
  const CuadroRasterDecoded({
    required this.pixels,
    required this.coloredPng,
  });

  final Uint8List pixels;
  final Uint8List coloredPng;
}

/// Extrae RGBA crudo y genera PNG coloreado para overlay.
Future<CuadroRasterDecoded> decodificarYColorearCuadro(
  CapaCuadro cuadro,
  Uint8List pngBytes,
) async {
  final codec = await ui.instantiateImageCodec(pngBytes);
  final frame = await codec.getNextFrame();
  final image = frame.image;
  if (image.width != cuadro.ancho || image.height != cuadro.alto) {
    image.dispose();
    throw const FormatException('La imagen no coincide con el catálogo de capas.');
  }
  final bytes = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
  image.dispose();
  if (bytes == null) {
    throw const FormatException('No se pudieron leer los píxeles de la capa.');
  }
  final pixels = Uint8List.fromList(bytes.buffer.asUint8List());
  var out = await compute(
    _colorearPixeles,
    _ColorJob(
      pixels: Uint8List.fromList(pixels),
      codificacion: cuadro.codificacion,
      capaId: cuadro.capaId,
    ),
  );
  // OverlayImage asume fila 0 = norte. Con muestra sur-norte hay que invertir filas.
  if (cuadro.rejilla.filas == 'sur-norte') {
    out = _voltearVertical(out, cuadro.ancho, cuadro.alto);
  }
  // El mapa usa Mercator: remuestrear filas para que latitudes intermedias no queden corridas.
  out = await compute(
    _ajustarMercator,
    _MercatorJob(
      pixels: out,
      ancho: cuadro.ancho,
      alto: cuadro.alto,
      sur: cuadro.rejilla.sur,
      norte: cuadro.rejilla.norte,
    ),
  );
  final buffer = await ui.ImmutableBuffer.fromUint8List(out);
  final descriptor = ui.ImageDescriptor.raw(
    buffer,
    width: cuadro.ancho,
    height: cuadro.alto,
    pixelFormat: ui.PixelFormat.rgba8888,
  );
  final coloredCodec = await descriptor.instantiateCodec();
  final coloredFrame = await coloredCodec.getNextFrame();
  final colored = coloredFrame.image;
  final png = await colored.toByteData(format: ui.ImageByteFormat.png);
  colored.dispose();
  if (png == null) {
    throw const FormatException('No se pudo generar la capa coloreada.');
  }
  return CuadroRasterDecoded(
    pixels: pixels,
    coloredPng: png.buffer.asUint8List(),
  );
}

Future<Uint8List> colorearCuadroPng(CapaCuadro cuadro, Uint8List pngBytes) async {
  final decoded = await decodificarYColorearCuadro(cuadro, pngBytes);
  return decoded.coloredPng;
}

class _ColorJob {
  const _ColorJob({
    required this.pixels,
    required this.codificacion,
    required this.capaId,
  });

  final Uint8List pixels;
  final String codificacion;
  final String capaId;
}

Uint8List _colorearPixeles(_ColorJob job) {
  final pixels = job.pixels;
  final out = Uint8List(pixels.length);
  for (var i = 0; i < pixels.length; i += 4) {
    final valor = decodificarValor(
      job.codificacion,
      pixels[i],
      pixels[i + 1],
      pixels[i + 2],
      pixels[i + 3],
    );
    final rgba = colorDeCapa(job.capaId, valor);
    out[i] = rgba[0];
    out[i + 1] = rgba[1];
    out[i + 2] = rgba[2];
    out[i + 3] = rgba[3];
  }
  return out;
}

Uint8List _voltearVertical(Uint8List rgba, int ancho, int alto) {
  final stride = ancho * 4;
  final out = Uint8List(rgba.length);
  for (var y = 0; y < alto; y++) {
    final src = y * stride;
    final dst = (alto - 1 - y) * stride;
    out.setRange(dst, dst + stride, rgba, src);
  }
  return out;
}

class _MercatorJob {
  const _MercatorJob({
    required this.pixels,
    required this.ancho,
    required this.alto,
    required this.sur,
    required this.norte,
  });

  final Uint8List pixels;
  final int ancho;
  final int alto;
  final double sur;
  final double norte;
}

/// Web Mercator (EPSG:3857): Y = ln(tan(π/4 + φ/2)).
double _mercatorY(double latDegrees) {
  final lat = latDegrees.clamp(-85.05112878, 85.05112878) * math.pi / 180;
  return math.log(math.tan(math.pi / 4 + lat / 2));
}

double _latFromMercatorY(double y) {
  return (2 * math.atan(math.exp(y)) - math.pi / 2) * 180 / math.pi;
}

/// Reordena filas para que OverlayImage (lineal en pantalla/Mercator) muestre la lat correcta.
Uint8List _ajustarMercator(_MercatorJob job) {
  final alto = job.alto;
  final ancho = job.ancho;
  if (alto < 2 || (job.norte - job.sur).abs() < 1e-9) return job.pixels;
  final stride = ancho * 4;
  final src = job.pixels;
  final out = Uint8List(src.length);
  final mNorte = _mercatorY(job.norte);
  final mSur = _mercatorY(job.sur);
  final spanLat = job.norte - job.sur;
  for (var y = 0; y < alto; y++) {
    final t = y / (alto - 1); // 0 = norte (arriba)
    final merc = mNorte + t * (mSur - mNorte);
    final lat = _latFromMercatorY(merc);
    final srcYF = ((job.norte - lat) / spanLat) * (alto - 1);
    final y0 = srcYF.floor().clamp(0, alto - 1);
    final y1 = (y0 + 1).clamp(0, alto - 1);
    final fy = (srcYF - y0).clamp(0.0, 1.0);
    final dst = y * stride;
    final row0 = y0 * stride;
    final row1 = y1 * stride;
    for (var x = 0; x < stride; x++) {
      final a = src[row0 + x];
      final b = src[row1 + x];
      out[dst + x] = (a + (b - a) * fy).round().clamp(0, 255);
    }
  }
  return out;
}

double _hypot(double u, double v) => math.sqrt(u * u + v * v);

List<int> _hexRgb(String hex) {
  final h = hex.replaceFirst('#', '');
  return [
    int.parse(h.substring(0, 2), radix: 16),
    int.parse(h.substring(2, 4), radix: 16),
    int.parse(h.substring(4, 6), radix: 16),
  ];
}
