import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Shows a server-formatted decimal string with the store currency. The app
/// never parses or computes money: [amount] is rendered verbatim.
class PriceText extends StatelessWidget {
  const PriceText({
    super.key,
    required this.amount,
    required this.currency,
    this.oldAmount,
    this.style,
    this.oldStyle,
    this.alignment = WrapAlignment.start,
  });

  final String amount;
  final String currency;

  /// Original price shown struck-through when the item is discounted.
  final String? oldAmount;
  final TextStyle? style;
  final TextStyle? oldStyle;
  final WrapAlignment alignment;

  @override
  Widget build(BuildContext context) {
    final main = (style ?? context.text.titleMedium)?.copyWith(
      fontWeight: FontWeight.w800,
      color: style?.color ?? context.colors.primary,
    );
    final old = (oldStyle ?? context.text.bodySmall)?.copyWith(
      decoration: TextDecoration.lineThrough,
      color: context.colors.onSurfaceVariant,
    );
    return Wrap(
      alignment: alignment,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 6,
      children: [
        Text(context.l10n.currencyAmount(amount, currency), style: main),
        if (oldAmount != null && oldAmount!.isNotEmpty && oldAmount != amount)
          Text(context.l10n.currencyAmount(oldAmount!, currency), style: old),
      ],
    );
  }
}
