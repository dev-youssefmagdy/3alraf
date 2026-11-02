import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/product_models.dart';
import 'catalog_widgets.dart';

/// Bundle card for rails and the bundles grid.
class BundleCard extends ConsumerWidget {
  const BundleCard({super.key, required this.bundle});

  final Bundle bundle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final currency = ref.watch(currencyLabelProvider);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(AppRoutes.bundle(bundle.slug)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  NetworkImageX(url: bundle.image, cacheWidth: 360),
                  if (bundle.hasSavings)
                    PositionedDirectional(
                      top: AppSpacing.sm,
                      start: AppSpacing.sm,
                      child: StatusChip(
                        label: l10n.bundleSave(
                          l10n.currencyAmount(bundle.savings, currency),
                        ),
                        tone: StatusTone.danger,
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.sm + 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    bundle.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.text.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (bundle.available && bundle.inStock)
                    PriceText(
                      amount: bundle.price,
                      currency: currency,
                      oldAmount: bundle.hasSavings ? bundle.regularTotal : null,
                    )
                  else
                    StatusChip(label: l10n.bundleUnavailable),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
