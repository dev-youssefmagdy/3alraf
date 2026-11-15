import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Button types from the storefront Button (`5:308`).
///
/// * [filled] / [primary]: brand solid + sticker shadow (add to cart, save…).
/// * [pop]: magenta solid + sticker (checkout-critical / promo CTAs).
/// * [sun]: sunshine solid with ink text (notify me, add selected, hero).
/// * [danger]: red-50 fill with red-700 text (cancel order).
/// * [outlined] / [secondary]: white with a 1px slate-300 border.
/// * [text] / [ghost]: brand text only.
enum AppButtonVariant {
  filled,
  outlined,
  text,
  pop,
  sun,
  danger;

  static const primary = filled;
  static const secondary = outlined;
  static const ghost = text;
}

/// L 52 (every primary screen CTA) · M 44 (default) · S 36 (banners).
enum AppButtonSize {
  l(52, 16),
  m(44, 14),
  s(36, 12);

  const AppButtonSize(this.height, this.fontSize);
  final double height;
  final double fontSize;
}

/// Pill button with loading state, optional icon and the sticker shadow.
class AppButton extends StatefulWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.filled,
    this.size = AppButtonSize.m,
    this.icon,
    this.loading = false,
    this.expand = true,
    this.dense = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final AppButtonSize size;
  final IconData? icon;
  final bool loading;
  final bool expand;

  /// Tighter side padding and icon gap for narrow cards (product card CTA).
  final bool dense;

  @override
  State<AppButton> createState() => _AppButtonState();
}

class _AppButtonState extends State<AppButton> {
  bool _pressed = false;

  bool get _sticker => switch (widget.variant) {
    AppButtonVariant.filled ||
    AppButtonVariant.pop ||
    AppButtonVariant.sun => true,
    _ => false,
  };

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final enabled = !widget.loading && widget.onPressed != null;
    final cb = widget.loading ? null : widget.onPressed;
    final (Color? bg, Color? fg, Color shadow) = switch (widget.variant) {
      AppButtonVariant.filled => (c.primary, c.onPrimary, x.brandStrong),
      AppButtonVariant.pop => (x.pop, Colors.white, x.brandStrong),
      AppButtonVariant.sun => (x.sun, AppColors.slate950, x.brandStrong),
      AppButtonVariant.danger => (
        c.errorContainer,
        c.onErrorContainer,
        c.error,
      ),
      AppButtonVariant.outlined => (null, null, Colors.transparent),
      AppButtonVariant.text => (null, null, Colors.transparent),
    };
    final h = widget.size.height;
    final iconSize = widget.size == AppButtonSize.s ? 16.0 : 20.0;
    final label = Text(
      widget.label,
      overflow: TextOverflow.ellipsis,
      maxLines: 1,
    );
    final child = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (widget.loading) ...[
          SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2.4,
              color: fg ?? c.primary,
            ),
          ),
          const SizedBox(width: 8),
        ] else if (widget.icon != null) ...[
          Icon(widget.icon, size: iconSize),
          SizedBox(width: widget.dense ? 4 : 8),
        ],
        Flexible(
          // Dense buttons shrink the label rather than cut it (large text).
          child: widget.dense
              ? FittedBox(fit: BoxFit.scaleDown, child: label)
              : label,
        ),
      ],
    );
    final textStyle = context.text.labelMedium?.copyWith(
      fontSize: widget.size.fontSize,
    );
    final size = Size(64, h);
    final pad = EdgeInsets.symmetric(
      horizontal: widget.dense
          ? 8
          : widget.size == AppButtonSize.s
          ? 14
          : 20,
    );
    final Widget button = switch (widget.variant) {
      AppButtonVariant.outlined => OutlinedButton(
        onPressed: cb,
        style: OutlinedButton.styleFrom(
          minimumSize: size,
          padding: pad,
          textStyle: textStyle,
        ),
        child: child,
      ),
      AppButtonVariant.text => TextButton(
        onPressed: cb,
        style: TextButton.styleFrom(
          minimumSize: size,
          padding: pad,
          textStyle: textStyle,
        ),
        child: child,
      ),
      _ => FilledButton(
        onPressed: cb,
        style: FilledButton.styleFrom(
          backgroundColor: bg,
          foregroundColor: fg,
          disabledBackgroundColor: bg?.withValues(alpha: 0.45),
          disabledForegroundColor: fg?.withValues(alpha: 0.85),
          minimumSize: size,
          padding: pad,
          textStyle: textStyle,
        ),
        child: child,
      ),
    };
    final pressed = _pressed && enabled;
    Widget decorated = button;
    if (_sticker) {
      // Sticker shadow: pressed moves the button down 2 and halves the shadow.
      decorated = Listener(
        onPointerDown: (_) => setState(() => _pressed = true),
        onPointerUp: (_) => setState(() => _pressed = false),
        onPointerCancel: (_) => setState(() => _pressed = false),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 90),
          transform: Matrix4.translationValues(0, pressed ? 2 : 0, 0),
          decoration: ShapeDecoration(
            shape: const StadiumBorder(),
            shadows: enabled
                ? AppShadows.sticker(pressed: pressed, color: shadow)
                : const [],
          ),
          child: button,
        ),
      );
    }
    return Semantics(
      button: true,
      label: widget.label,
      excludeSemantics: true,
      enabled: cb != null,
      child: widget.expand
          ? SizedBox(width: double.infinity, child: decorated)
          : decorated,
    );
  }
}
