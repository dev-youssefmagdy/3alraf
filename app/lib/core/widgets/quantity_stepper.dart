import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Quantity stepper (`7:252`): a white pill (M 48 / S 36 high) with brand-50
/// circles for − and +.
class QuantityStepper extends StatelessWidget {
  const QuantityStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max = 99,
    this.compact = false,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  /// S size (36) for cart lines.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final h = compact ? 36.0 : 48.0;
    final circle = compact ? 28.0 : 40.0;
    Widget step(IconData icon, String tip, VoidCallback? onPressed) =>
        IconButton(
          tooltip: tip,
          icon: Icon(icon, size: compact ? 16 : 20),
          onPressed: onPressed,
          style: IconButton.styleFrom(
            fixedSize: Size.square(circle),
            minimumSize: Size.square(circle),
            padding: EdgeInsets.zero,
            backgroundColor: x.brandSoft,
            foregroundColor: c.primary,
            disabledBackgroundColor: x.brandSoft.withValues(alpha: 0.5),
            disabledForegroundColor: c.primary.withValues(alpha: 0.35),
            tapTargetSize: MaterialTapTargetSize.padded,
          ),
        );
    return Container(
      height: h,
      padding: const EdgeInsets.symmetric(horizontal: 4),
      decoration: ShapeDecoration(
        color: c.surfaceContainerLowest,
        shape: StadiumBorder(side: BorderSide(color: c.outlineVariant)),
      ),
      // Physical "− 1 +" in both directions, as drawn in the RTL frames
      // (Quantity stepper `7:252`, cart line, product page).
      child: Row(
        mainAxisSize: MainAxisSize.min,
        textDirection: TextDirection.ltr,
        children: [
          step(
            Icons.remove_rounded,
            l10n.quantityDecrease,
            value > min ? () => onChanged(value - 1) : null,
          ),
          Semantics(
            label: l10n.quantityLabel(value),
            excludeSemantics: true,
            child: SizedBox(
              width: compact ? 28 : 36,
              child: Text(
                '$value',
                textAlign: TextAlign.center,
                style: context.text.titleMedium,
              ),
            ),
          ),
          step(
            Icons.add_rounded,
            l10n.quantityIncrease,
            value < max ? () => onChanged(value + 1) : null,
          ),
        ],
      ),
    );
  }
}
