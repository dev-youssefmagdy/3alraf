import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/app_localizations.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/presentation/auth_widgets.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../orders/presentation/order_widgets.dart';
import '../../store/application/store_controller.dart';
import '../application/affiliate_controller.dart';
import '../data/affiliate_models.dart';

StatusTone _tone(String status) => switch (status) {
  'paid' || 'approved' || 'active' => StatusTone.success,
  'pending' || 'requested' || 'processing' => StatusTone.warning,
  'rejected' || 'cancelled' || 'failed' => StatusTone.danger,
  _ => StatusTone.neutral,
};

void _leave(BuildContext context) =>
    context.canPop() ? context.pop() : context.go(AppRoutes.affiliate);

Future<void> _copy(BuildContext context, String text) async {
  await Clipboard.setData(ClipboardData(text: text));
  if (context.mounted) context.showSnack(context.l10n.linkCopied);
}

/// Entry: login when signed out, dashboard when signed in.
class AffiliateHubPage extends ConsumerWidget {
  const AffiliateHubPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(affiliateControllerProvider);
    if (!s.ready) {
      return Scaffold(
        appBar: AppBar(title: Text(context.l10n.affiliateTitle)),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    return s.signedIn ? const _DashboardView() : const _AffiliateLogin();
  }
}

class _AffiliateLogin extends ConsumerStatefulWidget {
  const _AffiliateLogin();

