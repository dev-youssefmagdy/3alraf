import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/cart_models.dart';

/// Server-computed totals (Order totals `21:1971`). Rows with a zero value
/// are hidden (a display decision only); nothing is added, subtracted or
/// rounded here. The total is Price/L brand; when the server splits a COD
/// order (prepaid shipping + amount on delivery) the mint block shows both.
class TotalsCard extends StatelessWidget {
  const TotalsCard({
    super.key,
    required this.totals,
    required this.currency,
    this.showShippingHint = false,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.bare = false,
  });

  final CartTotals totals;
  final String currency;

  /// In the cart (no city yet) shipping is "calculated at checkout".
  final bool showShippingHint;
  final EdgeInsetsGeometry padding;

  /// Without the card frame (when nested in another card).
  final bool bare;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final t = totals;
    Widget row(String label, String value, {Color? color, bool raw = false}) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: context.text.bodyMedium?.copyWith(
                  color: c.onSurfaceVariant,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Text(
              raw ? value : l10n.money(value, currency),
              style: context.text.titleSmall?.copyWith(color: color),
            ),
          ],
        ),
      );
    }

    final split = !isZeroMoney(t.prepaidShipping) && !isZeroMoney(t.codAmount);
    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        row(l10n.cartSubtotal, t.subtotal),
        if (!isZeroMoney(t.discount))
          row(l10n.cartDiscount, '-${t.discount}', color: x.success),
        if (!isZeroMoney(t.shippingNet))
          row(l10n.cartShipping, t.shippingNet)
        else if (showShippingHint)
          row(l10n.cartShipping, l10n.cartShippingAtCheckout, raw: true),
        if (!isZeroMoney(t.packagingFee))
          row(l10n.cartPackagingFee, t.packagingFee),
        if (!isZeroMoney(t.codFee))
          row(t.codFeeLabel ?? l10n.cartCodFee, t.codFee),
        if (!isZeroMoney(t.paymentFee)) row(l10n.cartPaymentFee, t.paymentFee),
        if (!isZeroMoney(t.tax)) row(l10n.cartTax, t.tax),
        const Divider(height: AppSpacing.lg),
        Row(
          children: [
            Expanded(
              child: Text(l10n.cartTotal, style: context.text.titleLarge),
            ),
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: AlignmentDirectional.centerEnd,
                child: Text(
                  l10n.money(t.total, currency),
                  style: context.textX.priceL.copyWith(color: c.primary),
                ),
              ),
            ),
          ],
        ),
        if (split) ...[
          const SizedBox(height: AppSpacing.smd),
          Container(
            key: const ValueKey('totals-cod-split'),
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.smd,
              vertical: AppSpacing.sm,
            ),
            decoration: BoxDecoration(
              color: x.mintSoft,
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: Column(
              children: [
                for (final (label, value) in [
                  (l10n.cartPayNowShipping, t.prepaidShipping),
                  (l10n.cartPayOnDelivery, t.codAmount),
                ])
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 2),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            label,
                            style: context.text.bodySmall?.copyWith(
                              color: x.mintStrong,
                            ),
                          ),
                        ),
                        Text(
                          l10n.money(value, currency),
                          style: context.text.labelSmall?.copyWith(
                            color: x.mintStrong,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
    if (bare) return content;
    return SectionCard(padding: padding, child: content);
  }
}
