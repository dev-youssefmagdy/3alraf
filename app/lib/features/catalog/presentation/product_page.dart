import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../store/application/store_controller.dart';
import '../../wishlist/application/wishlist_controller.dart';
import '../application/catalog_providers.dart';
import '../data/product_models.dart';
import 'catalog_widgets.dart';
import 'product_gallery.dart';
import 'product_sheets.dart';
import 'quick_add.dart';
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
  final _scroll = ScrollController();
  int _qty = 1;
  bool _adding = false;

  ProductDetail get _p => widget.product;
  ProductVariant? get _variant => VariantPicker.resolve(_p, _selection);

  @override
  void dispose() {
    _gallery.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _select(int optionId, int valueId) {
    setState(() {
      _selection = {..._selection, optionId: valueId};
      _qty = 1;
    });
    final image = _variant?.image;
    if (image != null) {
      final i = _p.gallery.indexWhere((e) => e.url == image);
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
    final url = ref
        .read(storeInfoProvider)
        ?.productUrl(_p.slug, ref.read(localeProvider).languageCode);
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
    if (!mounted) return;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        appSnackBar(
          context,
          now == null
              ? l10n.errorGeneric
              : now
              ? l10n.wishlistAdded
              : l10n.wishlistRemoved,
          tone: now == null ? SnackTone.error : SnackTone.info,
        ),
      );
  }

  Future<void> _addToCart(ProductVariant variant) async {
    setState(() => _adding = true);
    await addVariantToCart(
      context,
      ref,
      productId: _p.id,
      variantId: variant.id,
      quantity: _qty,
    );
    if (mounted) setState(() => _adding = false);
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
    final flashEnds = variant?.flashEnds;
    final topInset = MediaQuery.paddingOf(context).top;
    return Scaffold(
      body: Stack(
        children: [
          ListView(
            controller: _scroll,
            padding: const EdgeInsets.only(bottom: AppSpacing.xl),
            children: [
              SizedBox(height: topInset),
              ProductGallery(
                images: p.gallery,
                controller: _gallery,
                heroTag: widget.heroTag,
                badge: soldOut
                    ? StatusChip(label: l10n.outOfStock, tone: StatusTone.ink)
                    : null,
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.gutter,
                  AppSpacing.xs,
                  AppSpacing.gutter,
                  0,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (p.brand != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: ActionChip(
                          label: Text(p.brand!.name),
                          onPressed: () =>
                              context.push(AppRoutes.brand(p.brand!.slug)),
                          backgroundColor: context.extra.brandSoft,
                          side: BorderSide.none,
                          labelStyle: context.textX.labelXs.copyWith(
                            color: context.colors.primary,
                            fontSize: 12,
                          ),
                          visualDensity: VisualDensity.compact,
                        ),
                      ),
                    Text(p.name, style: context.text.headlineMedium),
                    if (p.ratingCount > 0) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Wrap(
                        spacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          RatingStars(rating: p.ratingAverage),
                          Text(
                            '${p.ratingAverage.toStringAsFixed(1)} '
                            '(${l10n.reviewsCount(p.ratingCount)})',
                            style: context.text.bodySmall?.copyWith(
                              color: context.extra.textMuted,
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: AppSpacing.smd),
                    PriceText(
                      amount: price,
                      currency: currency,
                      oldAmount: oldPrice,
                      // Price/L, pop-600 on sale (Product `54:1852`).
                      style: context.textX.priceL.copyWith(
                        color:
                            oldPrice != null &&
                                oldPrice.isNotEmpty &&
                                oldPrice != price
                            ? context.extra.pop
                            : null,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.smd),
                    Wrap(
                      spacing: AppSpacing.sm,
                      runSpacing: AppSpacing.sm,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        _StockBadge(
                          variant: variant,
                          soldOut: soldOut,
                          product: p,
                        ),
                        if (flashEnds != null &&
                            flashEnds.isAfter(DateTime.now()))
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                l10n.flashEndsIn,
                                style: context.text.bodySmall,
                              ),
                              const SizedBox(width: 6),
                              CountdownChip(
                                endsAt: flashEnds,
                                onFinished: () =>
                                    ref.invalidate(productProvider(p.slug)),
                              ),
                            ],
                          ),
                      ],
                    ),
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
                          style: context.text.bodySmall?.copyWith(
                            color: context.colors.error,
                          ),
                        ),
                      ),
                    if (!soldOut && variant != null) ...[
                      const SizedBox(height: AppSpacing.md),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              l10n.quantity,
                              style: context.text.titleSmall,
                            ),
                          ),
                          QuantityStepper(
                            value: _qty,
                            max: maxQty,
                            onChanged: (v) => setState(() => _qty = v),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: AppSpacing.md),
                    const TrustStrip.row(),
                    if (_hasText(p.description) || p.sku != null) ...[
                      const SizedBox(height: AppSpacing.md),
                      SectionCard(
                        title: l10n.description,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (_hasText(p.description))
                              HtmlWidget(
                                p.description!,
                                textStyle: context.text.bodyMedium?.copyWith(
                                  color: context.colors.onSurfaceVariant,
                                ),
                                // No remote scripts/webviews: core renders a
                                // safe subset.
                              ),
                            if (p.sku != null) ...[
                              const SizedBox(height: AppSpacing.sm),
                              Text(
                                '${l10n.sku}: ${p.sku}',
                                style: context.text.bodySmall?.copyWith(
                                  color: context.extra.textMuted,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.md),
                    _ReviewsSection(product: p),
                  ],
                ),
              ),
              ProductRail(
                title: l10n.relatedProducts,
                products: p.related,
                scope: 'related',
              ),
            ],
          ),
          // Once the gallery has scrolled away, the over-media buttons get
          // a solid surface strip so page content never shows behind them.
          PositionedDirectional(
            top: 0,
            start: 0,
            end: 0,
            height: topInset + AppSpacing.sm * 2 + 44,
            child: IgnorePointer(
              child: AnimatedBuilder(
                animation: _scroll,
                builder: (context, _) {
                  final past =
                      _scroll.hasClients &&
                      _scroll.offset > MediaQuery.sizeOf(context).width - 72;
                  return AnimatedOpacity(
                    opacity: past ? 1 : 0,
                    duration: context.reduceMotion
                        ? Duration.zero
                        : const Duration(milliseconds: 150),
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: context.colors.surface,
                        border: Border(
                          bottom: BorderSide(
                            color: context.colors.outlineVariant,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
          PositionedDirectional(
            top: topInset + AppSpacing.sm,
            start: AppSpacing.smd,
            end: AppSpacing.smd,
            child: Row(
              children: [
                _GlassButton(
                  tooltip: MaterialLocalizations.of(context).backButtonTooltip,
                  icon: Icons.chevron_left_rounded,
                  onPressed: () => Navigator.of(context).maybePop(),
                ),
                const Spacer(),
                _GlassButton(
                  tooltip: l10n.share,
                  icon: Icons.share_outlined,
                  onPressed: _share,
                ),
                const SizedBox(width: AppSpacing.sm),
                _GlassButton(
                  tooltip: favorite ? l10n.wishlistRemove : l10n.wishlistAdd,
                  icon: favorite
                      ? Icons.favorite_rounded
                      : Icons.favorite_border_rounded,
                  color: context.extra.pop,
                  onPressed: _toggleWishlist,
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: _BuyBar(
        busy: _adding,
        soldOut: soldOut,
        needsSelection: selectable,
        price: price,
        oldPrice: oldPrice,
        currency: currency,
        favorite: favorite,
        onFavorite: _toggleWishlist,
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

/// 44 white circle with `shadowRaised` over the gallery (Over-media bar).
class _GlassButton extends StatelessWidget {
  const _GlassButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
    this.color,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;
  final Color? color;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: const BoxDecoration(
      shape: BoxShape.circle,
      boxShadow: AppShadows.raised,
    ),
    child: IconButton(
      tooltip: tooltip,
      onPressed: onPressed,
      style: IconButton.styleFrom(
        backgroundColor: Colors.white,
        foregroundColor: color ?? AppColors.slate950,
        fixedSize: const Size.square(44),
      ),
      icon: Icon(icon, size: 22),
    ),
  );
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

/// Sticky Product bar (`47:750`): price (+ old price) · 52 outline
/// wishlist circle · Primary L add to cart, or Sun L notify-me when sold out.
class _BuyBar extends StatelessWidget {
  const _BuyBar({
    required this.busy,
    required this.soldOut,
    required this.needsSelection,
    required this.price,
    required this.oldPrice,
    required this.currency,
    required this.favorite,
    required this.onFavorite,
    required this.onAdd,
    required this.onChoose,
    required this.onNotify,
  });

  final bool busy;
  final bool soldOut;
  final bool needsSelection;
  final String price;
  final String? oldPrice;
  final String currency;
  final bool favorite;
  final VoidCallback onFavorite;
  final VoidCallback? onAdd;
  final VoidCallback onChoose;
  final VoidCallback onNotify;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final onSale =
        oldPrice != null && oldPrice!.isNotEmpty && oldPrice != price;
    return StickyActionBar(
      key: const ValueKey('product-buy-bar'),
      leading: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 110),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: AlignmentDirectional.centerStart,
                  child: Text(
                    l10n.money(price, currency),
                    style: context.textX.priceM.copyWith(
                      color: onSale ? context.extra.pop : c.primary,
                    ),
                  ),
                ),
                if (onSale)
                  Text(
                    l10n.money(oldPrice!, currency),
                    style: context.textX.priceOld.copyWith(
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.smd),
          SizedBox.square(
            dimension: 52,
            child: IconButton.outlined(
              tooltip: favorite ? l10n.wishlistRemove : l10n.wishlistAdd,
              onPressed: onFavorite,
              style: IconButton.styleFrom(
                side: BorderSide(color: c.outline),
                foregroundColor: context.extra.pop,
              ),
              icon: Icon(
                favorite
                    ? Icons.favorite_rounded
                    : Icons.favorite_border_rounded,
              ),
            ),
          ),
        ],
      ),
      action: soldOut
          ? AppButton(
              label: l10n.notifyMe,
              icon: Icons.notifications_active_outlined,
              variant: AppButtonVariant.sun,
              size: AppButtonSize.l,
              dense: true,
              onPressed: onNotify,
            )
          : AppButton(
              label: needsSelection ? l10n.chooseOptions : l10n.addToCart,
              icon: Icons.shopping_cart_outlined,
              size: AppButtonSize.l,
              dense: true,
              loading: busy,
              onPressed: needsSelection ? onChoose : onAdd,
            ),
    );
  }
}

/// Reviews card: score, stars and count, up to two review items, the
/// "write a review" outline button and "view all".
class _ReviewsSection extends ConsumerWidget {
  const _ReviewsSection({required this.product});

  final ProductDetail product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final p = product;
    final shown = p.reviews.take(2).toList();
    return SectionCard(
      title: l10n.reviewsTitle,
      trailing: p.ratingCount > shown.length
          ? ViewAllLink(
              onPressed: () => context.push(AppRoutes.productReviews(p.slug)),
            )
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (p.ratingCount > 0)
            RatingSummary(average: p.ratingAverage, count: p.ratingCount)
          else
            Text(
              l10n.noReviews,
              style: context.text.bodyMedium?.copyWith(
                color: context.colors.onSurfaceVariant,
              ),
            ),
          for (final r in shown) ...[
            const SizedBox(height: AppSpacing.smd),
            ReviewTile(review: r),
          ],
          if (p.canReview) ...[
            const SizedBox(height: AppSpacing.smd),
            AppButton(
              label: l10n.writeReview,
              variant: AppButtonVariant.outlined,
              icon: Icons.rate_review_outlined,
              onPressed: () => showReviewSheet(context, slug: p.slug),
            ),
          ],
        ],
      ),
    );
  }
}

/// Big score (Display), stars and the review count.
class RatingSummary extends StatelessWidget {
  const RatingSummary({
    super.key,
    required this.average,
    required this.count,
    this.large = false,
  });

  final double average;
  final int count;
  final bool large;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Text(
        average.toStringAsFixed(1),
        style: large ? context.text.displayMedium : context.text.displaySmall,
      ),
      const SizedBox(width: AppSpacing.md),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            RatingStars(rating: average, size: large ? 20 : 16),
            const SizedBox(height: 2),
            Text(
              context.l10n.reviewsCount(count),
              style: context.text.bodySmall?.copyWith(
                color: context.extra.textMuted,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

/// Review item (`21:2061`): a bordered card with a violet initial circle,
/// the author, stars and the text.
class ReviewTile extends StatelessWidget {
  const ReviewTile({super.key, required this.review});

  final Review review;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final c = context.colors;
    final author = review.author;
    final initial = author == null || author.isEmpty
        ? '★'
        : author.characters.first;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.smd),
      decoration: BoxDecoration(
        color: c.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: c.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ExcludeSemantics(
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: x.violetSoft,
                  child: Text(
                    initial,
                    style: context.text.labelMedium?.copyWith(
                      color: x.violetText,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              if (author != null)
                Expanded(
                  child: Text(
                    author,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.text.titleSmall,
                  ),
                )
              else
                const Spacer(),
              RatingStars(rating: review.rating.toDouble(), size: 14),
            ],
          ),
          if (review.body != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              review.body!,
              style: context.text.bodyMedium?.copyWith(
                color: c.onSurfaceVariant,
              ),
            ),
          ],
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