  @override
  ConsumerState<_AffiliateLogin> createState() => _AffiliateLoginState();
}

class _AffiliateLoginState extends ConsumerState<_AffiliateLogin> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      await ref
          .read(affiliateControllerProvider.notifier)
          .login(_email.text.trim(), _password.text);
    } on ApiException catch (e) {
      if (!mounted) return;
      final fields = FormErrors.from(e);
      setState(() => _errors = fields);
      if (fields.fields.isEmpty) {
        context.showSnack(errorMessage(context.l10n, e));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return AuthScaffold(
      title: l10n.affiliateTitle,
      subtitle: l10n.affiliateIntro,
      children: [
        Form(
          key: _form,
          child: Column(
            children: [
              AppTextField(
                label: l10n.email,
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.email],
                prefixIcon: Icons.mail_outline_rounded,
                errorText: _errors['email'],
                validator: (v) => emailValidator(context, v),
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                label: l10n.password,
                controller: _password,
                obscure: true,
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.password],
                prefixIcon: Icons.lock_outline_rounded,
                errorText: _errors['password'],
                validator: (v) => requiredValidator(context, v),
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(label: l10n.login, loading: _busy, onPressed: _submit),
        const SizedBox(height: AppSpacing.sm),
        AppButton(
          label: l10n.affiliateApply,
          variant: AppButtonVariant.outlined,
          onPressed: () => context.push(AppRoutes.affiliateRegister),
        ),
      ],
    );
  }
}

class AffiliateRegisterPage extends ConsumerStatefulWidget {
  const AffiliateRegisterPage({super.key});

  @override
  ConsumerState<AffiliateRegisterPage> createState() =>
      _AffiliateRegisterPageState();
}

class _AffiliateRegisterPageState extends ConsumerState<AffiliateRegisterPage> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;
  bool _sent = false;

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      await ref
          .read(affiliateControllerProvider.notifier)
          .register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirm.text,
            phone: _phone.text.trim(),
          );
      if (mounted) setState(() => _sent = true);
    } on ApiException catch (e) {
      if (!mounted) return;
      final fields = FormErrors.from(e);
      setState(() => _errors = fields);
      if (fields.fields.isEmpty) {
        context.showSnack(errorMessage(context.l10n, e));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    const gap = SizedBox(height: AppSpacing.md);
    if (_sent) {
      return AuthScaffold(
        title: l10n.affiliateApplied,
        subtitle: l10n.affiliateAppliedBody,
        children: [
          AppButton(label: l10n.close, onPressed: () => _leave(context)),
        ],
      );
    }
    return AuthScaffold(
      title: l10n.affiliateApply,
      subtitle: l10n.affiliateIntro,
      children: [
        Form(
          key: _form,
          child: Column(
            children: [
              AppTextField(
                label: l10n.fullName,
                controller: _name,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.person_outline_rounded,
                errorText: _errors['name'],
                validator: (v) => requiredValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.email,
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.mail_outline_rounded,
                errorText: _errors['email'],
                validator: (v) => emailValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.phoneOptional,
                controller: _phone,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.phone_outlined,
                errorText: _errors['phone'],
              ),
              gap,
              AppTextField(
                label: l10n.password,
                controller: _password,
                obscure: true,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.lock_outline_rounded,
                errorText: _errors['password'],
                validator: (v) => passwordValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.confirmPassword,
                controller: _confirm,
                obscure: true,
                textInputAction: TextInputAction.done,
                prefixIcon: Icons.lock_outline_rounded,
                errorText: _errors['password_confirmation'],
                validator: (v) =>
                    v == _password.text ? null : l10n.passwordMismatch,
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(
          label: l10n.affiliateApply,
          loading: _busy,
          onPressed: _submit,
        ),
      ],
    );
  }
}

class _DashboardView extends ConsumerWidget {
  const _DashboardView();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final dash = ref.watch(affiliateDashboardProvider);
    final currency = ref.watch(currencyLabelProvider);
    final store = ref.watch(storeProfileProvider);
    String money(String v) => l10n.currencyAmount(v, currency);
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.affiliateTitle),
        actions: [
          IconButton(
            tooltip: l10n.logout,
            icon: const Icon(Icons.logout_rounded),
            onPressed: () =>
                ref.read(affiliateControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: dash.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: const [
            AppShimmer(height: 120, radius: AppRadius.md),
            SizedBox(height: AppSpacing.sm),
            AppShimmer(height: 120, radius: AppRadius.md),
          ],
        ),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(affiliateDashboardProvider),
        ),
        data: (d) {
          final st = d.stats;
          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(affiliateDashboardProvider);
              await ref.read(affiliateDashboardProvider.future);
            },
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                Text(
                  l10n.affiliateWelcome(d.affiliate.name),
                  style: context.text.titleLarge,
                ),
                if (d.affiliate.status != 'approved')
                  Padding(
                    padding: const EdgeInsets.only(top: AppSpacing.xs),
                    child: StatusChip(
                      label: d.affiliate.status,
                      tone: _tone(d.affiliate.status),
                    ),
                  ),
                const SizedBox(height: AppSpacing.md),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: AppSpacing.sm,
                  crossAxisSpacing: AppSpacing.sm,
                  childAspectRatio: 2.2,
                  children: [
                    _Stat(l10n.affiliateClicks, '${st.clicks}'),
                    _Stat(l10n.affiliateOrders, '${st.orders}'),
                    _Stat(l10n.affiliateConversion, '${st.conversionPercent}%'),
                    _Stat(l10n.affiliateSales, money(st.lifetimeSales)),
                  ],
                ),
                if (st.tier != null)
                  Card(
                    margin: const EdgeInsets.only(top: AppSpacing.sm),
                    child: ListTile(
                      leading: const Icon(Icons.workspace_premium_outlined),
                      title: Text(
                        l10n.affiliateTier(st.tier!.name, st.tier!.percent),
                      ),
                      subtitle: st.nextTier == null
                          ? null
                          : Text(
                              l10n.affiliateNextTier(
                                st.nextTier!.name,
                                money(st.nextTier!.minSales),
                              ),
                            ),
                    ),
                  ),
                Card(
                  margin: const EdgeInsets.only(top: AppSpacing.sm),
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          l10n.affiliateCommissions,
                          style: context.text.titleSmall,
                        ),
                        for (final k in const [
                          'pending',
                          'approved',
                          'paid',
                          'available',
                        ])
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(_commissionLabel(l10n, k)),
                                ),
                                Text(
                                  money(st.commissions[k] ?? '0.00'),
                                  style: context.text.titleSmall,
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                _NavTile(
                  Icons.share_outlined,
                  l10n.affiliateProducts,
                  AppRoutes.affiliateProducts,
                ),
                _NavTile(
                  Icons.payments_outlined,
                  l10n.affiliatePayouts,
                  AppRoutes.affiliatePayouts,
                ),
                if (store?.features['ambassadors'] ?? false)
                  _NavTile(
                    Icons.campaign_outlined,
                    l10n.ambassadorTitle,
                    AppRoutes.affiliateAmbassador,
                  ),
                if (d.recentCommissions.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    l10n.affiliateRecentCommissions,
                    style: context.text.titleSmall,
                  ),
                  for (final c in d.recentCommissions)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(c.order ?? '-'),
                      subtitle: Text('${c.percent}%'),
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(money(c.amount)),
                          StatusChip(label: c.status, tone: _tone(c.status)),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

String _commissionLabel(AppLocalizations l10n, String key) => switch (key) {
  'pending' => l10n.commissionPending,
  'approved' => l10n.commissionApproved,
  'paid' => l10n.commissionPaid,
  _ => l10n.commissionAvailable,
};

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.sm),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: context.text.bodySmall),
          Text(
            value,
            style: context.text.titleMedium,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    ),
  );
}

class _NavTile extends StatelessWidget {
  const _NavTile(this.icon, this.title, this.route);

  final IconData icon;
  final String title;
  final String route;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(top: AppSpacing.sm),
    child: ListTile(
      leading: Icon(icon),
      title: Text(title),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => context.push(route),
    ),
  );
}

