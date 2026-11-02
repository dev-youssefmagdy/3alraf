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
import '../../addresses/presentation/addresses_pages.dart';
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
                      padding: const EdgeInsets.all(AppSpacing.md),
                      children: [
                        switch (s.step) {
                          CheckoutStep.address => _AddressStep(state: s),
                          CheckoutStep.shipping => _ShippingStep(
                            state: s,
                            currency: currency,
                          ),
                          CheckoutStep.packaging => _PackagingStep(
                            state: s,
                            currency: currency,
                          ),
                          CheckoutStep.payment => _PaymentStep(state: s),
                          CheckoutStep.review => _ReviewStep(
                            state: s,
                            currency: currency,
                          ),
                        },
                      ],
                    ),
                  ),
                  SafeArea(
                    top: false,
                    child: Material(
                      elevation: 8,
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        child: Row(
                          children: [
                            if (quoteTotal != null)
                              Padding(
                                padding: const EdgeInsetsDirectional.only(
                                  end: AppSpacing.md,
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      l10n.cartTotal,
                                      style: context.text.bodySmall,
                                    ),
                                    PriceText(
                                      amount: quoteTotal,
                                      currency: currency,
                                    ),
                                  ],
                                ),
                              ),
                            Expanded(
                              child: AppButton(
                                label: last
                                    ? l10n.placeOrder
                                    : l10n.continueAction,
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
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      child: Row(
        children: [
          for (var i = 0; i < steps.length; i++) ...[
            Expanded(
              child: InkWell(
                onTap: () => onTap(steps[i]),
                child: Semantics(
                  label: _stepLabel(context, steps[i]),
                  selected: i == cur,
                  child: Column(
                    children: [
                      CircleAvatar(
                        radius: 14,
                        backgroundColor: i <= cur
                            ? context.colors.primary
                            : context.colors.surfaceContainerHigh,
                        foregroundColor: i <= cur
                            ? context.colors.onPrimary
                            : context.colors.onSurfaceVariant,
                        child: i < cur
                            ? const Icon(Icons.check_rounded, size: 16)
                            : Text('${i + 1}'),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _stepLabel(context, steps[i]),
                        style: context.text.labelSmall,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
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
          child: Text(text!, style: TextStyle(color: context.colors.error)),
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
        Text(l10n.stepAddress, style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.sm),
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
            child: Text(l10n.addressNone, textAlign: TextAlign.center),
          ),
        for (final a in addresses.items)
          AddressCard(
            address: a,
            selected: a.id == state.addressId,
            onTap: () => c.selectAddress(a),
          ),
        const SizedBox(height: AppSpacing.sm),
        OutlinedButton.icon(
          icon: const Icon(Icons.add_rounded),
          label: Text(l10n.addressAdd),
          onPressed: () async {
            final saved = await context.push<Address>(AppRoutes.addressEdit);
            if (saved != null) await c.selectAddress(saved);
          },
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
        Text(l10n.stepShipping, style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.sm),
        _ErrorText(state.errorFor('shipping_company_id')),
        if (state.loadingShipping)
          const Center(child: CircularProgressIndicator())
        else if (state.shippingOptions.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(l10n.shippingNoOptions, textAlign: TextAlign.center),
          ),
        for (final o in state.shippingOptions)
          Card(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md),
              side: BorderSide(
                color: o.id == state.shippingId
                    ? context.colors.primary
                    : Colors.transparent,
                width: 2,
              ),
            ),
            child: ListTile(
              onTap: () => c.selectShipping(o.id),
              leading: Icon(
                o.id == state.shippingId
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: context.colors.primary,
              ),
              title: Text(o.name),
              subtitle: o.etaDays == null
                  ? null
                  : Text(l10n.shippingEta(o.etaDays!)),
              trailing: o.free
                  ? StatusChip(
                      label: l10n.shippingFree,
                      tone: StatusTone.success,
                    )
                  : Text(
                      l10n.currencyAmount(o.fee, currency),
                      style: context.text.titleSmall,
                    ),
            ),
          ),
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
    }) => Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(
          color: selected ? context.colors.primary : Colors.transparent,
          width: 2,
        ),
      ),
      child: ListTile(
        onTap: onTap,
        leading: image == null
            ? Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: context.colors.primary,
              )
            : NetworkImageX(
                url: image,
                width: 48,
                height: 48,
                radius: 8,
                cacheWidth: 96,
              ),
        title: Text(title),
        subtitle: subtitle == null ? null : Text(subtitle),
        trailing: price == null
            ? null
            : Text(
                l10n.currencyAmount(price, currency),
                style: context.text.titleSmall,
              ),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l10n.stepPackaging, style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.sm),
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
  const _PaymentStep({required this.state});

  final CheckoutState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = ref.read(checkoutControllerProvider.notifier);
    final methods = state.payments.methods;
    final selected = state.payment;
    final proofMethod = state.needsPrepay ? state.prepay : selected;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l10n.stepPayment, style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.sm),
        _ErrorText(state.errorFor('payment_method_id')),
        if (methods.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(l10n.paymentNoMethods, textAlign: TextAlign.center),
          ),
        for (final m in methods)
          Card(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md),
              side: BorderSide(
                color: m.id == state.paymentId
                    ? context.colors.primary
                    : Colors.transparent,
                width: 2,
              ),
            ),
            child: ListTile(
              onTap: () => c.selectPayment(m.id),
              leading: Icon(
                m.id == state.paymentId
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: context.colors.primary,
              ),
              title: Text(m.name),
              subtitle: m.isCod || m.instructions == null
                  ? null
                  : Text(m.instructions!),
            ),
          ),
        if (state.needsPrepay) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(l10n.paymentPrepayTitle, style: context.text.titleSmall),
          Text(l10n.paymentPrepayBody, style: context.text.bodySmall),
          _ErrorText(state.errorFor('prepay_method_id')),
          RadioGroup<int>(
            groupValue: state.prepayId,
            onChanged: (v) => v == null ? null : c.selectPrepay(v),
            child: Column(
              children: [
                for (final m in state.payments.prepayCandidates)
                  RadioListTile<int>(
                    value: m.id,
                    title: Text(m.name),
                    subtitle: m.instructions == null
                        ? null
                        : Text(m.instructions!),
                  ),
              ],
            ),
          ),
        ],
        if (state.proofRequired && proofMethod != null) ...[
          const SizedBox(height: AppSpacing.md),
          Card(
            color: context.colors.primaryContainer,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l10n.proofRequired, style: context.text.titleSmall),
                  if (proofMethod.accountNumber != null)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: SelectableText(proofMethod.accountNumber!),
                      trailing: IconButton(
                        tooltip: l10n.copy,
                        icon: const Icon(Icons.copy_rounded),
                        onPressed: () {
                          Clipboard.setData(
                            ClipboardData(text: proofMethod.accountNumber!),
                          );
                          context.showSnack(l10n.copied);
                        },
                      ),
                    ),
                  _ErrorText(state.errorFor('proof')),
                  ProofPreview(
                    path: state.proofPath,
                    onChange: () async {
                      final p = await pickProof(context, ref);
                      if (p != null) c.setProof(p);
                    },
                    onRemove: state.proofPath == null
                        ? null
                        : () => c.setProof(null),
                  ),
                ],
              ),
            ),
          ),
        ],
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
    }) => Card(
      child: ListTile(
        title: Text(title, style: context.text.labelMedium),
        subtitle: Text(body, style: context.text.bodyMedium),
        trailing: editable
            ? TextButton(onPressed: () => c.goTo(step), child: Text(l10n.edit))
            : null,
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l10n.stepReview, style: context.text.titleMedium),
        const SizedBox(height: AppSpacing.sm),
        if (problems.isNotEmpty)
          Card(
            color: context.colors.errorContainer,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final p in problems)
                    Text(p, style: TextStyle(color: context.colors.error)),
                ],
              ),
            ),
          ),
        if (address != null)
          section(
            l10n.reviewAddress,
            '${address.name} - ${address.phone}\n${address.city}, ${address.address}',
            CheckoutStep.address,
          ),
        if (ship != null)
          section(
            l10n.reviewShipping,
            ship.free
                ? '${ship.name} - ${l10n.shippingFree}'
                : '${ship.name} - ${l10n.currencyAmount(ship.fee, currency)}',
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
        const SizedBox(height: AppSpacing.sm),
        AppTextField(
          label: l10n.reviewNotes,
          hint: l10n.reviewNotesHint,
          maxLines: 2,
          prefixIcon: Icons.notes_rounded,
          onChanged: c.setNotes,
        ),
        const SizedBox(height: AppSpacing.sm),
        if (state.working && cart == null)
          const Center(child: CircularProgressIndicator())
        else if (cart != null)
          TotalsCard(totals: cart.totals, currency: currency),
      ],
    );
  }
}
