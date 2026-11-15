import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/routing/link_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../../store/application/store_controller.dart';
import '../../wishlist/application/wishlist_controller.dart';
import '../data/catalog_models.dart';
import 'quick_add.dart';

/// Currency label of the active store (server provides the symbol).
final currencyLabelProvider = Provider<String>(
  (ref) => ref.watch(storeControllerProvider).currencyLabel,
);

/// Identifies one product image across a list -> detail Hero flight. The
/// [scope] keeps tags unique when the same product appears in two rails.
class ProductHeroTag {
  const ProductHeroTag(this.scope, this.slug);
  final String scope;
  final String slug;

  @override
  bool operator ==(Object other) =>
      other is ProductHeroTag && other.scope == scope && other.slug == slug;

  @override
  int get hashCode => Object.hash(scope, slug);
}

/// Opens a product page, passing the hero tag for the image transition.
void openProduct(BuildContext context, String slug, [ProductHeroTag? hero]) {
  context.push(AppRoutes.product(slug), extra: hero);
}

/// Sends a guest to sign in and brings them back to the current screen.
void promptSignIn(BuildContext context) {
  final from = GoRouterState.of(context).uri.toString();
  context.push(AppRoutes.loginWithReturn(from));
}

/// Opens a banner / deep link inside the app or in the browser.
Future<void> openLink(BuildContext context, String? link) async {
  final target = resolveLink(link);
  if (target == null) return;
  if (target.route != null) {
    final route = target.route!;
    const tabs = [
      AppRoutes.home,
      AppRoutes.categories,
      AppRoutes.cart,
      AppRoutes.wishlist,
      AppRoutes.account,
    ];
    if (tabs.contains(route)) {
      context.go(route);
    } else {
      context.push(route);
    }
  } else if (target.external != null) {
    await launchUrl(target.external!, mode: LaunchMode.externalApplication);
  }
}

/// Product card wired to navigation, the wishlist heart and the CTA. The
/// CTA ("add to cart") runs [quickAdd]: simple items go straight to the
/// cart, variable items open the variant sheet and sold-out items open the
/// notify-me sheet.
class ProductTile extends ConsumerStatefulWidget {
  const ProductTile({
    super.key,
    required this.product,
    required this.heroScope,
    this.index,
    this.showCta = true,
  });

  final ProductSummary product;
  final String heroScope;

  /// Position in its grid / rail; rotates the media tint.
  final int? index;
  final bool showCta;

  @override
  ConsumerState<ProductTile> createState() => _ProductTileState();
}

class _ProductTileState extends ConsumerState<ProductTile> {
  bool _busy = false;

  Future<void> _cta() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await quickAdd(context, ref, widget.product);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _favorite() async {
    final l10n = context.l10n;
    final product = widget.product;
    if (!ref.read(authControllerProvider).signedIn) {
      promptSignIn(context);
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final now = await ref
        .read(wishlistProvider.notifier)
        .toggle(product.id, product: product);
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

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    final favorite = ref.watch(
      wishlistIdsProvider.select((ids) => ids.contains(product.id)),
    );
    final tag = ProductHeroTag(widget.heroScope, product.slug);
    final flashEnds = product.flashEndsAt;
    return ProductCard(
      title: product.name,
      price: product.price,
      oldPrice: product.oldPrice,
      currency: ref.watch(currencyLabelProvider),
      imageUrl: product.image,
      discountPercent: product.discountPercent?.toString(),
      rating: product.rating,
      reviewCount: product.reviewsCount,
      favorite: favorite,
      heroTag: tag,
      tintIndex: widget.index,
      flashEndsAt:
          product.flash &&
              flashEnds != null &&
              flashEnds.isAfter(DateTime.now())
          ? flashEnds
          : null,
      ctaLoading: _busy,
      onTap: () => openProduct(context, product.slug, tag),
      onFavorite: _favorite,
      onAddToCart: widget.showCta ? _cta : null,
    );
  }
}

/// True when some card in [items] shows the flash countdown row.
bool anyFlash(Iterable<ProductSummary> items) {
  final now = DateTime.now();
  return items.any(
    (p) => p.flash && p.flashEndsAt != null && p.flashEndsAt!.isAfter(now),
  );
}

/// Whether any item shows a struck old price (it wraps to a second line on
/// narrow cards, so fixed cells need room for it).
bool anySale(Iterable<ProductSummary> items) => items.any(
  (p) => p.oldPrice != null && p.oldPrice!.isNotEmpty && p.oldPrice != p.price,
);

/// 2-column grid (3 / 4 on wider screens), gap 12, cells tall enough for
/// the card with its CTA at the current text scale.
SliverGridDelegate productGridDelegate(
  BuildContext context, {
  bool withFlash = false,
  bool withSale = false,
  bool withCta = true,
}) {
  final width = MediaQuery.sizeOf(context).width;
  final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
  final cell =
      (width - AppSpacing.md * 2 - AppSpacing.grid * (columns - 1)) / columns;
  return SliverGridDelegateWithFixedCrossAxisCount(
    crossAxisCount: columns,
    mainAxisSpacing: AppSpacing.grid,
    crossAxisSpacing: AppSpacing.grid,
    mainAxisExtent: ProductCard.extentFor(
      context,
      cell,
      withFlash: withFlash,
      withSale: withSale,
      withCta: withCta,
    ),
  );
}

/// Skeleton of App/Product card (bones on slate-100, same radii).
class ProductCardSkeleton extends StatelessWidget {
  const ProductCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.sm),
    decoration: BoxDecoration(
      color: context.colors.surfaceContainerLowest,
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: context.colors.outlineVariant),
    ),
    child: const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AspectRatio(
          aspectRatio: 1,
          child: AppShimmer(height: double.infinity, radius: AppRadius.sm),
        ),
        SizedBox(height: 8),
        AppShimmer(width: 120, height: 14),
        SizedBox(height: 6),
        AppShimmer(width: 90, height: 14),
        SizedBox(height: 8),
        AppShimmer(width: 70, height: 18),
        SizedBox(height: 10),
        AppShimmer(height: 40, radius: AppRadius.pill),
      ],
    ),
  );
}