class AffiliateProductsPage extends ConsumerWidget {
  const AffiliateProductsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final products = ref.watch(affiliateProductsProvider);
    final currency = ref.watch(currencyLabelProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.affiliateProducts)),
      body: products.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(affiliateProductsProvider),
        ),
        data: (list) => list.isEmpty
            ? EmptyState(
                icon: Icons.inventory_2_outlined,
                title: l10n.emptyTitle,
              )
            : RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(affiliateProductsProvider);
                  await ref.read(affiliateProductsProvider.future);
                },
                child: ListView.builder(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  itemCount: list.length,
                  itemBuilder: (context, i) {
                    final p = list[i];
                    return Card(
                      key: ValueKey('aff:${p.id}'),
                      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.sm),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                NetworkImageX(
                                  url: p.image,
                                  width: 56,
                                  height: 56,
                                  radius: AppRadius.sm,
                                  cacheWidth: 56,
                                  semanticLabel: p.name,
                                ),
                                const SizedBox(width: AppSpacing.sm),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        p.name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: context.text.titleSmall,
                                      ),
                                      Text(
                                        l10n.currencyAmount(p.price, currency),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            Wrap(
                              spacing: AppSpacing.xs,
                              children: [
                                TextButton.icon(
                                  onPressed: () => _copy(context, p.link),
                                  icon: const Icon(Icons.copy_rounded),
                                  label: Text(l10n.copyLink),
                                ),
                                TextButton.icon(
                                  onPressed: () => SharePlus.instance.share(
                                    ShareParams(text: '${p.name}\n${p.link}'),
                                  ),
                                  icon: const Icon(Icons.share_outlined),
                                  label: Text(l10n.share),
                                ),
                                TextButton.icon(
                                  onPressed: () => showQr(context, p),
                                  icon: const Icon(Icons.qr_code_2_rounded),
                                  label: Text(l10n.affiliateQr),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

/// QR of the affiliate share link (rendered locally from the link).
Future<void> showQr(BuildContext context, AffiliateProduct p) =>
    showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis),
        content: SizedBox.square(
          dimension: 240,
          child: Semantics(
            label: c.l10n.affiliateQr,
            image: true,
            child: QrImageView(
              data: p.link,
              backgroundColor: Colors.white,
              padding: const EdgeInsets.all(8),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: Text(c.l10n.close),
          ),
        ],
      ),
    );

class AffiliatePayoutsPage extends ConsumerStatefulWidget {
  const AffiliatePayoutsPage({super.key});

  @override
  ConsumerState<AffiliatePayoutsPage> createState() =>
      _AffiliatePayoutsPageState();
}

class _AffiliatePayoutsPageState extends ConsumerState<AffiliatePayoutsPage> {
  bool _busy = false;

  Future<void> _request() async {
    final l10n = context.l10n;
    setState(() => _busy = true);
    try {
      await ref.read(affiliateRepositoryProvider).requestPayout();
      ref.invalidate(affiliatePayoutsProvider);
      ref.invalidate(affiliateDashboardProvider);
      if (mounted) context.showSnack(l10n.payoutRequested);
    } on ApiException catch (e) {
      if (mounted) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final payouts = ref.watch(affiliatePayoutsProvider);
    final currency = ref.watch(currencyLabelProvider);
    final available = ref
        .watch(affiliateDashboardProvider)
        .asData
        ?.value
        .stats
        .commissions['available'];
    return Scaffold(
      appBar: AppBar(title: Text(l10n.affiliatePayouts)),
      body: payouts.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(affiliatePayoutsProvider),
        ),
        data: (list) => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (available != null)
                      Text(
                        l10n.payoutAvailable(
                          l10n.currencyAmount(available, currency),
                        ),
                        style: context.text.titleMedium,
                      ),
                    const SizedBox(height: AppSpacing.sm),
                    AppButton(
                      label: l10n.payoutRequest,
                      icon: Icons.payments_outlined,
                      loading: _busy,
                      onPressed: _request,
                    ),
                  ],
                ),
              ),
            ),
            if (list.isEmpty)
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Center(child: Text(l10n.payoutsEmpty)),
              ),
            for (final p in list)
              ListTile(
                key: ValueKey('payout:${p.id}'),
                title: Text(l10n.currencyAmount(p.amount, currency)),
                subtitle: Text(formatDate(p.requestedAt)),
                trailing: StatusChip(label: p.status, tone: _tone(p.status)),
              ),
          ],
        ),
      ),
    );
  }
}

class AmbassadorPage extends ConsumerStatefulWidget {
  const AmbassadorPage({super.key});

  @override
  ConsumerState<AmbassadorPage> createState() => _AmbassadorPageState();
}

class _AmbassadorPageState extends ConsumerState<AmbassadorPage> {
  final _disclosure = TextEditingController();
  bool _terms = false;
  bool _busy = false;
  String? _error;
  int? _productId;
  String? _channel;

