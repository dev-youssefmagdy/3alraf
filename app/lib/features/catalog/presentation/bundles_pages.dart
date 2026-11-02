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
      appBar: AppBar(title: Text(l10n.bundles)),
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
                child: GridView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: MediaQuery.sizeOf(context).width >= 600
                        ? 3
                        : 2,
                    mainAxisSpacing: AppSpacing.sm,
                    crossAxisSpacing: AppSpacing.sm,
                    mainAxisExtent:
                        250 + 130 * MediaQuery.textScalerOf(context).scale(1),
                  ),
                  itemCount: list.length,
                  itemBuilder: (_, i) => BundleCard(bundle: list[i]),
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
    final currency = ref.watch(currencyLabelProvider);
    final bundle = ref.watch(bundleProvider(widget.slug));
    return Scaffold(
      appBar: AppBar(title: Text(bundle.asData?.value.name ?? l10n.bundle)),
      body: bundle.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(bundleProvider(widget.slug)),
        ),
        data: (b) => ListView(
          padding: const EdgeInsets.only(bottom: AppSpacing.xl),
          children: [
            AspectRatio(
              aspectRatio: 1.4,
              child: NetworkImageX(url: b.image, cacheWidth: 800),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    b.name,
                    style: context.text.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  PriceText(
                    amount: b.price,
                    currency: currency,
                    oldAmount: b.hasSavings ? b.regularTotal : null,
                    style: context.text.headlineSmall,
                  ),
                  if (b.hasSavings)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xs),
                      child: StatusChip(
                        label: l10n.bundleSave(
                          l10n.currencyAmount(b.savings, currency),
                        ),
                        tone: StatusTone.success,
                      ),
                    ),
                  if (b.description != null && b.description!.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(b.description!, style: context.text.bodyMedium),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    l10n.bundleContains,
                    style: context.text.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            for (final c in b.components)
              ListTile(
                leading: SizedBox(
                  width: 56,
                  height: 56,
                  child: NetworkImageX(
                    url: c.image,
                    radius: AppRadius.sm,
                    cacheWidth: 56,
                  ),
                ),
                title: Text(c.name),
                subtitle: Text(
                  '${l10n.quantityLabel(c.quantity)} · '
                  '${l10n.currencyAmount(c.unitPrice, currency)}',
                ),
                onTap: () => openProduct(context, c.slug),
              ),
          ],
        ),
      ),
      bottomNavigationBar: bundle.asData == null
          ? null
          : _bundleBar(context, bundle.asData!.value),
    );
  }

  Widget _bundleBar(BuildContext context, Bundle b) {
    final l10n = context.l10n;
    final canBuy = b.available && b.inStock;
    return Material(
      elevation: 8,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: AppButton(
            label: canBuy ? l10n.addToCart : l10n.bundleUnavailable,
            icon: Icons.add_shopping_cart_rounded,
            loading: _adding,
            onPressed: canBuy ? () => _add(b) : null,
          ),
        ),
      ),
    );
  }
}
