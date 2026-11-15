import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../cart/presentation/totals_card.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../application/orders_controller.dart';
import '../data/order_models.dart';
import 'order_sheets.dart';
import 'order_widgets.dart';
import 'proof_widgets.dart';

class OrdersPage extends ConsumerStatefulWidget {
  const OrdersPage({super.key});

  @override
  ConsumerState<OrdersPage> createState() => _OrdersPageState();
}

class _OrdersPageState extends ConsumerState<OrdersPage> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 400) {
        ref.read(ordersControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final s = ref.watch(ordersControllerProvider);
    final currency = ref.watch(currencyLabelProvider);
    final c = ref.read(ordersControllerProvider.notifier);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.ordersTitle)),
      body: Builder(
        builder: (context) {
          if (s.orders.isEmpty && s.loading) {
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                for (var i = 0; i < 4; i++) ...[
                  const AppShimmer(height: 92, radius: AppRadius.md),
                  const SizedBox(height: AppSpacing.sm),
                ],
              ],
            );
          }
          if (s.orders.isEmpty && s.error != null) {
            return ErrorState(error: s.error, onRetry: c.refresh);
          }
          if (s.orders.isEmpty) {
            return EmptyState(
              icon: Icons.inventory_2_outlined,
              title: l10n.ordersEmptyTitle,
              message: l10n.ordersEmptyBody,
              actionLabel: l10n.startShopping,
              onAction: () => context.go(AppRoutes.home),
            );
          }
          return RefreshIndicator(
            onRefresh: c.refresh,
            child: ListView.builder(
              controller: _scroll,
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: s.orders.length + (s.hasMore ? 1 : 0),
              itemBuilder: (context, i) {
                if (i >= s.orders.length) {
                  return Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Center(
                      child: s.loadMoreFailed
                          ? TextButton(
                              onPressed: c.loadMore,
                              child: Text(l10n.retry),
                            )
                          : const CircularProgressIndicator(),
                    ),
                  );
                }
                final o = s.orders[i];
                return Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.smd),
                  child: _OrderRow(order: o, currency: currency),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

/// Order row (`60:3472`): 56 icon tile, number H3 + status badge, date ·
/// count, total, forward chevron.
class _OrderRow extends StatelessWidget {
  const _OrderRow({required this.order, required this.currency});

  final Order order;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final o = order;
    final meta = [
      formatDate(o.placedAt),
      if (o.itemsCount != null) l10n.orderItemsCount(o.itemsCount!),
    ].where((e) => e.isNotEmpty).join(' · ');
    return Material(
      color: c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(color: c.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(AppRoutes.order(o.token)),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.smd),
          child: Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: x.brandSoft,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: Icon(Icons.inventory_2_outlined, color: c.primary),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: AppSpacing.sm,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(o.number, style: context.text.titleLarge),
                        StatusChip(
                          label: o.statusLabel,
                          tone: statusTone(o.statusValue),
                        ),
                      ],
                    ),
                    if (meta.isNotEmpty)
                      Text(
                        meta,
                        style: context.text.bodySmall?.copyWith(
                          color: x.textMuted,
                        ),
                      ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.money(o.total, currency),
                      style: context.textX.priceM.copyWith(
                        color: c.onSurface,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: x.textMuted),
            ],
          ),
        ),
      ),
    );
  }
}

class OrderDetailPage extends ConsumerStatefulWidget {
  const OrderDetailPage({super.key, required this.token});

  final String token;

  @override
  ConsumerState<OrderDetailPage> createState() => _OrderDetailPageState();
}

class _OrderDetailPageState extends ConsumerState<OrderDetailPage> {
  bool _busy = false;

  void _snack(String text) => context.showSnack(text);

  Future<void> _run(Future<void> Function() action) async {
    final l10n = context.l10n;
    setState(() => _busy = true);
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) _snack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _refresh() {
    ref.invalidate(orderProvider(widget.token));
    ref.invalidate(ordersControllerProvider);
  }

