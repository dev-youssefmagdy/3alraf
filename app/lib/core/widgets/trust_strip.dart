import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// One trust promise.
class TrustItem {
  const TrustItem(this.icon, this.title, this.text);
  final IconData icon;
  final String title;
  final String text;
}

/// The four storefront promises: fast shipping, cash on delivery, authentic
/// products, gift wrapping.
List<TrustItem> defaultTrustItems(BuildContext context) {
  final l10n = context.l10n;
  return [
    TrustItem(
      Icons.local_shipping_outlined,
      l10n.trustFast,
      l10n.trustFastText,
    ),
    TrustItem(Icons.payments_outlined, l10n.trustCod, l10n.trustCodText),
    TrustItem(
      Icons.verified_outlined,
      l10n.trustAuthentic,
      l10n.trustAuthenticText,
    ),
    TrustItem(Icons.card_giftcard_rounded, l10n.trustGift, l10n.trustGiftText),
  ];
}

/// App/Trust strip (`46:497`).
///
/// * [TrustStrip.band] (end of Home): brand-800 block, radius 24, padding
///   16, 2×2 items, each a 40 sun icon circle + Label/S title + Body/S line
///   in white.
/// * [TrustStrip.row] (under the buy box on Product / Bundle): white card,
///   radius 16, four 36 brand-50 icon circles + Label/XS captions.
class TrustStrip extends StatelessWidget {
  const TrustStrip.band({super.key, this.items}) : compact = false;
  const TrustStrip.row({super.key, this.items}) : compact = true;

  /// Defaults to [defaultTrustItems].
  final List<TrustItem>? items;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final list = items ?? defaultTrustItems(context);
    return compact ? _row(context, list) : _band(context, list);
  }

  Widget _band(BuildContext context, List<TrustItem> list) {
    final x = context.extra;
    Widget cell(TrustItem t) => Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(color: x.sun, shape: BoxShape.circle),
          child: Icon(t.icon, size: 20, color: AppColors.slate950),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                t.title,
                style: context.text.labelSmall?.copyWith(color: Colors.white),
              ),
              Text(
                t.text,
                style: context.text.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.85),
                ),
              ),
            ],
          ),
        ),
      ],
    );
    final rows = <Widget>[];
    for (var i = 0; i < list.length; i += 2) {
      if (i > 0) rows.add(const SizedBox(height: AppSpacing.md));
      rows.add(
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: cell(list[i])),
            const SizedBox(width: AppSpacing.smd),
            Expanded(
              child: i + 1 < list.length
                  ? cell(list[i + 1])
                  : const SizedBox.shrink(),
            ),
          ],
        ),
      );
    }
    return Container(
      key: const ValueKey('trust-band'),
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.gutter),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: x.brandStrong,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(children: rows),
    );
  }

  Widget _row(BuildContext context, List<TrustItem> list) {
    final c = context.colors;
    final x = context.extra;
    return Container(
      key: const ValueKey('trust-row'),
      padding: const EdgeInsets.symmetric(
        vertical: AppSpacing.smd,
        horizontal: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: c.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: c.outlineVariant),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final t in list)
            Expanded(
              child: Column(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: x.brandSoft,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(t.icon, size: 18, color: c.primary),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    t.title,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: context.textX.labelXs,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
