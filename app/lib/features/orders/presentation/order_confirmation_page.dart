import 'package:flutter/material.dart';
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
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(l10n.orderPlacedTitle),
      ),
      body: o == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                Icon(
                  Icons.check_circle_rounded,
                  size: 72,
                  color: context.extra.success,
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  l10n.orderPlacedBody(o.number),
                  textAlign: TextAlign.center,
                  style: context.text.titleMedium,
                ),
                const SizedBox(height: AppSpacing.sm),
                Center(
                  child: PriceText(
                    amount: o.total,
                    currency: currency,
                    style: context.text.headlineSmall,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Center(
                  child: StatusChip(
                    label: paymentStatusLabel(l10n, o.paymentStatus),
                    tone: o.isPaid ? StatusTone.success : StatusTone.warning,
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                if (_action == NextAction.redirectToPayment ||
                    _action == NextAction.retryPayment)
                  AppButton(
                    label: l10n.orderPayNow,
                    icon: Icons.payment_rounded,
                    onPressed: _pay,
                  ),
                if (_action == NextAction.uploadProof) ...[
                  Text(l10n.proofRequired, textAlign: TextAlign.center),
                  const SizedBox(height: AppSpacing.sm),
                  ProofUploader(
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
                ],
                if (_action == NextAction.none) ...[
                  Text(
                    l10n.orderPlacedAwaiting,
                    textAlign: TextAlign.center,
                    style: context.text.bodyMedium,
                  ),
                ],
                const SizedBox(height: AppSpacing.md),
                AppButton(
                  label: l10n.orderViewOrder,
                  variant: AppButtonVariant.outlined,
                  onPressed: () {
                    context.go(AppRoutes.home);
                    context.push(AppRoutes.order(o.token));
                  },
                ),
                const SizedBox(height: AppSpacing.sm),
                AppButton(
                  label: l10n.startShopping,
                  variant: AppButtonVariant.text,
                  onPressed: () => context.go(AppRoutes.home),
                ),
              ],
            ),
    );
  }
}
