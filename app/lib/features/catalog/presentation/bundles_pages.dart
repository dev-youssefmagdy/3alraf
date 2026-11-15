import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../cart/application/cart_controller.dart';
import '../application/catalog_providers.dart';
import '../data/product_models.dart';
import 'bundle_widgets.dart';
import 'catalog_widgets.dart';

class BundlesPage extends ConsumerWidget {
  const BundlesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final bundles = ref.watch(bundlesProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.bundles),
        actions: [
          IconButton(
            tooltip: l10n.searchHint,
            icon: const Icon(Icons.search_rounded),
            onPressed: () => context.push(AppRoutes.search),
          ),
        ],
      ),
      body: bundles.when(
        loading: () => const ProductGridSkeleton(),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(bundlesProvider),
        ),
        data: (list) => list.isEmpty
            ? EmptyState(
                title: l10n.bundlesEmpty,
                icon: Icons.inventory_2_outlined,
              )
            : RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(bundlesProvider);
                  await ref
                      .read(bundlesProvider.future)
                      .catchError((_) => const <Bundle>[]);
                },
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(
                      child: ColorHeaderBlock(
                        title: l10n.bundles,
                        subtitle: l10n.bundlesSubtitle,
                        tone: HeaderTone.gold,
                      ),
                    ),
                    SliverPadding(
                      padding: const EdgeInsets.all(AppSpacing.gutter),
                      sliver: SliverLayoutBuilder(
                        builder: (context, constraints) {
                          final w = constraints.crossAxisExtent;
                          final cols = w >= 560 ? 3 : 2;
                          final cell =
                              (w - AppSpacing.grid * (cols - 1)) / cols;
                          return SliverGrid.builder(
                            gridDelegate:
                                SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: cols,
                                  mainAxisSpacing: AppSpacing.grid,
                                  crossAxisSpacing: AppSpacing.grid,
                                  mainAxisExtent: bundleCardExtent(
                                    context,
                                    cell,
                                  ),
                                ),
                            itemCount: list.length,
                            itemBuilder: (_, i) => BundleCard(bundle: list[i]),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}

class BundlePage extends ConsumerStatefulWidget {
  const BundlePage({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<BundlePage> createState() => _BundlePageState();
}

class _BundlePageState extends ConsumerState<BundlePage> {
  bool _adding = false;

  Future<void> _add(Bundle b) async {
    final l10n = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    setState(() => _adding = true);
    try {
      final message = await ref
          .read(cartControllerProvider.notifier)
          .add(bundleId: b.id);
      HapticFeedback.mediumImpact();
      if (!mounted) return;
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          appSnackBar(
            context,
            message ?? l10n.addedToCart,
            tone: SnackTone.success,
            actionLabel: l10n.viewCart,
            onAction: () => router.go(AppRoutes.cart),
          ),
        );
    } on ApiException catch (e) {
      if (!mounted) return;
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          appSnackBar(context, errorMessage(l10n, e), tone: SnackTone.error),
        );
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final currency = ref.watch(currencyLabelProvider);
    final bundle = ref.watch(bundleProvider(widget.slug));
    return Scaffold(
      appBar: AppBar(title: Text(l10n.bundle)),
      body: bundle.when(
        loading: () => ListView(
          physics: const NeverScrollableScrollPhysics(),
          children: const [
            AppShimmer(height: 300, radius: 0),
            Padding(
              padding: EdgeInsets.all(AppSpacing.gutter),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AppShimmer(height: 28),
                  SizedBox(height: 12),
                  AppShimmer(width: 140, height: 28),
                  SizedBox(height: 20),
                  AppShimmer(height: 160, radius: AppRadius.md),
                ],
              ),
            ),
          ],
        ),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(bundleProvider(widget.slug)),
        ),
        data: (b) => _BundleBody(bundle: b, currency: currency),
      ),
      bottomNavigationBar: bundle.asData == null
          ? null
          : _bundleBar(context, bundle.asData!.value, currency),
    );
  }

  Widget _bundleBar(BuildContext context, Bundle b, String currency) {
    final l10n = context.l10n;
    final canBuy = b.available && b.inStock;
    return StickyActionBar(
      leading: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.money(b.price, currency),
            style: context.textX.priceM.copyWith(color: context.colors.primary),
          ),
          if (b.hasSavings)
            Text(
              l10n.money(b.regularTotal, currency),
              style: context.textX.priceOld.copyWith(
                decoration: TextDecoration.lineThrough,
              ),
            ),
        ],
      ),
      action: canBuy
          ? AppButton(
              label: l10n.addToCart,
              icon: Icons.shopping_cart_outlined,
              size: AppButtonSize.l,
              dense: true,
              loading: _adding,
              onPressed: () => _add(b),
            )
          : AppButton(
              label: l10n.bundleUnavailable,
              size: AppButtonSize.l,
              onPressed: null,
            ),
    );
  }
}

