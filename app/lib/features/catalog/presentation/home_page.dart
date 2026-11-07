import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../shell/presentation/shell_scaffold.dart';
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
    final l10n = context.l10n;
    final home = ref.watch(homeProvider);
    final storeName = ref.watch(storeControllerProvider).name;
    ref.listen(homeProvider, (_, next) {
      final data = next.asData?.value;
      if (data != null) _maybePopup(data);
    });
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(storeName.isEmpty ? l10n.appName : storeName),
        actions: [
          IconButton(
            tooltip: l10n.searchHint,
            icon: const Icon(Icons.search_rounded),
            onPressed: () => context.push(AppRoutes.search),
          ),
        ],
      ),
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
    final promos = [...data.strips, ...data.tiles];
    final empty =
        data.hero.isEmpty &&
        data.categories.isEmpty &&
        data.recommended.isEmpty &&
        data.flashSale == null &&
        data.mostSold.isEmpty &&
        data.mostReviewed.isEmpty;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      children: [
        const SearchPill(),
        if (data.hero.isNotEmpty) _HeroCarousel(banners: data.hero),
        if (data.categories.isNotEmpty) ...[
          SectionHeader(
            title: l10n.navCategories,
            onViewAll: () => context.go(AppRoutes.categories),
          ),
          HorizontalRail(
            height: 112,
            itemCount: data.categories.length,
            itemBuilder: (_, i) {
              final c = data.categories[i];
              return CircleLabel(
                label: c.name,
                imageUrl: c.image ?? c.icon,
                onTap: () => context.push(AppRoutes.category(c.slug)),
              );
            },
          ),
        ],
        if (promos.isNotEmpty) _PromoGrid(banners: promos),
        if (data.flashSale != null)
          ProductRail(
            title: data.flashSale!.name.isEmpty
                ? l10n.flashSale
                : data.flashSale!.name,
            products: data.flashSale!.products,
            scope: 'home-flash',
            trailing: CountdownChip(
              endsAt: data.flashSale!.endsAt,
              onFinished: () => ref.invalidate(homeProvider),
            ),
          ),
        if (giftOn) const _GiftCta(),
        if (bundles.isNotEmpty) ...[
          SectionHeader(
            title: l10n.bundles,
            onViewAll: () => context.push(AppRoutes.bundles),
          ),
          HorizontalRail(
            height: 290,
            itemWidth: 190,
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
        if (data.brands.isNotEmpty) ...[
          SectionHeader(
            title: l10n.brands,
            onViewAll: () => context.push(AppRoutes.brands),
          ),
          HorizontalRail(
            height: 112,
            itemCount: data.brands.length,
            itemBuilder: (_, i) {
              final b = data.brands[i];
              return CircleLabel(
                label: b.name,
                imageUrl: b.logo,
                contain: true,
                onTap: () => context.push(AppRoutes.brand(b.slug)),
              );
            },
          ),
        ],
        if (empty)
          SizedBox(
            height: 320,
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

class _HeroCarousel extends StatefulWidget {
  const _HeroCarousel({required this.banners});

  final List<PromoBanner> banners;

  @override
  State<_HeroCarousel> createState() => _HeroCarouselState();
}

class _HeroCarouselState extends State<_HeroCarousel> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final many = widget.banners.length > 1;
    return Column(
      children: [
        CarouselSlider.builder(
          itemCount: widget.banners.length,
          options: CarouselOptions(
            aspectRatio: 16 / 8,
            viewportFraction: 0.92,
            enlargeCenterPage: true,
            enlargeFactor: 0.12,
            autoPlay: many,
            autoPlayInterval: const Duration(seconds: 5),
            enableInfiniteScroll: many,
            onPageChanged: (i, _) => setState(() => _index = i),
          ),
          itemBuilder: (context, i, _) {
            final b = widget.banners[i];
            return Semantics(
              button: true,
              child: GestureDetector(
                onTap: () => openLink(context, b.link),
                child: NetworkImageX(
                  url: b.image,
                  width: double.infinity,
                  radius: AppRadius.md,
                  semanticLabel: b.title,
                  cacheWidth: 720,
                ),
              ),
            );
          },
        ),
        if (many)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.sm),
            child: AnimatedSmoothIndicator(
              activeIndex: _index,
              count: widget.banners.length,
              effect: ExpandingDotsEffect(
                dotHeight: 6,
                dotWidth: 6,
                activeDotColor: context.colors.primary,
                dotColor: context.colors.outlineVariant,
              ),
            ),
          ),
      ],
    );
  }
}

/// Promo strips and tiles: 2-column tiles.
class _PromoGrid extends StatelessWidget {
  const _PromoGrid({required this.banners});

  final List<PromoBanner> banners;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: banners.length == 1 ? 1 : 2,
        mainAxisSpacing: AppSpacing.sm,
        crossAxisSpacing: AppSpacing.sm,
        childAspectRatio: 16 / 7,
        children: [
          for (final b in banners)
            GestureDetector(
              onTap: () => openLink(context, b.link),
              child: NetworkImageX(
                url: b.image,
                radius: AppRadius.sm,
                semanticLabel: b.title,
                cacheWidth: 400,
              ),
            ),
        ],
      ),
    );
  }
}

class _GiftCta extends StatelessWidget {
  const _GiftCta();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Material(
        color: context.extra.tint,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.md),
          onTap: () => context.push(AppRoutes.giftFinder),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(
              children: [
                Icon(
                  Icons.card_giftcard_rounded,
                  size: 40,
                  color: context.colors.primary,
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l10n.giftCtaTitle,
                        style: context.text.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(l10n.giftCtaBody, style: context.text.bodySmall),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        const AppShimmer(height: 48, radius: AppRadius.lg),
        const SizedBox(height: AppSpacing.md),
        const AppShimmer(height: 150, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.md),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            for (var i = 0; i < 5; i++)
              const AppShimmer(height: 60, circle: true),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        const AppShimmer(width: 140, height: 20),
        const SizedBox(height: AppSpacing.sm),
        const Row(
          children: [
            Expanded(child: ProductCardSkeleton()),
            SizedBox(width: AppSpacing.sm),
            Expanded(child: ProductCardSkeleton()),
          ],
        ),
      ],
    );
  }
}
