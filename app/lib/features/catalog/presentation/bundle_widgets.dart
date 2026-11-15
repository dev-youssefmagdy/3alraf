import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/product_models.dart';
import 'catalog_widgets.dart';

/// App/Bundle card width (2 columns on 390, or a rail item).
const double bundleCardWidth = 173;

/// Height of a [BundleCard] [width] wide at the current text scale: square
/// media + count, 2-line name, price and the save pill.
double bundleCardExtent(
  BuildContext context, [
  double width = bundleCardWidth,
]) {
  final scale = MediaQuery.textScalerOf(context).scale(1);
  return width + 6 + (18 + 46 + 28) * scale + 4 + 26 + 10;
}

/// App/Bundle card (`43:358`): gold-soft media with a collage of up to 3
/// photos (radius 12, 3px white ring) and the Bundle badge at top-start;
/// then the count line (gold text), 2-line name, Price/M brand + struck
/// regular total and a sun "save X" pill. Sold out shows an ink pill and the
/// photos at 50 %. The whole card opens `/bundle/{slug}`.
class BundleCard extends ConsumerWidget {
  const BundleCard({super.key, required this.bundle});

  final Bundle bundle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final currency = ref.watch(currencyLabelProvider);
    final soldOut = !(bundle.available && bundle.inStock);
    final photos = [
      for (final comp in bundle.components)
        if (comp.image != null && comp.image!.isNotEmpty) comp.image!,
    ];
    if (photos.isEmpty && bundle.image != null) photos.add(bundle.image!);
    return Material(
      color: c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(color: c.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(AppRoutes.bundle(bundle.slug)),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AspectRatio(
                aspectRatio: 1,
                child: Container(
                  decoration: BoxDecoration(
                    color: x.goldSoft,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Opacity(
                        opacity: soldOut ? 0.5 : 1,
                        child: BundleCollage(photos: photos.take(3).toList()),
                      ),
                      PositionedDirectional(
                        top: 6,
                        start: 6,
                        child: StatusChip(
                          label: l10n.bundleBadge,
                          tone: StatusTone.gold,
                          icon: Icons.inventory_2_outlined,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 6),
              if (bundle.components.isNotEmpty)
                Text(
                  l10n.bundleItemsCount(bundle.components.length),
                  style: context.textX.labelXs.copyWith(color: x.goldText),
                ),
              Text(
                bundle.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: context.text.titleSmall,
              ),
              const SizedBox(height: 4),
              PriceText(
                amount: bundle.price,
                currency: currency,
                style: context.textX.priceM.copyWith(color: c.primary),
                oldAmount: bundle.hasSavings ? bundle.regularTotal : null,
              ),
              if (soldOut) ...[
                const SizedBox(height: 4),
                StatusChip(label: l10n.bundleUnavailable, tone: StatusTone.ink),
              ] else if (bundle.hasSavings) ...[
                const SizedBox(height: 4),
                StatusChip(
                  label: l10n.bundleSave(
                    l10n.money(bundle.savings, currency),
                  ),
                  tone: StatusTone.sun,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Up to three photos: one large at the start, two stacked at the end.
class BundleCollage extends StatelessWidget {
  const BundleCollage({super.key, required this.photos});

  final List<String> photos;

  @override
  Widget build(BuildContext context) {
    Widget photo(String url) => Container(
      decoration: BoxDecoration(
        border: Border.all(color: Colors.white, width: 3),
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: NetworkImageX(
        url: url,
        width: double.infinity,
        height: double.infinity,
        radius: AppRadius.sm - 3,
        cacheWidth: 240,
      ),
    );
    if (photos.isEmpty) {
      return Icon(
        Icons.inventory_2_outlined,
        size: 40,
        color: context.extra.goldText,
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 32, 10, 10),
      child: switch (photos.length) {
        1 => photo(photos[0]),
        2 => Row(
          children: [
            Expanded(child: photo(photos[0])),
            const SizedBox(width: 6),
            Expanded(child: photo(photos[1])),
          ],
        ),
        _ => Row(
          children: [
            Expanded(flex: 3, child: photo(photos[0])),
            const SizedBox(width: 6),
            Expanded(
              flex: 2,
              child: Column(
                children: [
                  Expanded(child: photo(photos[1])),
                  const SizedBox(height: 6),
                  Expanded(child: photo(photos[2])),
                ],
              ),
            ),
          ],
        ),
      },
    );
  }
}
