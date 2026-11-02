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
                  const AppShimmer(height: 96, radius: AppRadius.md),
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
              icon: Icons.receipt_long_outlined,
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
                return Card(
                  margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: ListTile(
                    onTap: () => context.push(AppRoutes.order(o.token)),
                    title: Row(
                      children: [
                        Expanded(
                          child: Text(o.number, style: context.text.titleSmall),
                        ),
                        StatusChip(
                          label: o.statusLabel,
                          tone: statusTone(o.statusValue),
                        ),
                      ],
                    ),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              [
                                formatDate(o.placedAt),
                                if (o.itemsCount != null)
                                  l10n.orderItemsCount(o.itemsCount!),
                              ].where((e) => e.isNotEmpty).join(' - '),
                            ),
                          ),
                          PriceText(amount: o.total, currency: currency),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
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
    final code = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
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
    final data = await showModalBottomSheet<ReturnRequestData>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
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
      appBar: AppBar(
        title: Text(order.asData?.value.number ?? l10n.orderDetailTitle),
        actions: [
          if (order.asData != null)
            IconButton(
              tooltip: l10n.orderInvoice,
              icon: const Icon(Icons.picture_as_pdf_outlined),
              onPressed: _busy ? null : () => _invoice(order.asData!.value),
            ),
        ],
      ),
      body: order.when(
        loading: () => const Center(child: CircularProgressIndicator()),
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
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              _Summary(order: o),
              if (o.canPayOnline && !o.isPaid) ...[
                const SizedBox(height: AppSpacing.sm),
                AppButton(
                  label: o.payNow == null
                      ? l10n.orderPayNow
                      : l10n.orderPayNowAmount(
                          l10n.currencyAmount(o.payNow!, currency),
                        ),
                  icon: Icons.payment_rounded,
                  loading: _busy,
                  onPressed: () => _payNow(o),
                ),
              ],
              if (o.canUploadProof) ...[
                const SizedBox(height: AppSpacing.sm),
                _Section(
                  title: l10n.orderUploadProof,
                  child: ProofUploader(
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
                ),
              ],
              const SizedBox(height: AppSpacing.sm),
              _Section(
                title: l10n.orderItems,
                child: Column(
                  children: [
                    for (final i in o.items)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(i.name),
                        subtitle: Text(
                          '${l10n.currencyAmount(i.unitPrice, currency)}  x ${i.quantity}',
                        ),
                        trailing: Text(
                          l10n.currencyAmount(i.lineSubtotal, currency),
                          style: context.text.titleSmall,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              TotalsCard(totals: orderTotals(o), currency: currency),
              if (o.city != null || o.address != null)
                _Section(
                  title: l10n.reviewAddress,
                  child: Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: Text(
                      [
                        o.customerName,
                        '${o.city ?? ''} ${o.address ?? ''}'.trim(),
                        if (o.shippingCompany != null) o.shippingCompany,
                      ].whereType<String>().join('\n'),
                    ),
                  ),
                ),
              if (o.timeline.isNotEmpty)
                _Section(
                  title: l10n.orderTimeline,
                  child: Column(
                    children: [
                      for (final t in o.timeline.reversed)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          dense: true,
                          leading: Icon(
                            Icons.circle,
                            size: 12,
                            color: context.colors.primary,
                          ),
                          title: Text(t.status),
                          subtitle: Text(
                            [
                              formatDate(t.at),
                              if (t.note != null) t.note!,
                            ].where((e) => e.isNotEmpty).join(' - '),
                          ),
                        ),
                    ],
                  ),
                ),
              if (o.returns.isNotEmpty)
                _Section(
                  title: l10n.orderReturns,
                  child: Column(
                    children: [
                      for (final r in o.returns)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(r.number),
                          subtitle: Text(r.status),
                        ),
                    ],
                  ),
                ),
              const SizedBox(height: AppSpacing.md),
              if (signedIn && o.canCancel)
                AppButton(
                  label: l10n.orderCancel,
                  variant: AppButtonVariant.outlined,
                  onPressed: _busy ? null : () => _cancel(o),
                ),
              if (signedIn &&
                  o.canReturn &&
                  o.items.any((i) => i.returnable > 0))
                AppButton(
                  label: l10n.orderReturn,
                  variant: AppButtonVariant.outlined,
                  onPressed: _busy ? null : () => _return(o),
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
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(top: AppSpacing.sm),
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: context.text.titleSmall),
          const SizedBox(height: AppSpacing.xs),
          child,
        ],
      ),
    ),
  );
}

class _Summary extends StatelessWidget {
  const _Summary({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final o = order;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.xs,
              children: [
                StatusChip(
                  label: o.statusLabel,
                  tone: statusTone(o.statusValue),
                ),
                StatusChip(
                  label: paymentStatusLabel(l10n, o.paymentStatus),
                  tone: o.isPaid ? StatusTone.success : StatusTone.warning,
                ),
                if (o.proofStatus != null)
                  StatusChip(
                    label: proofStatusLabel(l10n, o.proofStatus!),
                    tone: o.proofStatus == 'rejected'
                        ? StatusTone.danger
                        : StatusTone.neutral,
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            if (o.placedAt != null)
              Text('${l10n.orderPlacedAt}: ${formatDate(o.placedAt)}'),
            if (o.paymentMethodName != null)
              Text('${l10n.reviewPayment}: ${o.paymentMethodName}'),
          ],
        ),
      ),
    );
  }
}
