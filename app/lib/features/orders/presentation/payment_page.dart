import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/orders_controller.dart';
import '../application/payment_poller.dart';

/// True when [url] is the gateway handing the shopper back to the store
/// (`/{locale}/payments/return/{token}`).
bool isPaymentReturnUrl(String url) {
  final uri = Uri.tryParse(url);
  return uri != null && uri.path.contains('/payments/return/');
}

enum _Phase { web, confirming, paid, pending, error }

/// Online payment in an in-app WebView. When the gateway returns to
/// `payments/return/{token}` the page is intercepted and the order is polled
/// (`meta.paid`; only the signed webhook marks it). Pops `true` when paid.
class PaymentPage extends ConsumerStatefulWidget {
  const PaymentPage({super.key, required this.token, this.url});

  final String token;

  /// Gateway link from checkout; fetched with `orders/{token}/pay` if absent.
  final String? url;

  @override
  ConsumerState<PaymentPage> createState() => _PaymentPageState();
}

class _PaymentPageState extends ConsumerState<PaymentPage> {
  WebViewController? _web;
  _Phase _phase = _Phase.web;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    setState(() {
      _phase = _Phase.web;
      _error = null;
      _loading = true;
    });
    try {
      final url =
          widget.url ??
          await ref.read(ordersRepositoryProvider).pay(widget.token);
      if (!mounted) return;
      final c = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setNavigationDelegate(
          NavigationDelegate(
            onNavigationRequest: (req) {
              if (isPaymentReturnUrl(req.url)) {
                _confirm();
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
            onPageFinished: (_) {
              if (mounted) setState(() => _loading = false);
            },
          ),
        )
        ..loadRequest(Uri.parse(url));
      setState(() => _web = c);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _phase = _Phase.error;
          _error = e;
        });
      }
    }
  }

  Future<void> _confirm() async {
    if (_phase == _Phase.confirming) return;
    setState(() => _phase = _Phase.confirming);
    try {
      final res = await pollPayment(
        ref.read(ordersRepositoryProvider),
        widget.token,
      );
      if (!mounted) return;
      ref.invalidate(orderProvider(widget.token));
      setState(() => _phase = res.paid ? _Phase.paid : _Phase.pending);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _phase = _Phase.error;
          _error = e;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.paymentWebTitle),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          tooltip: l10n.close,
          onPressed: () => _phase == _Phase.web
              ? _confirm()
              : context.pop(_phase == _Phase.paid),
        ),
      ),
      body: switch (_phase) {
        _Phase.web =>
          _web == null
              ? const Center(child: CircularProgressIndicator())
              : Stack(
                  children: [
                    WebViewWidget(controller: _web!),
                    if (_loading) const LinearProgressIndicator(),
                  ],
                ),
        _Phase.confirming => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(),
              const SizedBox(height: AppSpacing.md),
              Text(l10n.paymentConfirming),
            ],
          ),
        ),
        _Phase.paid => EmptyState(
          icon: Icons.check_circle_outline_rounded,
          title: l10n.paymentSuccess,
          actionLabel: l10n.orderViewOrder,
          onAction: () => context.pop(true),
        ),
        _Phase.pending => EmptyState(
          icon: Icons.hourglass_bottom_rounded,
          title: l10n.paymentPendingTitle,
          message: l10n.paymentPendingBody,
          actionLabel: l10n.orderViewOrder,
          onAction: () => context.pop(false),
        ),
        _Phase.error => ErrorState(error: _error, onRetry: _start),
      },
    );
  }
}