class ProductGridSkeleton extends StatelessWidget {
  const ProductGridSkeleton({super.key, this.count = 6});
  final int count;

  @override
  Widget build(BuildContext context) => GridView.builder(
    physics: const NeverScrollableScrollPhysics(),
    padding: const EdgeInsets.all(AppSpacing.md),
    gridDelegate: productGridDelegate(context),
    itemCount: count,
    itemBuilder: (_, _) => const ProductCardSkeleton(),
  );
}

/// Titled horizontal rail of product cards (App/Product rail `46:330`):
/// 156 cards, gap 12, snapping, 2.3 visible, 4px progress.
class ProductRail extends StatelessWidget {
  const ProductRail({
    super.key,
    required this.title,
    required this.products,
    required this.scope,
    this.trailing,
    this.onViewAll,
    this.header,
    this.progressColor,
    this.progressTrackColor,
  });

  final String title;
  final List<ProductSummary> products;
  final Color? progressColor;
  final Color? progressTrackColor;
  final String scope;
  final Widget? trailing;
  final VoidCallback? onViewAll;

  /// Replaces the default [SectionHeader] (e.g. the flash header).
  final Widget? header;

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        header ??
            SectionHeader(
              title: title,
              onViewAll: onViewAll,
              trailing: trailing,
            ),
        HorizontalRail(
          height: ProductCard.extentFor(
            context,
            railCardWidth,
            withFlash: anyFlash(products),
            withSale: anySale(products),
          ),
          itemWidth: railCardWidth,
          itemCount: products.length,
          showProgress: products.length > 2,
          progressColor: progressColor,
          progressTrackColor: progressTrackColor,
          itemBuilder: (_, i) =>
              ProductTile(product: products[i], heroScope: scope, index: i),
        ),
      ],
    );
  }

  /// App/Product card `Size=Rail` width (2.3 cards visible on 390).
  static const double railCardWidth = 156;
}

/// Round avatar for category / brand rails: App/Category circle with a
/// coloured ring (by [index]) and a first-letter fallback.
class CircleLabel extends StatelessWidget {
  const CircleLabel({
    super.key,
    required this.label,
    required this.onTap,
    this.imageUrl,
    this.size = 64,
    this.contain = false,
    this.index = 0,
    this.ring = true,
  });

  final String label;
  final String? imageUrl;
  final VoidCallback onTap;
  final double size;
  final bool contain;
  final int index;
  final bool ring;

  @override
  Widget build(BuildContext context) => CategoryCircle(
    label: label,
    imageUrl: imageUrl,
    onTap: onTap,
    size: size,
    contain: contain,
    index: index,
    ring: ring,
  );
}

/// Brand tile (`8:546`): brand-50 media square with the logo (or the first
/// letter), then the name and the product count on white.
class BrandTile extends StatelessWidget {
  const BrandTile({super.key, required this.brand, required this.onTap});

  final Brand brand;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final letter = brand.name.isEmpty ? '' : brand.name.characters.first;
    final hasLogo = brand.logo != null && brand.logo!.isNotEmpty;
    return Semantics(
      button: true,
      label: brand.name,
      excludeSemantics: true,
      child: Material(
        color: c.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(color: c.outlineVariant),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: ColoredBox(
                  color: x.brandSoft,
                  child: Center(
                    child: hasLogo
                        ? Padding(
                            padding: const EdgeInsets.all(AppSpacing.md),
                            child: NetworkImageX(
                              url: brand.logo,
                              fit: BoxFit.contain,
                              cacheWidth: 200,
                            ),
                          )
                        : Text(
                            letter,
                            style: context.text.displaySmall?.copyWith(
                              color: c.primary,
                            ),
                          ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.sm,
                  vertical: AppSpacing.sm,
                ),
                child: Column(
                  children: [
                    Text(
                      brand.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: context.text.titleSmall,
                    ),
                    if (brand.productsCount != null)
                      Text(
                        context.l10n.productsCount(brand.productsCount!),
                        maxLines: 1,
                        style: context.text.bodySmall?.copyWith(
                          color: x.textMuted,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Height of a tile [width] wide at the current text scale.
  static double extentFor(BuildContext context, double width) =>
      width * 0.66 + 16 + 40 * MediaQuery.textScalerOf(context).scale(1);
}

/// App/Search bar (`42:85`): 44 pill on slate-100 with the muted hint and
/// a 36 brand circle with the search icon at the inline-end. Tapping it
/// opens the search screen.
class SearchPill extends StatelessWidget {
  const SearchPill({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gutter,
        vertical: AppSpacing.smd,
      ),
      child: Semantics(
        button: true,
        label: l10n.searchHint,
        excludeSemantics: true,
        child: Material(
          color: c.surfaceContainerHighest,
          shape: const StadiumBorder(),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => context.push(AppRoutes.search),
            child: SizedBox(
              height: 44,
              child: Padding(
                padding: const EdgeInsetsDirectional.only(start: 16, end: 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        l10n.searchHint,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: context.text.bodyMedium?.copyWith(
                          color: context.extra.textMuted,
                        ),
                      ),
                    ),
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: c.primary,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.search_rounded,
                        size: 20,
                        color: c.onPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
