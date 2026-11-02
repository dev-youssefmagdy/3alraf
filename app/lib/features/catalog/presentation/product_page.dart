import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../../tenant/application/tenant_controller.dart';
import '../../wishlist/application/wishlist_controller.dart';
import '../../cart/application/cart_controller.dart';
import '../application/catalog_providers.dart';
import '../data/product_models.dart';
import 'catalog_widgets.dart';
import 'product_gallery.dart';
import 'product_sheets.dart';
import 'variant_picker.dart';

/// Product detail. Mirrors `storefront/product.blade.php`.
class ProductPage extends ConsumerWidget {
  const ProductPage({super.key, required this.slug, this.heroTag});

  final String slug;
  final ProductHeroTag? heroTag;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final product = ref.watch(productProvider(slug));
    return product.when(
      loading: () => Scaffold(appBar: AppBar(), body: const _ProductSkeleton()),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorState(
          error: e,
          onRetry: () => ref.invalidate(productProvider(slug)),
        ),
      ),
      data: (p) =>
          ProductView(key: ValueKey(p.id), product: p, heroTag: heroTag),
    );
  }
}

class ProductView extends ConsumerStatefulWidget {
  const ProductView({super.key, required this.product, this.heroTag});

  final ProductDetail product;
  final ProductHeroTag? heroTag;

  @override
  ConsumerState<ProductView> createState() => _ProductViewState();
}

class _ProductViewState extends ConsumerState<ProductView> {
  late Map<int, int> _selection = VariantPicker.defaultSelection(
    widget.product,
  );
  final _gallery = PageController();
  int _qty = 1;
  bool _adding = false;

  ProductDetail get _p => widget.product;
  ProductVariant? get _variant => VariantPicker.resolve(_p, _selection);

  @override
  void dispose() {
    _gallery.dispose();
    super.dispose();
  }

  void _select(int optionId, int valueId) {
    setState(() {
      _selection = {..._selection, optionId: valueId};
      _qty = 1;
    });
    final image = _variant?.image;
    if (image != null) {
      final i = _p.images.indexWhere((e) => e.url == image);
      if (i >= 0 && _gallery.hasClients) {
        _gallery.animateToPage(
          i,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    }
  }

  Future<void> _share() async {
    final base = ref.read(tenantControllerProvider).tenant?.baseUrl;
    final url = base == null
        ? null
        : '${base.endsWith('/') ? base : '$base/'}product/${_p.slug}';
    await SharePlus.instance.share(
      ShareParams(text: url == null ? _p.name : '${_p.name}\n$url'),
    );
  }

  Future<void> _toggleWishlist() async {
    if (!ref.read(authControllerProvider).signedIn) {
      promptSignIn(context);
      return;
    }
    final l10n = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final now = await ref.read(wishlistProvider.notifier).toggle(_p.id);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(
            now == null
                ? l10n.errorGeneric
                : now
                ? l10n.wishlistAdded
                : l10n.wishlistRemoved,
          ),
        ),
      );
  }

