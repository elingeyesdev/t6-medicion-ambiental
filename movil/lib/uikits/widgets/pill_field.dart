import 'package:flutter/material.dart';

/// Campo redondeado estilo UIKits SignUp (`signupscreen05` / `03`).
class UiKitPillField extends StatelessWidget {
  const UiKitPillField({
    required this.child,
    this.maxWidth = 430,
    super.key,
  });

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final fieldWidth = width > 412 ? width * 0.5 : width;
    final scheme = Theme.of(context).colorScheme;
    return Align(
      alignment: Alignment.center,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Container(
          width: fieldWidth.clamp(0, maxWidth),
          padding: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLowest,
            borderRadius: BorderRadius.circular(32),
            border: Border.all(color: scheme.outlineVariant),
          ),
          child: child,
        ),
      ),
    );
  }
}
