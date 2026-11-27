import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../shell/presentation/home_app_bar.dart';
import '../../store/application/store_controller.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import '../data/product_models.dart';
import 'bundle_widgets.dart';
import 'catalog_widgets.dart';

/// Home tab. Mirrors `storefront/home.blade.php`: hero, categories, promo
/// strips/tiles, flash sale, gift finder, bundles, product rails, brands,
/// popup banner.
class HomePage extends ConsumerStatefulWidget {
  const HomePage({super.key});

  @override
  ConsumerState<HomePage> createState() => _HomePageState();
}

class _HomePageState extends ConsumerState<HomePage> {
  Future<void> _refresh() async {
    ref.invalidate(bundlesProvider);
    ref.invalidate(homeProvider);
    await ref.read(homeProvider.future).catchError((_) => HomeData());
  }

  void _maybePopup(HomeData data) {
    final popup = data.popup;
    if (popup == null || popup.image == null) return;
    if (ref.read(popupShownProvider)) return;
    ref.read(popupShownProvider.notifier).markShown();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      showDialog<void>(
        context: context,
        builder: (ctx) => Dialog(
          clipBehavior: Clip.antiAlias,
          child: Stack(
            children: [
              GestureDetector(
                onTap: () {
                  Navigator.of(ctx).pop();
                  openLink(context, popup.link);
                },
                child: NetworkImageX(
                  url: popup.image,
                  width: double.infinity,
                  fit: BoxFit.fitWidth,
                  semanticLabel: popup.title,
                ),
              ),
              PositionedDirectional(
                top: 4,
                end: 4,
                child: IconButton.filledTonal(
                  tooltip: MaterialLocalizations.of(ctx).closeButtonTooltip,
                  onPressed: () => Navigator.of(ctx).pop(),
                  icon: const Icon(Icons.close_rounded),
                ),
              ),
            ],
          ),
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final home = ref.watch(homeProvider);
    ref.listen(homeProvider, (_, next) {
      final data = next.asData?.value;
      if (data != null) _maybePopup(data);
    });
    return Scaffold(
      appBar: const HomeAppBar(),
      body: home.when(
        loading: () => const _HomeSkeleton(),
        error: (e, _) =>
            ErrorState(error: e, onRetry: () => ref.invalidate(homeProvider)),
        data: (data) => RefreshIndicator(
          onRefresh: _refresh,
          child: _HomeContent(data: data),
        ),
      ),
    );
  }
}

class _HomeContent extends ConsumerWidget {
  const _HomeContent({required this.data});

  final HomeData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final giftOn =
        ref.watch(storeProfileProvider)?.features['gift_finder'] ?? false;
    final bundles =
        ref.watch(bundlesProvider).asData?.value ?? const <Bundle>[];
    final empty =
        data.hero.isEmpty &&
        data.categories.isEmpty &&
        data.recommended.isEmpty &&
        data.flashSale == null &&
        data.mostSold.isEmpty &&
        data.mostReviewed.isEmpty;
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(
        bottom: AppSpacing.section + kRaisedNavClearance,
      ),
      children: [
        const SearchPill(),
        if (data.hero.isNotEmpty) _HeroCarousel(banners: data.hero),
        if (data.categories.isNotEmpty) ...[
          SectionHeader(
            title: l10n.navCategories,
            onViewAll: () => context.go(AppRoutes.categories),
          ),
          CategoryCircleRow(
            itemCount: data.categories.length,
            itemBuilder: (_, i) {
              final c = data.categories[i];
              return CircleLabel(
                label: c.name,
                imageUrl: c.image ?? c.icon,
                index: i,
                onTap: () => context.push(AppRoutes.category(c.slug)),
              );
            },
          ),
        ],
        if (data.strips.isNotEmpty) _PromoStrips(banners: data.strips),
        if (data.tiles.isNotEmpty) _PromoTiles(banners: data.tiles),
        if (data.flashSale != null && data.flashSale!.products.isNotEmpty)
          _FlashSaleBlock(
            sale: data.flashSale!,
            onFinished: () => ref.invalidate(homeProvider),
          ),
        if (giftOn) const _GiftCta(),
        if (bundles.isNotEmpty) ...[
          SectionHeader(
            title: l10n.bundles,
            onViewAll: () => context.push(AppRoutes.bundles),
          ),
          HorizontalRail(
            height: bundleCardExtent(context),
            itemWidth: bundleCardWidth,
            itemCount: bundles.length,
            itemBuilder: (_, i) => BundleCard(bundle: bundles[i]),
          ),
        ],
        ProductRail(
          title: l10n.railRecommended,
          products: data.recommended,
          scope: 'home-recommended',
        ),
        ProductRail(
          title: l10n.railMostSold,
          products: data.mostSold,
          scope: 'home-sold',
        ),
        ProductRail(
          title: l10n.railMostReviewed,
          products: data.mostReviewed,
          scope: 'home-reviewed',
        ),
        for (final r in data.categoryRails)
          ProductRail(
            title: r.name,
            products: r.products,
            scope: 'home-cat-${r.id}',
            onViewAll: () => context.push(AppRoutes.category(r.slug)),
          ),
        if (data.brands.isNotEmpty) ...[
          SectionHeader(
            title: l10n.brands,
            onViewAll: () => context.push(AppRoutes.brands),
          ),
          HorizontalRail(
            height: BrandTile.extentFor(context, 144),
            itemWidth: 144,
            itemCount: data.brands.length,
            itemBuilder: (_, i) {
              final b = data.brands[i];
              return BrandTile(
                brand: b,
                onTap: () => context.push(AppRoutes.brand(b.slug)),
              );
            },
          ),
        ],
        if (!empty) ...[
          const SizedBox(height: AppSpacing.section),
          const TrustStrip.band(),
        ],
        if (empty)
          SizedBox(
            height: 320 * scale.clamp(1, 1.4),
            child: EmptyState(
              title: l10n.homeEmptyTitle,
              message: l10n.homeEmptyBody,
              icon: Icons.storefront_outlined,
            ),
          ),
      ],
    );
  }
}