/// Bundle page body (`55:2457`): gold collage media, H1, price row with the
/// sun save pill, description, "includes" card, trust row.
class _BundleBody extends StatelessWidget {
  const _BundleBody({required this.bundle, required this.currency});

  final Bundle bundle;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final b = bundle;
    final x = context.extra;
    final soldOut = !(b.available && b.inStock);
    final photos = [
      for (final comp in b.components)
        if (comp.image != null && comp.image!.isNotEmpty) comp.image!,
    ];
    if (photos.isEmpty && b.image != null) photos.add(b.image!);
    return ListView(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      children: [
        Container(
          height: 300,
          color: x.goldSoft,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Opacity(
                opacity: soldOut ? 0.5 : 1,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(28, 24, 28, 16),
                  child: BundleCollage(photos: photos.take(3).toList()),
                ),
              ),
              PositionedDirectional(
                top: AppSpacing.smd,
                end: AppSpacing.gutter,
                child: StatusChip(
                  label: l10n.bundleBadge,
                  tone: StatusTone.gold,
                  icon: Icons.inventory_2_outlined,
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(b.name, style: context.text.headlineMedium),
              const SizedBox(height: AppSpacing.sm),
              Wrap(
                spacing: AppSpacing.sm,
                runSpacing: AppSpacing.xs,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  PriceText(
                    amount: b.price,
                    currency: currency,
                    oldAmount: b.hasSavings ? b.regularTotal : null,
                    style: context.textX.priceL.copyWith(
                      color: context.colors.primary,
                    ),
                  ),
                  if (b.hasSavings)
                    StatusChip(
                      label: l10n.bundleSave(
                        l10n.money(b.savings, currency),
                      ),
                      tone: StatusTone.sun,
                    ),
                  if (soldOut)
                    StatusChip(
                      label: l10n.bundleUnavailable,
                      tone: StatusTone.ink,
                    ),
                ],
              ),
              if (b.description != null && b.description!.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  b.description!,
                  style: context.text.bodyLarge?.copyWith(
                    color: context.colors.onSurfaceVariant,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.mdl),
              Semantics(
                header: true,
                child: Text(
                  l10n.bundleContains,
                  style: context.text.headlineSmall,
                ),
              ),
              const SizedBox(height: AppSpacing.smd),
              SectionCard(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
                child: Column(
                  children: [
                    for (final c in b.components)
                      InkWell(
                        onTap: () => openProduct(context, c.slug),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.smd,
                            vertical: AppSpacing.sm,
                          ),
                          child: Row(
                            children: [
                              NetworkImageX(
                                url: c.image,
                                width: 48,
                                height: 48,
                                radius: AppRadius.sm,
                                cacheWidth: 96,
                              ),
                              const SizedBox(width: AppSpacing.smd),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      c.name,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: context.text.titleSmall,
                                    ),
                                    Text(
                                      l10n.money(
                                        c.unitPrice,
                                        currency,
                                      ),
                                      style: context.text.bodySmall?.copyWith(
                                        color: x.textMuted,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Semantics(
                                label: l10n.quantityLabel(c.quantity),
                                excludeSemantics: true,
                                child: Text(
                                  '× ${c.quantity}',
                                  style: context.text.labelMedium,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              const TrustStrip.row(),
            ],
          ),
        ),
      ],
    );
  }
}
