import 'package:flutter/material.dart';

enum AppButtonVariant { filled, outlined, text }

/// Primary button with built-in loading state and optional icon.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.filled,
    this.icon,
    this.loading = false,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final IconData? icon;
  final bool loading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final cb = loading ? null : onPressed;
    final child = loading
        ? const SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2.4),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 20),
                const SizedBox(width: 8),
              ],
              Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
            ],
          );
    final button = switch (variant) {
      AppButtonVariant.filled => FilledButton(onPressed: cb, child: child),
      AppButtonVariant.outlined => OutlinedButton(onPressed: cb, child: child),
      AppButtonVariant.text => TextButton(onPressed: cb, child: child),
    };
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      enabled: cb != null,
      child: expand ? SizedBox(width: double.infinity, child: button) : button,
    );
  }
}
