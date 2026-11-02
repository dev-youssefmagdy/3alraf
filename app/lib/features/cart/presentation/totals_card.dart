import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../data/cart_models.dart';

/// Server-computed totals. Rows with a zero value are hidden (a display
/// decision only); nothing is added, subtracted or rounded here.
class TotalsCard extends StatelessWidget {
  const TotalsCard({
    super.key,
    required this.totals,
    required this.currency,
    this.showShippingHint = false,
    this.padding = const EdgeInsets.all(AppSpacing.md),
  });

  final CartTotals totals;
  final String currency;

  /// In the cart (no city yet) shipping is "calculated at checkout".
  final bool showShippingHint;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = totals;
    Widget row(
      String label,
      String value, {
      bool strong = false,
      Color? color,
    }) {
      final style = strong
          ? context.text.titleMedium?.copyWith(fontWeight: FontWeight.w800)
          : context.text.bodyMedium;
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(child: Text(label, style: style)),
            Text(
              l10n.currencyAmount(value, currency),
              style: style?.copyWith(color: color),
            ),
          ],
        ),
      );
    }

    return Card(
      child: Padding(
        padding: padding,
        child: Column(
          children: [
            row(l10n.cartSubtotal, t.subtotal),
            if (!isZeroMoney(t.discount))
              row(
                l10n.cartDiscount,
                '-${t.discount}',
                color: context.extra.success,
              ),
            if (!isZeroMoney(t.shippingNet))
              row(l10n.cartShipping, t.shippingNet)
            else if (showShippingHint)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        l10n.cartShipping,
                        style: context.text.bodyMedium,
                      ),
                    ),
                    Text(
                      l10n.cartShippingAtCheckout,
                      style: context.text.bodySmall,
                    ),
                  ],
                ),
              ),
            if (!isZeroMoney(t.packagingFee))
              row(l10n.cartPackagingFee, t.packagingFee),
            if (!isZeroMoney(t.codFee))
              row(t.codFeeLabel ?? l10n.cartCodFee, t.codFee),
            if (!isZeroMoney(t.paymentFee))
              row(l10n.cartPaymentFee, t.paymentFee),
            if (!isZeroMoney(t.tax)) row(l10n.cartTax, t.tax),
            const Divider(height: AppSpacing.md),
            row(l10n.cartTotal, t.total, strong: true),
          ],
        ),
      ),
    );
  }
}
