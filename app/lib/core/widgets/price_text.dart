import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Shows a server-formatted decimal string with the store currency. The app
/// never parses or computes money: [amount] is rendered verbatim.
///
/// Defaults to Price/M (Baloo 800 18/24) in brand, or pop-600 when there is
/// a struck [oldAmount]; pass `context.textX.priceL` for the product page.
/// [prefix] renders before the price (e.g. "ابتداءً من" for variable items).
class PriceText extends StatelessWidget {
  const PriceText({
    super.key,
    required this.amount,
    required this.currency,
    this.oldAmount,
    this.style,
    this.oldStyle,
    this.alignment = WrapAlignment.start,
    this.prefix,
  });

  final String amount;
  final String currency;

  /// Original price shown struck-through when the item is discounted.
  final String? oldAmount;
  final TextStyle? style;
  final TextStyle? oldStyle;
  final WrapAlignment alignment;
  final String? prefix;

  bool get onSale =>
      oldAmount != null && oldAmount!.isNotEmpty && oldAmount != amount;

  @override
  Widget build(BuildContext context) {
    final x = context.textX;
    final main = (style ?? x.priceM).copyWith(
      color:
          style?.color ?? (onSale ? context.extra.pop : context.colors.onSurface),
    );
    final old = x.priceOld
        .merge(oldStyle)
        .copyWith(decoration: TextDecoration.lineThrough);
    return Wrap(
      alignment: alignment,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 6,
      children: [
        if (prefix != null) Text(prefix!, style: context.text.bodySmall),
        Text(context.l10n.money(amount, currency), style: main),
        if (onSale)
          Text(context.l10n.money(oldAmount!, currency), style: old),
      ],
    );
  }
}
