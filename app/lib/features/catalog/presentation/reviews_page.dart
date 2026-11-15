import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import 'product_page.dart' show RatingSummary, ReviewTile;
import 'product_sheets.dart';

/// All approved reviews of a product, paginated (`55:2091`): a summary card
/// (score, stars, count), review items and a sticky "write a review" bar
/// when the shopper may review. The summary reuses the product already
/// loaded by the product page underneath.
class ReviewsPage extends ConsumerWidget {
  const ReviewsPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final state = ref.watch(reviewsProvider(slug));
    final controller = ref.read(reviewsProvider(slug).notifier);
    final product = ref.watch(productProvider(slug)).asData?.value;
    Widget body;
    if (state.loading && state.items.isEmpty) {
      body = ListView(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.gutter),
        children: [
          for (var i = 0; i < 4; i++) ...[
            const AppShimmer(height: 110, radius: AppRadius.md),
            const SizedBox(height: AppSpacing.smd),
          ],
        ],
      );
    } else if (state.error != null && state.items.isEmpty) {
      body = ErrorState(
        error: state.error,
        onRetry: () => ref.invalidate(reviewsProvider(slug)),
      );
    } else if (state.items.isEmpty) {
      body = EmptyState(
        title: l10n.noReviews,
        icon: Icons.rate_review_outlined,
        tone: StateTone.sun,
      );
    } else {
      final hasSummary = product != null && product.ratingCount > 0;
      final lead = hasSummary ? 1 : 0;
      body = NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.extentAfter < 300) controller.loadMore();
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          itemCount: state.items.length + lead,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.smd),
          itemBuilder: (_, i) => hasSummary && i == 0
              ? SectionCard(
                  child: RatingSummary(
                    average: product.ratingAverage,
                    count: product.ratingCount,
                    large: true,
                  ),
                )
              : ReviewTile(review: state.items[i - lead]),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(l10n.reviewsTitle)),
      body: body,
      bottomNavigationBar: product != null && product.canReview
          ? StickyActionBar(
              action: AppButton(
                label: l10n.writeReview,
                size: AppButtonSize.l,
                onPressed: () => showReviewSheet(context, slug: slug),
              ),
            )
          : null,
    );
  }
}
