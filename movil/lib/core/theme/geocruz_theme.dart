import 'package:flutter/material.dart';

import '../../uikits/theme/uikits_theme.dart';

export '../../uikits/theme/uikits_theme.dart' show GeoSurBrandSeed, UiKitsTheme;

/// Colores de marca para mapa/marcadores (no son el ColorScheme completo).
/// El tema de la app viene de UIKits → [UiKitsTheme].
abstract final class GeoCruzColors {
  static const green = GeoSurBrandSeed.primary;
  static const greenDark = Color(0xFF0A3D2E);
  static const greenSoft = Color(0xFFD9F0E6);
  static const teal = GeoSurBrandSeed.secondary;
  static const clay = GeoSurBrandSeed.tertiary;
  static const greenLight = Color(0xFFC9EDB5);
  static const textSoft = Color(0xFF4A635A);
  static const border = Color(0xFFD3E4DC);
  static const background = GeoSurBrandSeed.canvas;
  static const amber = Color(0xFF8A5A10);
  static const amberSoft = Color(0xFFFFF3DD);
  static const surfaceContainer = Color(0xFFEEF3F0);
}

/// @Deprecated Prefer [UiKitsTheme.light]. Conservado para imports existentes.
abstract final class GeoCruzTheme {
  static ThemeData light([ColorScheme? dynamicScheme]) =>
      UiKitsTheme.light(dynamicScheme);
}
