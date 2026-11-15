import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import 'catalog_widgets.dart';

/// All brands (grid of logos) -> brand listing.
class BrandsPage extends ConsumerWidget {
  const BrandsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final brands = ref.watch(brandsProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.brands),
        actions: [
          IconButton(
            tooltip: l10n.searchHint,
            icon: const Icon(Icons.search_rounded),
            onPressed: () => context.push(AppRoutes.search),
          ),
        ],
      ),
      body: brands.when(
        loading: () => GridView.count(
          crossAxisCount: 2,
          padding: const EdgeInsets.all(AppSpacing.gutter),
          mainAxisSpacing: AppSpacing.grid,
          crossAxisSpacing: AppSpacing.grid,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            for (var i = 0; i < 6; i++)
              const AppShimmer(height: double.infinity, radius: AppRadius.md),
          ],
        ),
        error: (e, _) =>
            ErrorState(error: e, onRetry: () => ref.invalidate(brandsProvider)),
        data: (list) => list.isEmpty
            ? EmptyState(title: l10n.brandsEmpty, icon: Icons.sell_outlined)
            : RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(brandsProvider);
                  await ref
                      .read(brandsProvider.future)
                      .catchError((_) => const <Brand>[]);
                },
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(
                      child: ColorHeaderBlock(
                        title: l10n.brands,
                        subtitle: l10n.brandsCount(list.length),
                        tone: HeaderTone.sun,
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
                                  mainAxisExtent: BrandTile.extentFor(
                                    context,
                                    cell,
                                  ),
                                ),
                            itemCount: list.length,
                            itemBuilder: (_, i) => BrandTile(
                              brand: list[i],
                              onTap: () =>
                                  context.push(AppRoutes.brand(list[i].slug)),
                            ),
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
