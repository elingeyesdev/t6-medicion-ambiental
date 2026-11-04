import 'package:flutter/material.dart';

/// Cabecera de perfil basada en UIKits `ProfileScreen03` / `05`.
class UiKitProfileHeader extends StatelessWidget {
  const UiKitProfileHeader({
    required this.name,
    required this.subtitle,
    this.roleLabel,
    super.key,
  });

  final String name;
  final String subtitle;
  final String? roleLabel;

  String get _initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [scheme.primaryContainer, scheme.secondaryContainer],
        ),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        children: [
          CircleAvatar(
            radius: 44,
            backgroundColor: scheme.primary,
            foregroundColor: scheme.onPrimary,
            child: Text(
              _initials,
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                color: scheme.onPrimary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            name,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: scheme.onPrimaryContainer,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: scheme.onPrimaryContainer.withValues(alpha: 0.85),
            ),
          ),
          if (roleLabel != null) ...[
            const SizedBox(height: 10),
            Chip(
              avatar: Icon(Icons.verified_user_outlined, size: 16, color: scheme.primary),
              label: Text(roleLabel!),
              backgroundColor: scheme.surface.withValues(alpha: 0.85),
            ),
          ],
        ],
      ),
    );
  }
}

/// Bloque estadístico de UIKits `BlockText` (ProfileScreen03).
class UiKitStatBlock extends StatelessWidget {
  const UiKitStatBlock({required this.title, required this.value, super.key});

  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(8),
      child: Column(
        children: [
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          Text(title, style: Theme.of(context).textTheme.labelMedium),
        ],
      ),
    );
  }
}
