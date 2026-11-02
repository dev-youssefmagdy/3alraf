import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../shell/presentation/shell_scaffold.dart';
import '../application/cart_controller.dart';
import '../data/cart_models.dart';
import 'totals_card.dart';

/// Cart tab: lines with steppers, discount code, server totals, checkout.
class CartPage extends ConsumerStatefulWidget {
  const CartPage({super.key});

  @override
  ConsumerState<CartPage> createState() => _CartPageState();
}

class _CartPageState extends ConsumerState<CartPage> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _toast(String text) => context.showSnack(text);

  Future<void> _setQty(CartLine line, int q) async {
    final err = await ref
        .read(cartControllerProvider.notifier)
        .setQuantity(line, q);
    if (err != null && mounted) _toast(errorMessage(context.l10n, err));
  }

  Future<void> _remove(CartLine line) async {
    final l10n = context.l10n;
    final err = await ref.read(cartControllerProvider.notifier).remove(line);
    if (!mounted) return;
    _toast(err != null ? errorMessage(l10n, err) : l10n.cartRemoved);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = ref.watch(cartControllerProvider);
    final currency = ref.watch(currencyLabelProvider);
    final cart = state.cart;
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(l10n.cartTitle),
      ),
      body: Builder(
        builder: (context) {
          if (cart == null && state.loading) return const _CartSkeleton();
          if (cart == null) {
            return ErrorState(
              error: state.error,
              onRetry: () => ref.read(cartControllerProvider.notifier).load(),
            );
          }
          if (cart.isEmpty) {
            return RefreshIndicator(
              onRefresh: () => ref.read(cartControllerProvider.notifier).load(),
              child: LayoutBuilder(
                builder: (context, c) => SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  child: SizedBox(
                    height: c.maxHeight,
                    child: EmptyState(
                      icon: Icons.shopping_bag_outlined,
                      title: l10n.cartEmptyTitle,
                      message: l10n.cartEmptyBody,
                      actionLabel: l10n.startShopping,
                      onAction: () => context.go(AppRoutes.home),
                    ),
                  ),
                ),
              ),
            );
          }
          return Column(
            children: [
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () =>
                      ref.read(cartControllerProvider.notifier).load(),
                  child: ListView(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    children: [
                      for (final line in cart.items)
                        _LineTile(
                          key: ValueKey('line-${line.id}'),
                          line: line,
                          currency: currency,
                          onQty: (q) => _setQty(line, q),
                          onRemove: () => _remove(line),
                        ),
                      const SizedBox(height: AppSpacing.sm),
                      _CodeBox(controller: _code, state: state),
                      const SizedBox(height: AppSpacing.sm),
                      TotalsCard(
                        totals: cart.totals,
                        currency: currency,
                        showShippingHint: true,
                      ),
                    ],
                  ),
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md,
                    AppSpacing.sm,
                    AppSpacing.md,
                    AppSpacing.sm,
                  ),
                  child: AppButton(
                    label: cart.hasUnavailable
                        ? l10n.cartUnavailableNotice
                        : l10n.cartCheckout,
                    icon: Icons.lock_outline_rounded,
                    onPressed: cart.hasUnavailable || state.busy
                        ? null
                        : () => context.push(AppRoutes.checkout),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _LineTile extends StatelessWidget {
  const _LineTile({
    super.key,
    required this.line,
    required this.currency,
    required this.onQty,
    required this.onRemove,
  });

  final CartLine line;
  final String currency;
  final ValueChanged<int> onQty;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.sm),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            NetworkImageX(
              url: line.image,
              width: 84,
              height: 84,
              radius: AppRadius.sm,
              cacheWidth: 168,
              semanticLabel: line.name,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          line.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: context.text.titleSmall,
                        ),
                      ),
                      IconButton(
                        tooltip: l10n.cartRemove,
                        visualDensity: VisualDensity.compact,
                        icon: const Icon(Icons.delete_outline_rounded),
                        onPressed: onRemove,
                      ),
                    ],
                  ),
                  if (line.isBundle && line.components.isNotEmpty)
                    Text(
                      line.components
                          .map((c) => l10n.cartComponent(c.name, c.quantity))
                          .join('، '),
                      style: context.text.bodySmall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  if (!line.available)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: StatusChip(
                        label: l10n.cartUnavailable,
                        tone: StatusTone.danger,
                      ),
                    ),
                  const SizedBox(height: AppSpacing.xs),
                  Row(
                    children: [
                      PriceText(amount: line.unitPrice, currency: currency),
                      const Spacer(),
                      QuantityStepper(value: line.quantity, onChanged: onQty),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CodeBox extends ConsumerWidget {
  const _CodeBox({required this.controller, required this.state});

  final TextEditingController controller;
  final CartState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final notifier = ref.read(cartControllerProvider.notifier);
    if (state.code != null) {
      return Card(
        child: ListTile(
          leading: Icon(
            Icons.local_offer_rounded,
            color: context.extra.success,
          ),
          title: Text(l10n.discountApplied(state.code!)),
          trailing: TextButton(
            onPressed: notifier.clearCode,
            child: Text(l10n.discountRemove),
          ),
        ),
      );
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: AppTextField(
            label: l10n.discountCode,
            controller: controller,
            errorText: state.codeError,
            prefixIcon: Icons.local_offer_outlined,
            textInputAction: TextInputAction.done,
            onSubmitted: (v) => notifier.applyCode(v),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: AppButton(
            label: l10n.discountApply,
            expand: false,
            loading: state.applyingCode,
            onPressed: () async {
              final ok = await notifier.applyCode(controller.text);
              if (ok) controller.clear();
            },
          ),
        ),
      ],
    );
  }
}

class _CartSkeleton extends StatelessWidget {
  const _CartSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(AppSpacing.md),
    children: [
      for (var i = 0; i < 3; i++) ...[
        const AppShimmer(height: 108, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.sm),
      ],
      const AppShimmer(height: 140, radius: AppRadius.md),
    ],
  );
}