  Future<void> _addToCart(ProductVariant variant) async {
    final l10n = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    setState(() => _adding = true);
    try {
      final message = await ref
          .read(cartControllerProvider.notifier)
          .add(productId: _p.id, variantId: variant.id, quantity: _qty);
      HapticFeedback.mediumImpact();
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(message ?? l10n.addedToCart),
            action: SnackBarAction(
              label: l10n.viewCart,
              onPressed: () => router.go(AppRoutes.cart),
            ),
          ),
        );
    } on ApiException catch (e) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(errorMessage(l10n, e))));
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final p = _p;
    final variant = _variant;
    final currency = ref.watch(currencyLabelProvider);
    final wish = ref.watch(wishlistProvider);
    final favorite = wish.loaded ? wish.ids.contains(p.id) : p.inWishlist;
    // Show the resolved variant's price; before a full selection, the base.
    final price = variant?.price ?? p.price;
    final oldPrice = variant != null ? variant.oldPrice : p.regularPrice;
    final selectable = p.isVariable && variant == null;
    final soldOut = variant != null ? !variant.inStock : !p.anyInStock;
    final maxQty = variant?.stock ?? 99;
    return Scaffold(
      appBar: AppBar(
        actions: [
          IconButton(
            tooltip: l10n.share,
            icon: const Icon(Icons.ios_share_rounded),
            onPressed: _share,
          ),
          IconButton(
            tooltip: favorite ? l10n.wishlistRemove : l10n.wishlistAdd,
            icon: Icon(
              favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
              color: context.colors.primary,
            ),
            onPressed: _toggleWishlist,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.only(bottom: AppSpacing.xl),
        children: [
          ProductGallery(
            images: _p.images,
            controller: _gallery,
            heroTag: widget.heroTag,
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (p.brand != null)
                  InkWell(
                    onTap: () => context.push(AppRoutes.brand(p.brand!.slug)),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Text(
                        p.brand!.name,
                        style: context.text.labelLarge?.copyWith(
                          color: context.colors.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                Text(
                  p.name,
                  style: context.text.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (p.ratingCount > 0) ...[
                  const SizedBox(height: AppSpacing.xs),
                  RatingStars(rating: p.ratingAverage, count: p.ratingCount),
                ],
                const SizedBox(height: AppSpacing.sm),
                PriceText(
                  amount: price,
                  currency: currency,
                  oldAmount: oldPrice,
                  style: context.text.headlineSmall,
                ),
                if (variant?.flashEnds != null &&
                    variant!.flashEnds!.isAfter(DateTime.now())) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: [
                      Text(l10n.flashEndsIn, style: context.text.bodySmall),
                      const SizedBox(width: AppSpacing.sm),
                      CountdownChip(
                        endsAt: variant.flashEnds!,
                        onFinished: () =>
                            ref.invalidate(productProvider(p.slug)),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: AppSpacing.sm),
                _StockBadge(variant: variant, soldOut: soldOut, product: p),
                if (p.isVariable)
                  VariantPickerView(
                    product: p,
                    selection: _selection,
                    onSelect: _select,
                  ),
                if (selectable && _selection.length == p.options.length)
                  Padding(
                    padding: const EdgeInsets.only(top: AppSpacing.sm),
                    child: Text(
                      l10n.unavailableCombo,
                      style: TextStyle(color: context.colors.error),
                    ),
                  ),
                if (!soldOut && variant != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  Row(
                    children: [
                      Text(l10n.quantity, style: context.text.titleSmall),
                      const SizedBox(width: AppSpacing.md),
                      QuantityStepper(
                        value: _qty,
                        max: maxQty,
                        onChanged: (v) => setState(() => _qty = v),
                      ),
                    ],
                  ),
                ],
                if (_hasText(p.description)) ...[
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    l10n.description,
                    style: context.text.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  HtmlWidget(
                    p.description!,
                    textStyle: context.text.bodyMedium,
                    // No remote scripts/webviews: core renders a safe subset.
                  ),
                ],
                if (p.sku != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    '${l10n.sku}: ${p.sku}',
                    style: context.text.bodySmall?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
          _ReviewsSection(product: p),
          ProductRail(
            title: l10n.relatedProducts,
            products: p.related,
            scope: 'related',
          ),
        ],
      ),
      bottomNavigationBar: _BuyBar(
        busy: _adding,
        soldOut: soldOut,
        needsSelection: selectable,
        price: price,
        currency: currency,
        onAdd: variant == null ? null : () => _addToCart(variant),
        onChoose: () => context.showSnack(l10n.chooseOptions),
        onNotify: () => showNotifyMeSheet(
          context,
          productId: p.id,
          variantId: variant?.id ?? p.variants.firstOrNull?.id ?? 0,
        ),
      ),
    );
  }
}

bool _hasText(String? s) => s != null && s.trim().isNotEmpty;

class _StockBadge extends StatelessWidget {
  const _StockBadge({
    required this.variant,
    required this.soldOut,
    required this.product,
  });

  final ProductVariant? variant;
  final bool soldOut;
  final ProductDetail product;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (soldOut) {
      return StatusChip(label: l10n.outOfStock, tone: StatusTone.danger);
    }
    final v = variant;
    if (v == null) return const SizedBox.shrink();
    if (v.isLowStock) {
      return StatusChip(
        label: l10n.lowStock(v.stock ?? 0),
        tone: StatusTone.warning,
      );
    }
    return StatusChip(label: l10n.inStock, tone: StatusTone.success);
  }
}

/// Sticky bottom bar: price + the primary action for the current state.
class _BuyBar extends StatelessWidget {
  const _BuyBar({
    required this.busy,
    required this.soldOut,
    required this.needsSelection,
    required this.price,
    required this.currency,
    required this.onAdd,
    required this.onChoose,
    required this.onNotify,
  });

  final bool busy;
  final bool soldOut;
  final bool needsSelection;
  final String price;
  final String currency;
  final VoidCallback? onAdd;
  final VoidCallback onChoose;
  final VoidCallback onNotify;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Material(
      elevation: 8,
      color: context.colors.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Expanded(
                flex: 2,
                child: PriceText(amount: price, currency: currency),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                flex: 3,
                child: soldOut
                    ? AppButton(
                        label: l10n.notifyMe,
                        icon: Icons.notifications_active_outlined,
                        variant: AppButtonVariant.outlined,
                        onPressed: onNotify,
                      )
                    : AppButton(
                        label: needsSelection
                            ? l10n.chooseOptions
                            : l10n.addToCart,
                        icon: Icons.add_shopping_cart_rounded,
                        loading: busy,
                        onPressed: needsSelection ? onChoose : onAdd,
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReviewsSection extends ConsumerWidget {
  const _ReviewsSection({required this.product});

  final ProductDetail product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final p = product;
    final shown = p.reviews.take(3).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(
          title: l10n.reviewsTitle,
          onViewAll: p.ratingCount > shown.length
              ? () => context.push(AppRoutes.productReviews(p.slug))
              : null,
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (p.ratingCount > 0)
                Row(
                  children: [
                    Text(
                      p.ratingAverage.toStringAsFixed(1),
                      style: context.text.displaySmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    RatingStars(rating: p.ratingAverage, count: p.ratingCount),
                  ],
                )
              else
                Text(l10n.noReviews, style: context.text.bodyMedium),
              for (final r in shown) ReviewTile(review: r),
              if (p.canReview)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.sm),
                  child: AppButton(
                    label: l10n.writeReview,
                    variant: AppButtonVariant.outlined,
                    icon: Icons.rate_review_outlined,
                    onPressed: () => showReviewSheet(context, slug: p.slug),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class ReviewTile extends StatelessWidget {
  const ReviewTile({super.key, required this.review});

  final Review review;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              RatingStars(rating: review.rating.toDouble(), size: 14),
              const SizedBox(width: AppSpacing.sm),
              if (review.author != null)
                Text(review.author!, style: context.text.labelMedium),
            ],
          ),
          if (review.body != null) ...[
            const SizedBox(height: 4),
            Text(review.body!, style: context.text.bodyMedium),
          ],
          const Divider(height: AppSpacing.md),
        ],
      ),
    );
  }
}

class _ProductSkeleton extends StatelessWidget {
  const _ProductSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const NeverScrollableScrollPhysics(),
    children: const [
      AspectRatio(
        aspectRatio: 1,
        child: AppShimmer(height: double.infinity, radius: 0),
      ),
      Padding(
        padding: EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AppShimmer(width: 80, height: 14),
            SizedBox(height: 12),
            AppShimmer(height: 24),
            SizedBox(height: 12),
            AppShimmer(width: 120, height: 24),
            SizedBox(height: 20),
            AppShimmer(height: 44),
          ],
        ),
      ),
    ],
  );
}