/// Flash sale (§10.6 step 6): a full-bleed pop-600 block with the Flash
/// header (white sparkle + title, sun countdown units, white link) and a
/// rail of Sale cards.
class _FlashSaleBlock extends StatelessWidget {
  const _FlashSaleBlock({required this.sale, required this.onFinished});

  final FlashSale sale;
  final VoidCallback onFinished;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    final title = sale.name.isEmpty ? l10n.flashSale : sale.name;
    return Container(
      key: const ValueKey('home-flash'),
      margin: const EdgeInsets.only(top: AppSpacing.section),
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      color: x.pop,
      child: ProductRail(
        title: title,
        products: sale.products,
        scope: 'home-flash',
        progressColor: x.sun,
        progressTrackColor: Colors.white.withValues(alpha: 0.35),
        header: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.gutter,
            AppSpacing.smd,
            AppSpacing.gutter,
            AppSpacing.smd,
          ),
          child: Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            crossAxisAlignment: WrapCrossAlignment.center,
            alignment: WrapAlignment.spaceBetween,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.auto_awesome_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Semantics(
                      header: true,
                      child: Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.text.headlineSmall?.copyWith(
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              CountdownChip(
                endsAt: sale.endsAt,
                units: true,
                onFinished: onFinished,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeroCarousel extends StatelessWidget {
  const _HeroCarousel({required this.banners});

  final List<PromoBanner> banners;

  @override
  Widget build(BuildContext context) {
    return HeroCarousel(
      itemCount: banners.length,
      itemBuilder: (context, i) {
        final b = banners[i];
        return Semantics(
          button: true,
          child: GestureDetector(
            onTap: () => openLink(context, b.link),
            // Like the website, the banner is never cropped: the whole image
            // is shown (contain) over a blurred cover copy of itself, so a
            // banner whose proportions differ from the slot still fills it
            // without flat bars. Same URL + cacheWidth = one cached decode.
            child: Stack(
              fit: StackFit.expand,
              children: [
                ExcludeSemantics(
                  child: ImageFiltered(
                    imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
                    child: NetworkImageX(
                      url: b.image,
                      width: double.infinity,
                      height: double.infinity,
                      cacheWidth: 720,
                    ),
                  ),
                ),
                NetworkImageX(
                  url: b.image,
                  width: double.infinity,
                  height: double.infinity,
                  fit: BoxFit.contain,
                  semanticLabel: b.title,
                  cacheWidth: 720,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Promo strips (`App/Promo strip`, HOME_STRIP banners): full-width 16:3
/// banners (radius 16) in the gutter; more than one becomes a snapping
/// rail where the next strip peeks, like the website's strip row.
class _PromoStrips extends StatelessWidget {
  const _PromoStrips({required this.banners});

  final List<PromoBanner> banners;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    Widget strip(PromoBanner b) => Semantics(
      button: true,
      label: b.title,
      child: Material(
        color: context.extra.brandSoft,
        borderRadius: BorderRadius.circular(AppRadius.md),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => openLink(context, b.link),
          child: NetworkImageX(
            url: b.image,
            width: double.infinity,
            height: double.infinity,
            semanticLabel: b.title,
            cacheWidth: 800,
          ),
        ),
      ),
    );
    if (banners.length == 1) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.gutter,
          AppSpacing.section,
          AppSpacing.gutter,
          0,
        ),
        child: AspectRatio(aspectRatio: 16 / 3, child: strip(banners.first)),
      );
    }
    final itemWidth = (width - AppSpacing.gutter * 2) * 0.92;
    return Padding(
      key: const ValueKey('home-strips'),
      padding: const EdgeInsets.only(top: AppSpacing.section),
      child: HorizontalRail(
        height: itemWidth * 3 / 16,
        itemWidth: itemWidth,
        itemCount: banners.length,
        itemBuilder: (_, i) => strip(banners[i]),
      ),
    );
  }
}

/// Promo tiles (§10.5): a row of two 120-high tiles (radius 24) when there
/// are two or fewer, otherwise a snapping rail of 300-wide tiles.
class _PromoTiles extends StatelessWidget {
  const _PromoTiles({required this.banners});

  final List<PromoBanner> banners;

  @override
  Widget build(BuildContext context) {
    Widget tile(PromoBanner b) => Semantics(
      button: true,
      child: Material(
        color: context.extra.brandSoft,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => openLink(context, b.link),
          child: NetworkImageX(
            url: b.image,
            width: double.infinity,
            height: double.infinity,
            semanticLabel: b.title,
            cacheWidth: 600,
          ),
        ),
      ),
    );
    if (banners.length > 2) {
      return Padding(
        padding: const EdgeInsets.only(top: AppSpacing.section),
        child: HorizontalRail(
          height: 120,
          itemWidth: 300,
          itemCount: banners.length,
          itemBuilder: (_, i) => tile(banners[i]),
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.section,
        AppSpacing.gutter,
        0,
      ),
      child: SizedBox(
        height: 120,
        child: Row(
          children: [
            for (var i = 0; i < banners.length; i++) ...[
              if (i > 0) const SizedBox(width: AppSpacing.grid),
              Expanded(child: tile(banners[i])),
            ],
          ],
        ),
      ),
    );
  }
}

/// Gift finder CTA (§10.6 step 7): violet block with a sun gift circle,
/// the title and line in white and a white arrow circle.
class _GiftCta extends StatelessWidget {
  const _GiftCta();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.section,
        AppSpacing.gutter,
        0,
      ),
      child: Semantics(
        button: true,
        label: '${l10n.giftCtaTitle}. ${l10n.giftCtaBody}',
        excludeSemantics: true,
        child: Material(
          key: const ValueKey('home-gift-cta'),
          color: x.violet,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => context.push(AppRoutes.giftFinder),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.mdl),
              child: Row(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: x.sun,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.card_giftcard_rounded,
                      size: 28,
                      color: AppColors.slate950,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l10n.giftCtaTitle,
                          style: context.text.headlineSmall?.copyWith(
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          l10n.giftCtaBody,
                          style: context.text.bodySmall?.copyWith(
                            color: Colors.white.withValues(alpha: 0.9),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Container(
                    width: 40,
                    height: 40,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.chevron_right_rounded, color: x.violet),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// App/Skeleton `Hero`: search, hero slide, category circles, then a rail.
class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gutter,
        vertical: AppSpacing.smd,
      ),
      children: [
        const AppShimmer(height: 44, radius: AppRadius.pill),
        const SizedBox(height: AppSpacing.md),
        const AspectRatio(
          aspectRatio: 358 / 184,
          child: AppShimmer(height: double.infinity, radius: AppRadius.lg),
        ),
        const SizedBox(height: AppSpacing.section),
        const AppShimmer(width: 120, height: 22),
        const SizedBox(height: AppSpacing.smd),
        Row(
          children: [
            for (var i = 0; i < 5; i++) ...[
              if (i > 0) const SizedBox(width: AppSpacing.sm),
              const Expanded(child: AppShimmer(height: 64, circle: true)),
            ],
          ],
        ),
        const SizedBox(height: AppSpacing.section),
        const AppShimmer(width: 140, height: 22),
        const SizedBox(height: AppSpacing.smd),
        const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: ProductCardSkeleton()),
            SizedBox(width: AppSpacing.grid),
            Expanded(child: ProductCardSkeleton()),
          ],
        ),
      ],
    );
  }
}
