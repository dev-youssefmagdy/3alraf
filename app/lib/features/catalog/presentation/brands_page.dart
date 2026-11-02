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
      appBar: AppBar(title: Text(l10n.brands)),
      body: brands.when(
        loading: () => GridView.count(
          crossAxisCount: 3,
          padding: const EdgeInsets.all(AppSpacing.md),
          physics: const NeverScrollableScrollPhysics(),
          children: [
            for (var i = 0; i < 9; i++)
              const Padding(
                padding: EdgeInsets.all(AppSpacing.sm),
                child: AppShimmer(height: 80, radius: AppRadius.md),
              ),
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
                child: GridView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 130,
                    mainAxisExtent: 124,
                    mainAxisSpacing: AppSpacing.sm,
                    crossAxisSpacing: AppSpacing.sm,
                  ),
                  itemCount: list.length,
                  itemBuilder: (_, i) => CircleLabel(
                    label: list[i].name,
                    imageUrl: list[i].logo,
                    contain: true,
                    onTap: () => context.push(AppRoutes.brand(list[i].slug)),
                  ),
                ),
              ),
      ),
    );
  }
}
