import 'package:flutter/material.dart';

abstract final class GeoCruzColors {
  static const green = Color(0xFF126B55);
  static const greenDark = Color(0xFF183C38);
  static const greenSoft = Color(0xFFE8F2ED);
  static const greenLight = Color(0xFFC9EDB5);
  static const textSoft = Color(0xFF526963);
  static const border = Color(0xFFDBE5E0);
  static const background = Color(0xFFF6F8F7);
  static const amber = Color(0xFF8A5A10);
  static const amberSoft = Color(0xFFFFF3DD);
}

abstract final class GeoCruzTheme {
  static ThemeData light() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: GeoCruzColors.green,
      primary: GeoCruzColors.green,
      surface: Colors.white,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: GeoCruzColors.background,
      appBarTheme: const AppBarTheme(
        backgroundColor: GeoCruzColors.background,
        foregroundColor: GeoCruzColors.greenDark,
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      textTheme: const TextTheme(
        headlineLarge: TextStyle(
          color: GeoCruzColors.greenDark,
          fontSize: 32,
          height: 1.12,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.8,
        ),
        headlineMedium: TextStyle(
          color: GeoCruzColors.greenDark,
          fontSize: 24,
          height: 1.2,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.4,
        ),
        titleLarge: TextStyle(
          color: GeoCruzColors.greenDark,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
        bodyLarge: TextStyle(
          color: GeoCruzColors.greenDark,
          fontSize: 15,
          height: 1.45,
        ),
        bodyMedium: TextStyle(
          color: GeoCruzColors.textSoft,
          fontSize: 14,
          height: 1.45,
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: GeoCruzColors.border),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: GeoCruzColors.greenSoft,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          return TextStyle(
            color: states.contains(WidgetState.selected)
                ? GeoCruzColors.green
                : GeoCruzColors.textSoft,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
          );
        }),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: GeoCruzColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: GeoCruzColors.border),
        ),
      ),
    );
  }
}
