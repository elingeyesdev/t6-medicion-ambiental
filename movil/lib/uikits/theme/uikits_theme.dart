import 'package:flutter/material.dart';

/// Semilla de marca GeoSur (UIKits usa Material 3; aquí fijamos la marca).
///
/// Nota: el demo original usa `dynamic_color` (colores del wallpaper). Ese
/// paquete rompe el build Android actual (Gradle/Kotlin), así que aplicamos
/// el mismo Material 3 del kit con semilla GeoSur estable.
abstract final class GeoSurBrandSeed {
  static const primary = Color(0xFF0B6E4F);
  static const secondary = Color(0xFF1B9AAA);
  static const tertiary = Color(0xFFE07A3D);
  static const canvas = Color(0xFFF2F7F5);
}

abstract final class UiKitsTheme {
  /// Tema Material 3 al estilo UIKits (inputs píldora, NavigationBar, cards).
  static ThemeData light([ColorScheme? scheme]) {
    final base =
        scheme ??
        ColorScheme.fromSeed(
          seedColor: GeoSurBrandSeed.primary,
          primary: GeoSurBrandSeed.primary,
          secondary: GeoSurBrandSeed.secondary,
          tertiary: GeoSurBrandSeed.tertiary,
          surface: Colors.white,
          brightness: Brightness.light,
        );

    return ThemeData(
      useMaterial3: true,
      colorScheme: base,
      scaffoldBackgroundColor: GeoSurBrandSeed.canvas,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      appBarTheme: AppBarTheme(
        backgroundColor: GeoSurBrandSeed.canvas,
        foregroundColor: base.onSurface,
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: base.outlineVariant.withValues(alpha: 0.5)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: base.primaryContainer,
        elevation: 8,
        height: 68,
        shadowColor: Colors.black26,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            color: selected ? base.primary : base.onSurfaceVariant,
          );
        }),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(28),
          borderSide: BorderSide(color: base.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(28),
          borderSide: BorderSide(color: base.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(28),
          borderSide: BorderSide(color: base.primary, width: 1.8),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: base.secondaryContainer,
        selectedColor: base.primaryContainer,
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        showCheckmark: false,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 52),
          backgroundColor: base.primary,
          foregroundColor: base.onPrimary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(28),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(48, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(28),
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: base.primary,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      dividerTheme: DividerThemeData(
        color: base.outlineVariant.withValues(alpha: 0.65),
        space: 1,
      ),
    );
  }

  static ThemeData dark([ColorScheme? scheme]) {
    final base =
        scheme ??
        ColorScheme.fromSeed(
          seedColor: GeoSurBrandSeed.primary,
          brightness: Brightness.dark,
        );
    return ThemeData(useMaterial3: true, colorScheme: base);
  }
}
