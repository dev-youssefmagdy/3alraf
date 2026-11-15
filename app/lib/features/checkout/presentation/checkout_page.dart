import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../addresses/application/address_controller.dart';
import '../../addresses/data/address_models.dart';
import '../../cart/application/cart_controller.dart';
import '../../cart/data/cart_models.dart';
import '../../cart/presentation/totals_card.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../orders/presentation/proof_widgets.dart';
import '../application/checkout_controller.dart';
import '../data/checkout_models.dart';

String _stepLabel(BuildContext c, CheckoutStep s) => switch (s) {
  CheckoutStep.address => c.l10n.stepAddress,
  CheckoutStep.shipping => c.l10n.stepShipping,
  CheckoutStep.packaging => c.l10n.stepPackaging,
  CheckoutStep.payment => c.l10n.stepPayment,
  CheckoutStep.review => c.l10n.stepReview,
};

/// Checkout stepper: address -> shipping -> packaging -> payment -> review.
/// Signed-in only (the router sends guests to login and back).
class CheckoutPage extends ConsumerStatefulWidget {
  const CheckoutPage({super.key});

  @override
  ConsumerState<CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends ConsumerState<CheckoutPage> {
  Future<void> _place() async {
    final l10n = context.l10n;
    final router = GoRouter.of(context);
    final placed = await ref.read(checkoutControllerProvider.notifier).place();
    if (!mounted) return;
    final s = ref.read(checkoutControllerProvider);
    if (placed != null) {
      HapticFeedback.mediumImpact();
      router.go(AppRoutes.orderConfirmation(placed.order.token), extra: placed);
      return;
    }
    if (s.cartProblem) {
      final msg = s.errors.values.expand((e) => e).firstOrNull;
      router.go(AppRoutes.cart);
      if (msg != null) context.showSnack(msg);
      return;
    }
    if (s.message != null) {
      context.showSnack(s.message!);
      ref.read(checkoutControllerProvider.notifier).consumeMessage();
    } else {
      context.showSnack(l10n.checkoutFixErrors);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final s = ref.watch(checkoutControllerProvider);
    final c = ref.read(checkoutControllerProvider.notifier);
    final currency = ref.watch(currencyLabelProvider);
    final cart = ref.watch(cartControllerProvider).cart;
    final steps = s.steps;
    final index = steps.indexOf(s.step);
    final last = index == steps.length - 1;
    final quoteTotal = (s.quote ?? cart)?.totals.total;

    return PopScope(
      canPop: index <= 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) c.back();
      },
      child: Scaffold(
        appBar: AppBar(title: Text(l10n.checkoutTitle)),
        body: s.loadingOptions
            ? const Center(child: CircularProgressIndicator())
            : s.optionsError != null && s.payments.methods.isEmpty
            ? ErrorState(error: s.optionsError, onRetry: c.retryInit)
            : Column(
                children: [
                  _StepHeader(
                    steps: steps,
                    current: s.step,
                    onTap: (st) {
                      // Only go back to completed steps.
                      if (steps.indexOf(st) < index) c.goTo(st);
                    },
                  ),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.gutter,
                        AppSpacing.sm,
                        AppSpacing.gutter,
                        AppSpacing.lg,
                      ),
                      children: [
                        SectionCard(
                          number: index + 1,
                          title: _stepLabel(context, s.step),
                          child: switch (s.step) {
                            CheckoutStep.address => _AddressStep(state: s),
                            CheckoutStep.shipping => _ShippingStep(
                              state: s,
                              currency: currency,
                            ),
                            CheckoutStep.packaging => _PackagingStep(
                              state: s,
                              currency: currency,
                            ),
                            CheckoutStep.payment => _PaymentStep(
                              state: s,
                              currency: currency,
                            ),
                            CheckoutStep.review => _ReviewStep(
                              state: s,
                              currency: currency,
                            ),
                          },
                        ),
                      ],
                    ),
                  ),
                  StickyActionBar(
                    leading: quoteTotal == null
                        ? null
                        : BarTotal(
                            label: l10n.cartTotal,
                            amount: l10n.money(quoteTotal, currency),
                          ),
                    action: AppButton(
                      label: last ? l10n.placeOrder : l10n.continueAction,
                      size: AppButtonSize.l,
                      loading: s.working,
                      onPressed: last
                          ? _place
                          : s.canLeave(s.step)
                          ? c.next
                          : null,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({
    required this.steps,
    required this.current,
    required this.onTap,
  });

  final List<CheckoutStep> steps;
  final CheckoutStep current;
  final ValueChanged<CheckoutStep> onTap;

  @override
  Widget build(BuildContext context) {
    final cur = steps.indexOf(current);
    final c = context.colors;
    final x = context.extra;
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: AppSpacing.smd,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < steps.length; i++)
            Expanded(
              child: InkWell(
                onTap: () => onTap(steps[i]),
                borderRadius: BorderRadius.circular(AppRadius.sm),
                child: Semantics(
                  label: _stepLabel(context, steps[i]),
                  selected: i == cur,
                  child: Column(
                    children: [
                      Container(
                        width: 28,
                        height: 28,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: i < cur
                              ? x.success
                              : i == cur
                              ? c.primary
                              : c.surfaceContainerHighest,
                        ),
                        child: i < cur
                            ? const Icon(
                                Icons.check_rounded,
                                size: 16,
                                color: Colors.white,
                              )
                            : Text(
                                '${i + 1}',
                                style: context.textX.labelXs.copyWith(
                                  color: i == cur ? c.onPrimary : x.textMuted,
                                ),
                              ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _stepLabel(context, steps[i]),
                        style: context.textX.labelXs.copyWith(
                          color: i < cur
                              ? x.success
                              : i == cur
                              ? c.primary
                              : x.textMuted,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ErrorText extends StatelessWidget {
  const _ErrorText(this.text);

  final String? text;

  @override
  Widget build(BuildContext context) => text == null
      ? const SizedBox.shrink()
      : Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
          child: Text(
            text!,
            style: context.text.bodySmall?.copyWith(
              color: context.colors.error,
            ),
          ),
        );
}

// -- address ---------------------------------------------------------------

class _AddressStep extends ConsumerWidget {
  const _AddressStep({required this.state});

  final CheckoutState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final addresses = ref.watch(addressesProvider);
    final c = ref.read(checkoutControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ErrorText(
          state.errorFor('address_id') ??
              state.errorFor('city_id') ??
              state.errorFor('address') ??
              state.errorFor('phone') ??
              state.errorFor('name'),
        ),
        if (addresses.items.isEmpty && addresses.loading)
          const Center(child: CircularProgressIndicator())
        else if (addresses.items.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(
              l10n.addressNone,
              textAlign: TextAlign.center,
              style: context.text.bodyMedium?.copyWith(
                color: context.colors.onSurfaceVariant,
              ),
            ),
          ),
        for (final a in addresses.items) ...[
          ChoiceCard(
            key: ValueKey('checkout-address-${a.id}'),
            title: a.label ?? a.name,
            selected: a.id == state.addressId,
            onTap: () => c.selectAddress(a),
            badge: a.isDefault
                ? StatusChip(label: l10n.addressDefault, tone: StatusTone.pop)
                : null,
            subtitle:
                '${a.name} · \u2066${a.phone}\u2069\n${a.city} - ${a.address}',
            trailing: OutlinedButton.icon(
              onPressed: () async {
                final saved = await context.push<Address>(
                  AppRoutes.addressEdit,
                  extra: a,
                );
                if (saved != null && saved.id == state.addressId) {
                  await c.selectAddress(saved);
                }
              },
              icon: const Icon(Icons.edit_outlined, size: 14),
              label: Text(l10n.edit),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(44, 32),
                padding: const EdgeInsets.symmetric(horizontal: 10),
                textStyle: context.textX.labelXs.copyWith(fontSize: 12),
                backgroundColor: context.colors.surfaceContainerLowest,
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.smd),
        ],
        DashedBox(
          semanticLabel: l10n.addressAdd,
          onTap: () async {
            final saved = await context.push<Address>(AppRoutes.addressEdit);
            if (saved != null) await c.selectAddress(saved);
          },
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.add_rounded, size: 18, color: context.colors.primary),
              const SizedBox(width: 6),
              Text(
                l10n.addressAdd,
                style: context.text.labelMedium?.copyWith(
                  color: context.colors.primary,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// -- shipping --------------------------------------------------------------

class _ShippingStep extends ConsumerWidget {
  const _ShippingStep({required this.state, required this.currency});

  final CheckoutState state;
  final String currency;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = ref.read(checkoutControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ErrorText(state.errorFor('shipping_company_id')),
        if (state.loadingShipping)
          const Center(child: CircularProgressIndicator())
        else if (state.shippingOptions.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(
              l10n.shippingNoOptions,
              textAlign: TextAlign.center,
              style: context.text.bodyMedium?.copyWith(
                color: context.colors.onSurfaceVariant,
              ),
            ),
          ),
        for (final o in state.shippingOptions) ...[
          ChoiceCard(
            title: o.name,
            icon: Icons.local_shipping_outlined,
            selected: o.id == state.shippingId,
            onTap: () => c.selectShipping(o.id),
            subtitle: o.etaDays == null ? null : l10n.shippingEta(o.etaDays!),
            trailing: o.free
                ? StatusChip(label: l10n.shippingFree, tone: StatusTone.success)
                : Text(
                    l10n.money(o.fee, currency),
                    style: context.text.titleSmall?.copyWith(
                      color: context.extra.successText,
                    ),
                  ),
          ),
          const SizedBox(height: AppSpacing.smd),
        ],
      ],
    );
  }
}

// -- packaging -------------------------------------------------------------

class _PackagingStep extends ConsumerWidget {
  const _PackagingStep({required this.state, required this.currency});

  final CheckoutState state;
  final String currency;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = ref.read(checkoutControllerProvider.notifier);
    Widget tile({
      required bool selected,
      required String title,
      String? subtitle,
      String? price,
      String? image,
      required VoidCallback onTap,
    }) => Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.smd),
      child: ChoiceCard(
        selected: selected,
        title: title,
        subtitle: subtitle,
        onTap: onTap,
        leading: image == null
            ? null
            : Container(
                decoration: BoxDecoration(
                  color: context.extra.goldSoft,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: NetworkImageX(
                  url: image,
                  width: 56,
                  height: 56,
                  radius: AppRadius.sm,
                  cacheWidth: 112,
                ),
              ),
        trailing: price == null
            ? null
            : Text(
                '+ ${l10n.money(price, currency)}',
                style: context.text.titleSmall?.copyWith(
                  color: context.extra.goldText,
                ),
              ),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ErrorText(state.errorFor('packaging_option_id')),
        tile(
          selected: state.packagingId == null,
          title: l10n.packagingNone,
          onTap: () => c.selectPackaging(null),
        ),
        for (final p in state.packaging)
          tile(
            selected: state.packagingId == p.id,
            title: p.name,
            subtitle: p.description,
            price: p.price,
            image: p.image,
            onTap: () => c.selectPackaging(p.id),
          ),
      ],
    );
  }
}

// -- payment ---------------------------------------------------------------

class _PaymentStep extends ConsumerWidget {
  const _PaymentStep({required this.state, required this.currency});

  final CheckoutState state;
  final String currency;

  /// "+ 2.5%" / "+ 15 EGP" / "no fee", from the method's server values.
  Widget _fee(BuildContext context, PaymentMethod m) {
    final l10n = context.l10n;
    final parts = [
      if (!isZeroMoney(m.feePercent)) '+ ${m.feePercent}%',
      if (!isZeroMoney(m.feeFixed))
        '+ ${l10n.money(m.feeFixed, currency)}',
    ];
    return Text(
      parts.isEmpty ? l10n.noFee : parts.join(' '),
      style: context.textX.labelXs.copyWith(
        color: context.extra.successText,
        fontSize: 12,
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final x = context.extra;
    final c = ref.read(checkoutControllerProvider.notifier);
    final methods = state.payments.methods;
    final selected = state.payment;
    final proofMethod = state.needsPrepay ? state.prepay : selected;
    final proof = state.proofRequired && proofMethod != null
        ? _ProofBlock(state: state, method: proofMethod)
        : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ErrorText(state.errorFor('payment_method_id')),
        if (methods.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(
              l10n.paymentNoMethods,
              textAlign: TextAlign.center,
              style: context.text.bodyMedium?.copyWith(
                color: context.colors.onSurfaceVariant,
              ),
            ),
          ),
        for (final m in methods) ...[
          ChoiceCard(
            title: m.name,
            icon: Icons.payments_outlined,
            selected: m.id == state.paymentId,
            onTap: () => c.selectPayment(m.id),
            subtitle: m.isCod || m.instructions == null
                ? null
                : m.instructions!,
            trailing: _fee(context, m),
          ),
          const SizedBox(height: AppSpacing.smd),
          // COD: the mint "pay shipping upfront" block with the transfer
          // methods; other methods show their proof block under the card.
          if (m.id == state.paymentId && state.needsPrepay) ...[
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: x.mintSoft,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    l10n.paymentPrepayTitle,
                    style: context.text.titleSmall?.copyWith(
                      color: x.mintStrong,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    l10n.paymentPrepayBody,
                    style: context.text.bodySmall?.copyWith(
                      color: x.mintStrong,
                    ),
                  ),
                  _ErrorText(state.errorFor('prepay_method_id')),
                  const SizedBox(height: AppSpacing.sm),
                  for (final p in state.payments.prepayCandidates) ...[
                    ChoiceCard(
                      title: p.name,
                      icon: Icons.account_balance_wallet_outlined,
                      selected: state.prepayId == p.id,
                      onTap: () => c.selectPrepay(p.id),
                      subtitle: p.instructions,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                  ],
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.smd),
          ],
          if (m.id == state.paymentId && proof != null) ...[
            proof,
            const SizedBox(height: AppSpacing.smd),
          ],
        ],
      ],
    );
  }
}

/// "حوّل إلى" account card with a Pop copy button, then the dashed proof
/// upload target (opens the gallery / camera sheet).
class _ProofBlock extends ConsumerWidget {
  const _ProofBlock({required this.state, required this.method});

  final CheckoutState state;
  final PaymentMethod method;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = ref.read(checkoutControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (method.accountNumber != null) ...[
          TransferToCard(number: method.accountNumber!),
          const SizedBox(height: AppSpacing.smd),
        ],
        _ErrorText(state.errorFor('proof')),
        ProofPreview(
          path: state.proofPath,
          onChange: () async {
            final p = await pickProof(context, ref);
            if (p != null) c.setProof(p);
          },
          onRemove: state.proofPath == null ? null : () => c.setProof(null),
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          l10n.proofRequired,
          style: context.text.bodySmall?.copyWith(
            color: context.extra.textMuted,
          ),
        ),
      ],
    );
  }
}

// -- review ----------------------------------------------------------------

class _ReviewStep extends ConsumerWidget {
  const _ReviewStep({required this.state, required this.currency});

  final CheckoutState state;
  final String currency;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = ref.read(checkoutControllerProvider.notifier);
    final addresses = ref.watch(addressesProvider).items;
    final address = addresses.where((a) => a.id == state.addressId).firstOrNull;
    final ship = state.shippingOptions
        .where((o) => o.id == state.shippingId)
        .firstOrNull;
    final pack = state.packaging
        .where((p) => p.id == state.packagingId)
        .firstOrNull;
    final quote = state.quote;
    final Cart? cart = quote ?? ref.watch(cartControllerProvider).cart;
    final problems = state.errors.entries
        .expand((e) => e.value)
        .toSet()
        .toList();

    Widget section(
      String title,
      String body,
      CheckoutStep step, {
      bool editable = true,
    }) => Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.smd),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: context.text.bodySmall?.copyWith(
                    color: context.extra.textMuted,
                  ),
                ),
                Text(body, style: context.text.titleSmall),
              ],
            ),
          ),
          if (editable)
            TextButton(
              onPressed: () => c.goTo(step),
              style: TextButton.styleFrom(
                foregroundColor: context.extra.pop,
                minimumSize: const Size(44, 36),
                textStyle: context.textX.labelXs.copyWith(fontSize: 12),
              ),
              child: Text(l10n.edit),
            ),
        ],
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (problems.isNotEmpty) ...[
          Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              color: context.colors.errorContainer,
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final p in problems)
                  Text(
                    p,
                    style: context.text.bodySmall?.copyWith(
                      color: context.colors.onErrorContainer,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.smd),
        ],
        if (address != null)
          section(
            l10n.reviewAddress,
            '${address.name} - \u2066${address.phone}\u2069\n${address.city}, ${address.address}',
            CheckoutStep.address,
          ),
        if (ship != null)
          section(
            l10n.reviewShipping,
            ship.free
                ? '${ship.name} - ${l10n.shippingFree}'
                : '${ship.name} - ${l10n.money(ship.fee, currency)}',
            CheckoutStep.shipping,
          ),
        if (pack != null)
          section(l10n.stepPackaging, pack.name, CheckoutStep.packaging),
        if (state.payment != null)
          section(
            l10n.reviewPayment,
            state.payment!.name,
            CheckoutStep.payment,
          ),
        const SizedBox(height: AppSpacing.xs),
        AppTextField(
          label: l10n.reviewNotes,
          hint: l10n.reviewNotesHint,
          maxLines: 2,
          onChanged: c.setNotes,
        ),
        const SizedBox(height: AppSpacing.md),
        if (state.working && cart == null)
          const Center(child: CircularProgressIndicator())
        else if (cart != null)
          TotalsCard(totals: cart.totals, currency: currency),
      ],
    );
  }
}
