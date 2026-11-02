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
import '../../tenant/application/tenant_controller.dart';
import '../../wishlist/application/wishlist_controller.dart';
import '../data/catalog_models.dart';

/// Currency label of the active store (server provides the symbol).
final currencyLabelProvider = Provider<String>((ref) {
  final store = ref.watch(storeInfoProvider);
  return store?.currencySymbol ??
      ref.watch(tenantControllerProvider).tenant?.currency ??
      '';
});

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

/// Product card wired to navigation and the wishlist heart.
class ProductTile extends ConsumerWidget {
  const ProductTile({
    super.key,
    required this.product,
    required this.heroScope,
  });

  final ProductSummary product;
  final String heroScope;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final favorite = ref.watch(
      wishlistIdsProvider.select((ids) => ids.contains(product.id)),
    );
    final tag = ProductHeroTag(heroScope, product.slug);
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
      onTap: () => openProduct(context, product.slug, tag),
      onFavorite: () async {
        if (!ref.read(authControllerProvider).signedIn) {
          promptSignIn(context);
          return;
        }
        final messenger = ScaffoldMessenger.of(context);
        final now = await ref
            .read(wishlistProvider.notifier)
            .toggle(product.id, product: product);
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
      },
    );
  }
}

SliverGridDelegate productGridDelegate(BuildContext context) {
  final width = MediaQuery.sizeOf(context).width;
  final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
  final cell =
      (width - AppSpacing.md * 2 - AppSpacing.sm * (columns - 1)) / columns;
  final scale = MediaQuery.textScalerOf(context).scale(1);
  return SliverGridDelegateWithFixedCrossAxisCount(
    crossAxisCount: columns,
    mainAxisSpacing: AppSpacing.sm,
    crossAxisSpacing: AppSpacing.sm,
    mainAxisExtent: cell + 150 * scale,
  );
}

/// Shimmer placeholder card used by grids and rails.
class ProductCardSkeleton extends StatelessWidget {
  const ProductCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) => const Card(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AspectRatio(
          aspectRatio: 1,
          child: AppShimmer(height: double.infinity, radius: 0),
        ),
        Padding(
          padding: EdgeInsets.all(AppSpacing.sm + 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AppShimmer(width: 120, height: 14),
              SizedBox(height: 8),
              AppShimmer(width: 70, height: 14),
            ],
          ),
        ),
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

/// Titled horizontal rail of product cards.
class ProductRail extends StatelessWidget {
  const ProductRail({
    super.key,
    required this.title,
    required this.products,
    required this.scope,
    this.trailing,
    this.onViewAll,
  });

  final String title;
  final List<ProductSummary> products;
  final String scope;
  final Widget? trailing;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (trailing == null)
          SectionHeader(title: title, onViewAll: onViewAll)
        else
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.sm,
            ),
            child: Row(
              children: [
                Expanded(
                  child: Semantics(
                    header: true,
                    child: Text(
                      title,
                      style: context.text.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
                trailing!,
              ],
            ),
          ),
        HorizontalRail(
          height: 150 + 150 * scale,
          itemWidth: 150,
          itemCount: products.length,
          itemBuilder: (_, i) =>
              ProductTile(product: products[i], heroScope: scope),
        ),
      ],
    );
  }
}

/// Round avatar for category / brand rails with a first-letter fallback.
class CircleLabel extends StatelessWidget {
  const CircleLabel({
    super.key,
    required this.label,
    required this.onTap,
    this.imageUrl,
    this.size = 64,
    this.contain = false,
  });

  final String label;
  final String? imageUrl;
  final VoidCallback onTap;
  final double size;
  final bool contain;

  @override
  Widget build(BuildContext context) {
    final letter = label.isEmpty ? '' : label.characters.first.toUpperCase();
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: SizedBox(
        width: size + 16,
        child: Column(
          children: [
            Container(
              width: size,
              height: size,
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: context.extra.tint,
                border: Border.all(color: context.colors.outlineVariant),
              ),
              child: imageUrl == null
                  ? Center(
                      child: Text(
                        letter,
                        style: context.text.titleLarge?.copyWith(
                          color: context.colors.primary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    )
                  : NetworkImageX(
                      url: imageUrl,
                      fit: contain ? BoxFit.contain : BoxFit.cover,
                      cacheWidth: size.round(),
                    ),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              maxLines: 2,
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              style: context.text.labelMedium,
            ),
          ],
        ),
      ),
    );
  }
}

/// Search pill used on Home / Categories; taps open the search screen.
class SearchPill extends StatelessWidget {
  const SearchPill({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      child: Semantics(
        button: true,
        label: l10n.searchHint,
        child: InkWell(
          onTap: () => context.push(AppRoutes.search),
          borderRadius: BorderRadius.circular(AppRadius.lg),
          child: Container(
            height: 48,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            decoration: BoxDecoration(
              color: context.colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              border: Border.all(color: context.colors.outlineVariant),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  color: context.colors.onSurfaceVariant,
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Text(
                    l10n.searchHint,
                    style: context.text.bodyMedium?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