  Future<void> _cancel(Order o) async {
    final l10n = context.l10n;
    final code = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => const CancelSheet(),
    );
    if (code == null || !mounted) return;
    await _run(() async {
      await ref.read(ordersRepositoryProvider).cancel(o.token, code);
      _refresh();
      if (mounted) _snack(l10n.orderCancelled);
    });
  }

  Future<void> _return(Order o) async {
    final l10n = context.l10n;
    final data = await showAppBottomSheet<ReturnRequestData>(
      context: context,
      builder: (_) => ReturnSheet(order: o),
    );
    if (data == null || !mounted) return;
    await _run(() async {
      await ref
          .read(ordersRepositoryProvider)
          .requestReturn(
            o.token,
            quantities: data.quantities,
            reason: data.reason,
          );
      _refresh();
      if (mounted) _snack(l10n.orderReturnSent);
    });
  }

  Future<void> _invoice(Order o) async {
    final l10n = context.l10n;
    await _run(() async {
      final path = await ref
          .read(ordersRepositoryProvider)
          .downloadInvoice(o.token, o.number);
      await SharePlus.instance.share(
        ShareParams(
          files: [XFile(path, mimeType: 'application/pdf')],
          subject: l10n.orderInvoiceSubject(o.number),
        ),
      );
    });
  }

  Future<void> _payNow(Order o) async {
    final paid = await context.push<bool>(AppRoutes.orderPay(o.token));
    if (paid != null) _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final order = ref.watch(orderProvider(widget.token));
    final currency = ref.watch(currencyLabelProvider);
    // Public tracking: a guest with the link may view, pay and upload a
    // proof, but cancel / return need an account (API: bearer only).
    final signedIn = ref.watch(
      authControllerProvider.select((s) => s.signedIn),
    );
    return Scaffold(
      appBar: AppBar(title: Text(l10n.orderDetailTitle)),
      body: order.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            for (final h in const [140.0, 260.0, 160.0]) ...[
              AppShimmer(height: h, radius: AppRadius.md),
              const SizedBox(height: AppSpacing.smd),
            ],
          ],
        ),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(orderProvider(widget.token)),
        ),
        data: (o) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(orderProvider(widget.token));
            await ref.read(orderProvider(widget.token).future);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.gutter,
              AppSpacing.gutter,
              AppSpacing.gutter,
              AppSpacing.xl,
            ),
            children: [
              _Summary(
                order: o,
                busy: _busy,
                onInvoice: () => _invoice(o),
                onPay: o.canPayOnline && !o.isPaid ? () => _payNow(o) : null,
                payLabel: o.payNow == null
                    ? l10n.orderPayNow
                    : l10n.orderPayNowAmount(
                        l10n.money(o.payNow!, currency),
                      ),
              ),
              if (o.timeline.isNotEmpty)
                _Section(
                  title: l10n.orderTimeline,
                  child: OrderTimeline(entries: o.timeline),
                ),
              _Section(
                title: l10n.orderItems,
                child: Column(
                  children: [
                    for (final i in o.items)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(i.name, style: context.text.titleSmall),
                                  Text(
                                    [
                                      '× ${i.quantity}',
                                      l10n.money(
                                        i.unitPrice,
                                        currency,
                                      ),
                                      ?i.bundleName,
                                    ].join(' · '),
                                    style: context.text.bodySmall?.copyWith(
                                      color: context.extra.textMuted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              l10n.money(i.lineSubtotal, currency),
                              style: context.text.titleSmall,
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
              if (o.city != null || o.address != null)
                _Section(
                  title: l10n.reviewAddress,
                  child: Text(
                    [
                      o.customerName,
                      '${o.city ?? ''} ${o.address ?? ''}'.trim(),
                      if (o.shippingCompany != null) o.shippingCompany,
                    ].whereType<String>().join('\n'),
                    style: context.text.bodyMedium?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                  ),
                ),
              if (o.canUploadProof || o.proofStatus != null)
                _Section(
                  title: l10n.orderUploadProof,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (o.proofStatus != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                          child: Row(
                            children: [
                              Icon(
                                Icons.schedule_rounded,
                                size: 16,
                                color: o.proofStatus == 'rejected'
                                    ? context.colors.error
                                    : context.extra.warningText,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                proofStatusLabel(l10n, o.proofStatus!),
                                style: context.text.labelSmall?.copyWith(
                                  color: o.proofStatus == 'rejected'
                                      ? context.colors.error
                                      : context.extra.warningText,
                                ),
                              ),
                            ],
                          ),
                        ),
                      if (o.canUploadProof)
                        ProofUploader(
                          onUpload: (path) async {
                            try {
                              await ref
                                  .read(ordersRepositoryProvider)
                                  .uploadProof(o.token, path);
                              _refresh();
                              if (mounted) _snack(l10n.orderProofUploaded);
                            } on ApiException catch (e) {
                              if (mounted) _snack(errorMessage(l10n, e));
                            }
                          },
                        ),
                    ],
                  ),
                ),
              const SizedBox(height: AppSpacing.smd),
              TotalsCard(totals: orderTotals(o), currency: currency),
              if (o.returns.isNotEmpty)
                _Section(
                  title: l10n.orderReturns,
                  child: Column(
                    children: [
                      for (final r in o.returns)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  r.number,
                                  style: context.text.titleSmall,
                                ),
                              ),
                              StatusChip(label: r.status),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              const SizedBox(height: AppSpacing.md),
              Row(
                children: [
                  if (signedIn &&
                      o.canReturn &&
                      o.items.any((i) => i.returnable > 0))
                    Expanded(
                      child: AppButton(
                        label: l10n.orderReturn,
                        variant: AppButtonVariant.outlined,
                        size: AppButtonSize.l,
                        onPressed: _busy ? null : () => _return(o),
                      ),
                    ),
                  if (signedIn &&
                      o.canReturn &&
                      o.items.any((i) => i.returnable > 0) &&
                      o.canCancel)
                    const SizedBox(width: AppSpacing.smd),
                  if (signedIn && o.canCancel)
                    Expanded(
                      child: AppButton(
                        label: l10n.orderCancel,
                        variant: AppButtonVariant.danger,
                        size: AppButtonSize.l,
                        onPressed: _busy ? null : () => _cancel(o),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => SectionCard(
    margin: const EdgeInsets.only(top: AppSpacing.smd),
    title: title,
    child: child,
  );
}

/// Header card: number, status, date, payment badge (+ proof badge), the
/// invoice outline button and the Pop "pay now".
class _Summary extends StatelessWidget {
  const _Summary({
    required this.order,
    required this.busy,
    required this.onInvoice,
    required this.onPay,
    required this.payLabel,
  });

  final Order order;
  final bool busy;
  final VoidCallback onInvoice;
  final VoidCallback? onPay;
  final String payLabel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final o = order;
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(o.number, style: context.text.headlineMedium),
              ),
              StatusChip(label: o.statusLabel, tone: statusTone(o.statusValue)),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.xs,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              if (o.placedAt != null)
                Text(
                  '${l10n.orderPlacedAt}: ${formatDate(o.placedAt)}',
                  style: context.text.bodySmall?.copyWith(
                    color: context.extra.textMuted,
                  ),
                ),
              StatusChip(
                label: paymentStatusLabel(l10n, o.paymentStatus),
                tone: o.isPaid ? StatusTone.success : StatusTone.neutral,
              ),
              if (o.proofStatus != null)
                StatusChip(
                  label: proofStatusLabel(l10n, o.proofStatus!),
                  tone: o.proofStatus == 'rejected'
                      ? StatusTone.danger
                      : StatusTone.warning,
                ),
            ],
          ),
          if (o.paymentMethodName != null) ...[
            const SizedBox(height: 2),
            Text(
              '${l10n.reviewPayment}: ${o.paymentMethodName}',
              style: context.text.bodySmall?.copyWith(
                color: context.extra.textMuted,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          if (onPay != null) ...[
            AppButton(
              label: payLabel,
              icon: Icons.payment_rounded,
              variant: AppButtonVariant.pop,
              loading: busy,
              onPressed: onPay,
            ),
            const SizedBox(height: AppSpacing.sm),
          ],
          AppButton(
            label: l10n.orderInvoice,
            icon: Icons.picture_as_pdf_outlined,
            variant: AppButtonVariant.outlined,
            onPressed: busy ? null : onInvoice,
          ),
        ],
      ),
    );
  }
}