  @override
  void dispose() {
    _disclosure.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    final l10n = context.l10n;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      ref.invalidate(ambassadorProvider);
    } on ApiException catch (e) {
      if (mounted) {
        setState(
          () => _error = e.firstErrorFor('disclosure') ?? errorMessage(l10n, e),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final portal = ref.watch(ambassadorProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.ambassadorTitle)),
      body: portal.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            e is ApiException && e.kind == ApiErrorKind.featureDisabled
            ? EmptyState(
                icon: Icons.campaign_outlined,
                title: l10n.ambassadorUnavailable,
              )
            : ErrorState(
                error: e,
                onRetry: () => ref.invalidate(ambassadorProvider),
              ),
        data: (a) => a.enrolled ? _enrolled(context, a) : _join(context),
      ),
    );
  }

  Widget _join(BuildContext context) {
    final l10n = context.l10n;
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Text(l10n.ambassadorIntro),
        const SizedBox(height: AppSpacing.md),
        AppTextField(
          label: l10n.ambassadorDisclosure,
          controller: _disclosure,
          maxLines: 3,
          errorText: _error,
          onChanged: (_) => setState(() {}),
        ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(l10n.ambassadorTerms),
          value: _terms,
          onChanged: (v) => setState(() => _terms = v ?? false),
        ),
        const SizedBox(height: AppSpacing.sm),
        AppButton(
          label: l10n.ambassadorJoin,
          loading: _busy,
          onPressed: _terms && _disclosure.text.trim().isNotEmpty
              ? () => _run(
                  () => ref
                      .read(affiliateRepositoryProvider)
                      .joinAmbassador(disclosure: _disclosure.text.trim()),
                )
              : null,
        ),
      ],
    );
  }

  Widget _enrolled(BuildContext context, AmbassadorPortal a) {
    final l10n = context.l10n;
    final products =
        ref.watch(affiliateProductsProvider).asData?.value ??
        const <AffiliateProduct>[];
    final channel = _channel ?? (a.channels.isEmpty ? null : a.channels.first);
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l10n.ambassadorReport(a.report['window_days'] ?? 30),
                  style: context.text.titleSmall,
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  '${l10n.ambassadorValid}: ${a.report['valid_orders'] ?? 0}   '
                  '${l10n.ambassadorPending}: ${a.report['pending_orders'] ?? 0}   '
                  '${l10n.ambassadorExcluded}: ${a.report['excluded_orders'] ?? 0}',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(l10n.ambassadorNewLink, style: context.text.titleSmall),
        const SizedBox(height: AppSpacing.xs),
        DropdownButtonFormField<int>(
          initialValue: _productId,
          isExpanded: true,
          decoration: InputDecoration(labelText: l10n.ambassadorProduct),
          items: [
            for (final p in products)
              DropdownMenuItem(
                value: p.id,
                child: Text(p.name, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (v) => setState(() => _productId = v),
        ),
        const SizedBox(height: AppSpacing.sm),
        if (a.channels.isNotEmpty)
          Wrap(
            spacing: AppSpacing.xs,
            children: [
              for (final c in a.channels)
                ChoiceChip(
                  label: Text(c),
                  selected: c == channel,
                  onSelected: (_) => setState(() => _channel = c),
                ),
            ],
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.xs),
            child: Text(_error!, style: TextStyle(color: context.colors.error)),
          ),
        const SizedBox(height: AppSpacing.sm),
        AppButton(
          label: l10n.ambassadorCreateLink,
          loading: _busy,
          onPressed: _productId == null || channel == null
              ? null
              : () => _run(
                  () => ref
                      .read(affiliateRepositoryProvider)
                      .createLink(productId: _productId!, channel: channel)
                      .then((_) {}),
                ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(l10n.ambassadorLinks, style: context.text.titleSmall),
        if (a.links.isEmpty) Text(l10n.ambassadorNoLinks),
        for (final l in a.links)
          ListTile(
            key: ValueKey('link:${l.id}'),
            contentPadding: EdgeInsets.zero,
            title: Text(l.url, textDirection: TextDirection.ltr),
            subtitle: Text(
              '${l.channel} - ${l10n.affiliateClicks}: ${l.clicks}',
            ),
            trailing: IconButton(
              tooltip: l10n.copyLink,
              icon: const Icon(Icons.copy_rounded),
              onPressed: () => _copy(context, l.url),
            ),
          ),
        if (a.ideas.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          Text(l10n.ambassadorIdeas, style: context.text.titleSmall),
          for (final i in a.ideas)
            Card(
              margin: const EdgeInsets.only(top: AppSpacing.xs),
              child: ListTile(title: Text(i.title), subtitle: Text(i.body)),
            ),
        ],
      ],
    );
  }
}
