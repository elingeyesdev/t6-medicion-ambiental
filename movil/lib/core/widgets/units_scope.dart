import 'package:flutter/material.dart';

import '../../features/account/model/units_controller.dart';
import '../units/units_presentation.dart';

class UnitsScope extends InheritedNotifier<UnitsController> {
  const UnitsScope({
    required UnitsController controller,
    required super.child,
    super.key,
  }) : super(notifier: controller);

  static UnitsController of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<UnitsScope>();
    assert(scope != null, 'UnitsScope no está en el árbol.');
    return scope!.notifier!;
  }

  static UnitsController? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<UnitsScope>()?.notifier;

  static UnitsPresentation presentationOf(BuildContext context) =>
      of(context).presentation;
}
