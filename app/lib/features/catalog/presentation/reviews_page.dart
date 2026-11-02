import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import 'product_page.dart' show ReviewTile;

/// All approved reviews of a product, paginated.
class ReviewsPage extends ConsumerWidget {
  const ReviewsPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final state = ref.watch(reviewsProvider(slug));
    final controller = ref.read(reviewsProvider(slug).notifier);
    Widget body;
    if (state.loading && state.items.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (state.error != null && state.items.isEmpty) {
      body = ErrorState(
        error: state.error,
        onRetry: () => ref.invalidate(reviewsProvider(slug)),
      );
    } else if (state.items.isEmpty) {
      body = EmptyState(
        title: l10n.noReviews,
        icon: Icons.rate_review_outlined,
      );
    } else {
      body = NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.extentAfter < 300) controller.loadMore();
          return false;
        },
        child: ListView.builder(
          padding: const EdgeInsets.all(AppSpacing.md),
          itemCount: state.items.length,
          itemBuilder: (_, i) => ReviewTile(review: state.items[i]),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(l10n.reviewsTitle)),
      body: body,
    );
  }
}
