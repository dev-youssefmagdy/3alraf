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
import '../../catalog/presentation/catalog_widgets.dart';
import '../application/orders_controller.dart';
import '../data/order_models.dart';
import 'order_widgets.dart';
import 'proof_widgets.dart';

/// Shown right after checkout. [placed] carries `next_action`; when the page
/// is opened without it (deep link) the order is fetched and the action is
/// derived from its flags.
class OrderConfirmationPage extends ConsumerStatefulWidget {
  const OrderConfirmationPage({super.key, required this.token, this.placed});

  final String token;
  final PlacedOrder? placed;

  @override
  ConsumerState<OrderConfirmationPage> createState() =>
      _OrderConfirmationPageState();
}

class _OrderConfirmationPageState extends ConsumerState<OrderConfirmationPage> {
  late Order? _order = widget.placed?.order;
  late NextAction? _action = widget.placed?.nextAction;
  String? _paymentUrl;
  bool _autoOpened = false;

  @override
  void initState() {
    super.initState();
    _paymentUrl = widget.placed?.paymentUrl;
    if (_order == null) {
      _load();
    } else {
      WidgetsBinding.instance.addPostFrameCallback((_) => _maybeAutoOpen());
    }
  }

  void _snack(String text) => context.showSnack(text);

  Future<void> _load() async {
    try {
      final o = await ref.read(ordersRepositoryProvider).show(widget.token);
      if (!mounted) return;
      setState(() {
        _order = o;
        _action = o.canPayOnline
            ? NextAction.retryPayment
            : o.canUploadProof
            ? NextAction.uploadProof
            : NextAction.none;
      });
    } on ApiException catch (e) {
      if (mounted) _snack(errorMessage(context.l10n, e));
    }
  }

  void _maybeAutoOpen() {
    if (_autoOpened || !mounted) return;
    if (_action == NextAction.redirectToPayment && _paymentUrl != null) {
      _autoOpened = true;
      _pay();
    }
  }

  Future<void> _pay() async {
    final url = _paymentUrl;
    final paid = await context.push<bool>(
      AppRoutes.orderPay(widget.token),
      extra: url,
    );
    _paymentUrl = null; // a used link is not reused
    if (paid != null) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final currency = ref.watch(currencyLabelProvider);
    final o = _order;
    final x = context.extra;
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.surface,
      body: o == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: EdgeInsets.zero,
              children: [
                _SuccessHero(order: o),
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.gutter),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SectionCard(
                        child: Column(
                          children: [
                            if (o.placedAt != null)
                              _kv(
                                context,
                                l10n.orderPlacedAt,
                                formatDate(o.placedAt),
                              ),
                            if (o.paymentMethodName != null)
                              _kv(
                                context,
                                l10n.reviewPayment,
                                o.paymentMethodName!,
                              ),
                            _kv(
                              context,
                              l10n.orderStatus,
                              paymentStatusLabel(l10n, o.paymentStatus),
                              color: o.isPaid ? x.successText : x.warningText,
                            ),
                            const Divider(height: AppSpacing.lg),
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    l10n.cartTotal,
                                    style: context.text.titleLarge,
                                  ),
                                ),
                                Text(
                                  l10n.money(o.total, currency),
                                  style: context.textX.priceL.copyWith(
                                    color: c.primary,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      if (_action == NextAction.redirectToPayment ||
                          _action == NextAction.retryPayment) ...[
                        AppButton(
                          label: l10n.orderPayNow,
                          icon: Icons.payment_rounded,
                          variant: AppButtonVariant.pop,
                          size: AppButtonSize.l,
                          onPressed: _pay,
                        ),
                        const SizedBox(height: AppSpacing.smd),
                      ],
                      if (_action == NextAction.uploadProof) ...[
                        SectionCard(
                          title: l10n.orderUploadProof,
                          child: ProofUploader(
                            onUpload: (path) async {
                              try {
                                final updated = await ref
                                    .read(ordersRepositoryProvider)
                                    .uploadProof(widget.token, path);
                                if (!mounted) return;
                                setState(() {
                                  _order = updated;
                                  _action = NextAction.none;
                                });
                                _snack(l10n.orderProofUploaded);
                              } on ApiException catch (e) {
                                if (mounted) _snack(errorMessage(l10n, e));
                              }
                            },
                          ),
                        ),
                        const SizedBox(height: AppSpacing.smd),
                      ],
                      AppButton(
                        label: l10n.orderViewOrder,
                        size: AppButtonSize.l,
                        onPressed: () {
                          context.go(AppRoutes.home);
                          context.push(AppRoutes.order(o.token));
                        },
                      ),
                      const SizedBox(height: AppSpacing.smd),
                      AppButton(
                        label: l10n.startShopping,
                        variant: AppButtonVariant.outlined,
                        size: AppButtonSize.l,
                        onPressed: () => context.go(AppRoutes.home),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _kv(BuildContext context, String k, String v, {Color? color}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Expanded(
              child: Text(
                k,
                style: context.text.bodyMedium?.copyWith(
                  color: context.colors.onSurfaceVariant,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Flexible(
              child: Text(
                v,
                textAlign: TextAlign.end,
                style: context.text.titleSmall?.copyWith(color: color),
              ),
            ),
          ],
        ),
      );
}

/// Mint success hero (`59:3482`): 96 white check circle, "تم استلام طلبك",
/// the order number pill with a pop copy button and the review line.
class _SuccessHero extends StatelessWidget {
  const _SuccessHero({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    final top = MediaQuery.paddingOf(context).top;
    return Container(
      clipBehavior: Clip.hardEdge,
      decoration: BoxDecoration(color: x.mint),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -40,
            end: -50,
            child: ExcludeSemantics(
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: x.sun.withValues(alpha: 0.35),
                ),
              ),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              AppSpacing.gutter,
              top + AppSpacing.lg,
              AppSpacing.gutter,
              AppSpacing.lg,
            ),
            child: Column(
              children: [
                Container(
                  width: 96,
                  height: 96,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: AppShadows.raised,
                  ),
                  child: Icon(
                    Icons.check_rounded,
                    size: 52,
                    color: x.mintStrong,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                Semantics(
                  header: true,
                  child: Text(
                    l10n.orderPlacedTitle,
                    textAlign: TextAlign.center,
                    style: context.text.displaySmall?.copyWith(
                      color: AppColors.slate950,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  l10n.orderThanksLead,
                  textAlign: TextAlign.center,
                  style: context.text.bodyMedium?.copyWith(
                    color: AppColors.slate950,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Container(
                  padding: const EdgeInsetsDirectional.fromSTEB(16, 4, 4, 4),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(AppRadius.pill),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Directionality(
                        textDirection: TextDirection.ltr,
                        child: Text(
                          order.number,
                          style: context.text.titleLarge,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      IconButton.filled(
                        tooltip: l10n.orderNumberCopy,
                        style: IconButton.styleFrom(
                          backgroundColor: x.pop,
                          foregroundColor: Colors.white,
                          fixedSize: const Size.square(36),
                          minimumSize: const Size.square(36),
                        ),
                        iconSize: 18,
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: order.number));
                          context.showSnack(
                            l10n.copied,
                            tone: SnackTone.success,
                          );
                        },
                        icon: const Icon(Icons.copy_rounded),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.smd),
                Text(
                  l10n.orderPlacedAwaiting,
                  textAlign: TextAlign.center,
                  style: context.text.bodySmall?.copyWith(
                    color: AppColors.slate950,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
