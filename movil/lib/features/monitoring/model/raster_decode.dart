import 'dart:math' as math;
import 'dart:typed_data';
import 'dart:ui' as ui;

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

double? decodificarValor(String codigo, int r, int g, int b, int a) {
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
    case 'viento-uv':
      final u = (r / 255 * 200 - 100) * 3.6;
      final v = (g / 255 * 200 - 100) * 3.6;
      return _hypot(u, v);
    case 'viento-uv127':
      final u = (r - 127) / 1.27 * 3.6;
      final v = (g - 127) / 1.27 * 3.6;
      return _hypot(u, v);
    case 'viento-direccion':
      return r / 255 * 150;
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

Future<Uint8List> colorearCuadroPng(CapaCuadro cuadro, Uint8List pngBytes) async {
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
  final pixels = bytes.buffer.asUint8List();
  final out = Uint8List(pixels.length);
  for (var i = 0; i < pixels.length; i += 4) {
    final valor = decodificarValor(
      cuadro.codificacion,
      pixels[i],
      pixels[i + 1],
      pixels[i + 2],
      pixels[i + 3],
    );
    final rgba = colorDeCapa(cuadro.capaId, valor);
    out[i] = rgba[0];
    out[i + 1] = rgba[1];
    out[i + 2] = rgba[2];
    out[i + 3] = rgba[3];
  }
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
  return png.buffer.asUint8List();
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
