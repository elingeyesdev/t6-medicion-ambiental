import 'package:flutter/material.dart';

/// Modelo de menú de UIKits (`pages/17_materialu/consts.dart` → `NavMenu`).
class UiKitNavMenu {
  const UiKitNavMenu(this.title, this.icon, this.selectedIcon);

  final String title;
  final IconData icon;
  final IconData selectedIcon;
}
