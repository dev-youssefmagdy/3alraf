import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../catalog/application/catalog_providers.dart';
import '../../catalog/data/catalog_models.dart';
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
            final suggestions =
                ref.watch(homeProvider).asData?.value.recommended ??
                const <ProductSummary>[];
            return RefreshIndicator(
              onRefresh: () => ref.read(cartControllerProvider.notifier).load(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(
                  bottom: AppSpacing.lg + kRaisedNavClearance,
                ),
                children: [
                  StateBlock(
                    icon: Icons.shopping_cart_outlined,
                    tone: StateTone.pop,
                    title: l10n.cartEmptyTitle,
                    message: l10n.cartEmptyBody,
                    actionLabel: l10n.startShopping,
                    onAction: () => context.go(AppRoutes.home),
                  ),
                  ProductRail(
                    title: l10n.railRecommended,
                    products: suggestions,
                    scope: 'cart-empty',
                  ),
                ],
              ),
            );
          }
          final total = l10n.money(cart.totals.total, currency);
          return Column(
            children: [
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () =>
                      ref.read(cartControllerProvider.notifier).load(),
                  child: ListView(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    children: [
                      PageTitleRow(
                        title: l10n.cartTitle,
                        count: l10n.productsCount(cart.count),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: AppSpacing.gutter,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (cart.hasFlash) ...[
                              NoteBar(
                                text: l10n.cartFlashNote,
                                icon: Icons.schedule_rounded,
                                background: context.extra.sunSoft,
                                foreground: context.extra.warningText,
                              ),
                              const SizedBox(height: AppSpacing.smd),
                            ],
                            for (final line in cart.items) ...[
                              _LineTile(
                                key: ValueKey('line-${line.id}'),
                                line: line,
                                currency: currency,
                                onQty: (q) => _setQty(line, q),
                                onRemove: () => _remove(line),
                              ),
                              const SizedBox(height: AppSpacing.smd),
                            ],
                            if (cart.hasUnavailable) ...[
                              NoteBar(
                                text: l10n.cartUnavailableNotice,
                                icon: Icons.warning_amber_rounded,
                                background: context.colors.errorContainer,
                                foreground: context.colors.onErrorContainer,
                              ),
                              const SizedBox(height: AppSpacing.smd),
                            ],
                            SectionCard(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  _CodeBox(controller: _code, state: state),
                                  const SizedBox(height: AppSpacing.md),
                                  TotalsCard(
                                    totals: cart.totals,
                                    currency: currency,
                                    showShippingHint: true,
                                    bare: true,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: AppSpacing.smd),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.verified_user_outlined,
                                  size: 16,
                                  color: context.extra.textMuted,
                                ),
                                const SizedBox(width: 6),
                                Flexible(
                                  child: Text(
                                    l10n.cartSecureNote,
                                    style: context.text.bodySmall?.copyWith(
                                      color: context.extra.textMuted,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              // Stacks above the bottom nav; the extra bottom space keeps
              // the raised gift circle off the button.
              DecoratedBox(
                decoration: BoxDecoration(
                  color: context.colors.surfaceContainerLowest,
                  boxShadow: AppShadows.bar,
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md,
                    AppSpacing.md,
                    AppSpacing.md,
                    AppSpacing.md + kRaisedNavClearance,
                  ),
                  child: Row(
                    children: [
                      BarTotal(label: l10n.cartTotal, amount: total),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: AppButton(
                          label: cart.hasUnavailable
                              ? l10n.cartUnavailableNotice
                              : l10n.cartCheckout,
                          size: AppButtonSize.l,
                          onPressed: cart.hasUnavailable || state.busy
                              ? null
                              : () => context.push(AppRoutes.checkout),
                        ),
                      ),
                    ],
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
    final c = context.colors;
    final x = context.extra;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.smd),
      decoration: BoxDecoration(
        color: c.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: line.isBundle ? x.gold : c.outlineVariant),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Opacity(
            opacity: line.available ? 1 : 0.5,
            child: Container(
              decoration: BoxDecoration(
                color: line.isBundle ? x.goldSoft : x.cardTint(line.id),
                borderRadius: BorderRadius.circular(AppRadius.sm),
              ),
              child: NetworkImageX(
                url: line.image,
                width: 88,
                height: 88,
                radius: AppRadius.sm,
                cacheWidth: 176,
                semanticLabel: line.name,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.smd),
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
                      color: c.onSurfaceVariant,
                      icon: const Icon(Icons.delete_outline_rounded),
                      onPressed: onRemove,
                    ),
                  ],
                ),
                if (line.isBundle) ...[
                  StatusChip(
                    label: l10n.cartBundleLine,
                    tone: StatusTone.gold,
                    icon: Icons.inventory_2_outlined,
                  ),
                  if (line.components.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        line.components
                            .map((c) => l10n.cartComponent(c.name, c.quantity))
                            .join('، '),
                        style: context.text.bodySmall?.copyWith(
                          color: x.textMuted,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                if (!line.available)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: StatusChip(
                      label: l10n.cartUnavailable,
                      tone: StatusTone.danger,
                    ),
                  ),
                const SizedBox(height: AppSpacing.sm),
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.xs,
                  children: [
                    PriceText(amount: line.unitPrice, currency: currency),
                    QuantityStepper(
                      value: line.quantity,
                      onChanged: onQty,
                      compact: true,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
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
    final x = context.extra;
    final notifier = ref.read(cartControllerProvider.notifier);
    if (state.code != null) {
      return Row(
        children: [
          Icon(Icons.check_rounded, size: 18, color: x.success),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              l10n.discountApplied(state.code!),
              style: context.text.labelSmall?.copyWith(color: x.successText),
            ),
          ),
          TextButton(
            onPressed: notifier.clearCode,
            child: Text(l10n.discountRemove),
          ),
        ],
      );
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: AppTextField(
            label: l10n.discountCode,
            controller: controller,
            errorText: state.codeError,
            ltr: true,
            textInputAction: TextInputAction.done,
            onSubmitted: (v) => notifier.applyCode(v),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Padding(
          padding: EdgeInsets.only(bottom: state.codeError == null ? 2 : 26),
          child: AppButton(
            label: l10n.discountApply,
            expand: false,
            variant: AppButtonVariant.outlined,
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
      const AppShimmer(width: 140, height: 36),
      const SizedBox(height: AppSpacing.md),
      for (var i = 0; i < 3; i++) ...[
        const AppShimmer(height: 114, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.sm),
      ],
      const AppShimmer(height: 140, radius: AppRadius.md),
    ],
  );
}
