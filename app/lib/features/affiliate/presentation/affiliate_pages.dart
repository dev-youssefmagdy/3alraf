import 'dart:ui' as ui;

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

/// Localised label of a commission / payout status (raw value otherwise).
String _statusLabel(AppLocalizations l10n, String status) => switch (status) {
  'pending' || 'requested' => l10n.commissionPending,
  'approved' => l10n.commissionApproved,
  'paid' => l10n.commissionPaid,
  _ => status,
};

void _leave(BuildContext context) =>
    context.canPop() ? context.pop() : context.go(AppRoutes.affiliate);

Future<void> _copy(BuildContext context, String text) async {
  await Clipboard.setData(ClipboardData(text: text));
  if (context.mounted) {
    context.showSnack(context.l10n.linkCopied, tone: SnackTone.success);
  }
}

/// Display-only fraction of two server money strings (0 when unknown).
double _ratio(String part, String whole) {
  final p = double.tryParse(part) ?? 0;
  final w = double.tryParse(whole) ?? 0;
  return w <= 0 ? 0 : (p / w).clamp(0, 1).toDouble();
}

/// Entry: login when signed out, dashboard when signed in.
class AffiliateHubPage extends ConsumerWidget {
  const AffiliateHubPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(affiliateControllerProvider);
    if (!s.ready) {
      return Scaffold(
        appBar: brandAppBar(context, title: context.l10n.affiliateTitle),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    return s.signedIn ? const _DashboardView() : const _AffiliateLogin();
  }
}

/// Violet program hero (`63:4445`): sun sparkle circle, title, intro.
class _ProgramHero extends StatelessWidget {
  const _ProgramHero();

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final l10n = context.l10n;
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: x.violet,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -50,
            start: -40,
            child: DecorCircle(
              size: 140,
              color: Colors.white.withValues(alpha: 0.1),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.mdl),
            child: Column(
              children: [
                ExcludeSemantics(
                  child: Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: x.sun,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.auto_awesome_rounded,
                      color: AppColors.slate950,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.smd),
                Semantics(
                  header: true,
                  child: Text(
                    l10n.affiliateTitle,
                    textAlign: TextAlign.center,
                    style: context.text.headlineSmall?.copyWith(
                      color: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  l10n.affiliateIntro,
                  textAlign: TextAlign.center,
                  style: context.text.bodyMedium?.copyWith(
                    color: Colors.white.withValues(alpha: 0.9),
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
    return FormPageScaffold(
      title: l10n.affiliateTitle,
      appBar: brandAppBar(context, title: l10n.affiliateTitle),
      children: [
        const _ProgramHero(),
        const SizedBox(height: AppSpacing.mdl),
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
                ltr: true,
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
                errorText: _errors['password'],
                validator: (v) => requiredValidator(context, v),
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.mdl),
        AppButton(
          label: l10n.login,
          size: AppButtonSize.l,
          loading: _busy,
          onPressed: _submit,
        ),
        const SizedBox(height: AppSpacing.smd),
        AppButton(
          label: l10n.affiliateApply,
          variant: AppButtonVariant.outlined,
          size: AppButtonSize.l,
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
    final x = context.extra;
    const gap = SizedBox(height: AppSpacing.md);
    if (_sent) {
      return Scaffold(
        appBar: brandAppBar(context, title: l10n.affiliateApply),
        body: StateBlock(
          icon: Icons.mark_email_read_outlined,
          tone: StateTone.mint,
          title: l10n.affiliateApplied,
          message: l10n.affiliateAppliedBody,
          actionLabel: l10n.close,
          onAction: () => _leave(context),
        ),
      );
    }
    return FormPageScaffold(
      title: l10n.affiliateApply,
      appBar: brandAppBar(context, title: l10n.affiliateApply),
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: x.sun,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              ExcludeSemantics(
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.card_giftcard_rounded,
                    color: AppColors.slate950,
                    size: 22,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Text(
                  l10n.affiliateIntro,
                  style: context.text.titleSmall?.copyWith(
                    color: AppColors.slate950,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.mdl),
        Form(
          key: _form,
          child: Column(
            children: [
              AppTextField(
                label: l10n.fullName,
                controller: _name,
                textInputAction: TextInputAction.next,
                errorText: _errors['name'],
                validator: (v) => requiredValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.email,
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                ltr: true,
                errorText: _errors['email'],
                validator: (v) => emailValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.phoneOptional,
                controller: _phone,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                ltr: true,
                errorText: _errors['phone'],
              ),
              gap,
              AppTextField(
                label: l10n.password,
                controller: _password,
                obscure: true,
                textInputAction: TextInputAction.next,
                errorText: _errors['password'],
                validator: (v) => passwordValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.confirmPassword,
                controller: _confirm,
                obscure: true,
                textInputAction: TextInputAction.done,
                errorText: _errors['password_confirmation'],
                validator: (v) =>
                    v == _password.text ? null : l10n.passwordMismatch,
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.mdl),
        AppButton(
          label: l10n.affiliateApply,
          size: AppButtonSize.l,
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
    final x = context.extra;
    final dash = ref.watch(affiliateDashboardProvider);
    final currency = ref.watch(currencyLabelProvider);
    final store = ref.watch(storeProfileProvider);
    String money(String v) => l10n.money(v, currency);
    return Scaffold(
      appBar: brandAppBar(context, title: l10n.affiliateTitle),
      body: dash.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: const [
            AppShimmer(height: 32, width: 180, radius: AppRadius.xs),
            SizedBox(height: AppSpacing.md),
            AppShimmer(height: 96, radius: AppRadius.lg),
            SizedBox(height: AppSpacing.smd),
            AppShimmer(height: 200, radius: AppRadius.md),
            SizedBox(height: AppSpacing.smd),
            AppShimmer(height: 160, radius: AppRadius.md),
          ],
        ),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(affiliateDashboardProvider),
        ),
        data: (d) {
          final st = d.stats;
          final bonus = st.bonus;
          const gap = SizedBox(height: AppSpacing.smd);
          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(affiliateDashboardProvider);
              await ref.read(affiliateDashboardProvider.future);
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutter,
                AppSpacing.gutter,
                AppSpacing.gutter,
                AppSpacing.xl,
              ),
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Semantics(
                        header: true,
                        child: Text(
                          l10n.affiliateWelcome(d.affiliate.name),
                          style: context.text.headlineMedium,
                        ),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: () => ref
                          .read(affiliateControllerProvider.notifier)
                          .logout(),
                      style: TextButton.styleFrom(
                        foregroundColor: context.colors.error,
                      ),
                      icon: const Icon(Icons.logout_rounded, size: 18),
                      label: Text(l10n.logout),
                    ),
                  ],
                ),
                if (d.affiliate.status != 'approved')
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: StatusChip(
                      label: _statusLabel(l10n, d.affiliate.status),
                      tone: _tone(d.affiliate.status),
                    ),
                  ),
                const SizedBox(height: AppSpacing.md),
                if (st.tier != null) ...[
                  _TierCard(stats: st, money: money),
                  gap,
                ],
                _StatGrid(
                  cells: [
                    _StatCell(
                      l10n.affiliateClicks,
                      '${st.clicks}',
                      x.brandSoft,
                      context.colors.primary,
                    ),
                    _StatCell(
                      l10n.affiliateOrders,
                      '${st.orders}',
                      x.mintSoft,
                      x.mintStrong,
                    ),
                    _StatCell(
                      l10n.affiliateConversion,
                      '${st.conversionPercent}%',
                      x.sunSoft,
                      AppColors.slate950,
                    ),
                    _StatCell(
                      l10n.affiliateSales,
                      money(st.lifetimeSales),
                      x.violetSoft,
                      x.violetText,
                    ),
                  ],
                ),
                gap,
                _CommissionsCard(commissions: st.commissions, money: money),
                if (bonus != null && bonus.enabled) ...[
                  gap,
                  _BonusCard(bonus: bonus, money: money),
                ],
                gap,
                AppListGroup(
                  children: [
                    AppListTile(
                      icon: Icons.share_outlined,
                      title: l10n.affiliateProducts,
                      onTap: () => context.push(AppRoutes.affiliateProducts),
                    ),
                    AppListTile(
                      icon: Icons.payments_outlined,
                      title: l10n.affiliatePayouts,
                      onTap: () => context.push(AppRoutes.affiliatePayouts),
                    ),
                    if (store?.features['ambassadors'] ?? false)
                      AppListTile(
                        icon: Icons.auto_awesome_outlined,
                        title: l10n.ambassadorTitle,
                        onTap: () =>
                            context.push(AppRoutes.affiliateAmbassador),
                      ),
                  ],
                ),
                if (d.recentCommissions.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.mdl),
                  Semantics(
                    header: true,
                    child: Text(
                      l10n.affiliateRecentCommissions,
                      style: context.text.headlineSmall,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  SectionCard(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.md,
                      vertical: AppSpacing.xs,
                    ),
                    child: Column(
                      children: [
                        for (final (i, c) in d.recentCommissions.indexed) ...[
                          if (i > 0) const Divider(height: 1),
                          _MoneyRow(
                            title: c.order ?? '-',
                            subtitle: '${c.percent}%',
                            amount: money(c.amount),
                            status: c.status,
                          ),
                        ],
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

/// Violet tier card: star + "tier - n%", a sun progress bar toward the
/// next tier and the "next: … at …" line.
class _TierCard extends StatelessWidget {
  const _TierCard({required this.stats, required this.money});

  final AffiliateStats stats;
  final String Function(String) money;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    final tier = stats.tier!;
    final next = stats.nextTier;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: x.violet,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.star_rounded, color: Colors.white, size: 20),
              const SizedBox(width: AppSpacing.xs),
              Expanded(
                child: Text(
                  l10n.affiliateTier(tier.name, tier.percent),
                  style: context.text.titleMedium?.copyWith(
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
          if (next != null) ...[
            const SizedBox(height: AppSpacing.sm),
            AppProgressBar(
              value: _ratio(stats.lifetimeSales, next.minSales),
              color: x.sun,
              trackColor: Colors.white.withValues(alpha: 0.9),
              semanticLabel: l10n.affiliateTier(tier.name, tier.percent),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              l10n.affiliateNextTier(next.name, money(next.minSales)),
              style: context.text.bodySmall?.copyWith(
                color: Colors.white.withValues(alpha: 0.9),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _StatCell {
  const _StatCell(this.label, this.value, this.background, this.foreground);

  final String label;
  final String value;
  final Color background;
  final Color foreground;
}

/// Stat cards (`21:2040`) in a 2×2 grid; rows grow with the text scale.
class _StatGrid extends StatelessWidget {
  const _StatGrid({required this.cells});

  final List<_StatCell> cells;

  @override
  Widget build(BuildContext context) {
    Widget card(_StatCell c) => Container(
      padding: const EdgeInsets.all(AppSpacing.smd),
      decoration: BoxDecoration(
        color: c.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            c.label,
            style: context.text.labelSmall?.copyWith(color: c.foreground),
          ),
          const SizedBox(height: AppSpacing.xs),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              c.value,
              maxLines: 1,
              style: context.text.headlineMedium?.copyWith(color: c.foreground),
            ),
          ),
        ],
      ),
    );
    final rows = <Widget>[];
    for (var i = 0; i < cells.length; i += 2) {
      if (i > 0) rows.add(const SizedBox(height: AppSpacing.smd));
      rows.add(
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(child: card(cells[i])),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: i + 1 < cells.length
                    ? card(cells[i + 1])
                    : const SizedBox(),
              ),
            ],
          ),
        ),
      );
    }
    return Column(children: rows);
  }
}

/// "العمولات" card: pending (warning) · approved (success) · paid, then the
/// violet "available" block with the Sun payout CTA.
class _CommissionsCard extends StatelessWidget {
  const _CommissionsCard({required this.commissions, required this.money});

  final Map<String, String> commissions;
  final String Function(String) money;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    Widget tile(String key, Color bg, Color fg) => Container(
      padding: const EdgeInsets.all(AppSpacing.smd),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _commissionLabel(l10n, key),
            style: context.text.bodySmall?.copyWith(color: fg),
          ),
          Text(
            money(commissions[key] ?? '0.00'),
            style: context.textX.priceM.copyWith(color: fg),
          ),
        ],
      ),
    );
    return SectionCard(
      title: l10n.affiliateCommissions,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: tile('pending', x.warningSoft, x.warningText)),
                const SizedBox(width: AppSpacing.sm),
                Expanded(child: tile('approved', x.successSoft, x.successText)),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          tile('paid', x.brandSoft, context.colors.onSurface),
          const SizedBox(height: AppSpacing.smd),
          Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              color: x.violet,
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: Wrap(
              spacing: AppSpacing.smd,
              runSpacing: AppSpacing.sm,
              alignment: WrapAlignment.spaceBetween,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.commissionAvailable,
                      style: context.text.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.9),
                      ),
                    ),
                    Text(
                      money(commissions['available'] ?? '0.00'),
                      style: context.textX.priceL.copyWith(color: Colors.white),
                    ),
                  ],
                ),
                AppButton(
                  label: l10n.payoutRequest,
                  variant: AppButtonVariant.sun,
                  size: AppButtonSize.s,
                  expand: false,
                  onPressed: () => context.push(AppRoutes.affiliatePayouts),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Sales bonus card: the rule line, a mint progress bar toward the next
/// bonus, "current / threshold" and the earned total.
class _BonusCard extends StatelessWidget {
  const _BonusCard({required this.bonus, required this.money});

  final AffiliateBonus bonus;
  final String Function(String) money;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    return SectionCard(
      key: const ValueKey('affiliate-bonus'),
      title: l10n.affiliateBonusTitle,
      trailing: Icon(Icons.card_giftcard_rounded, color: x.mintStrong),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l10n.affiliateBonusRule(
              money(bonus.amount),
              money(bonus.threshold),
            ),
            style: context.text.bodySmall?.copyWith(
              color: context.colors.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppProgressBar(
            value: bonus.percent / 100,
            color: x.mint,
            semanticLabel: l10n.affiliateBonusTitle,
          ),
          const SizedBox(height: AppSpacing.xs),
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            spacing: AppSpacing.sm,
            children: [
              Text(
                '${bonus.towardNext} / ${money(bonus.threshold)}',
                textDirection: TextDirection.ltr,
                style: context.text.labelSmall?.copyWith(color: x.mintStrong),
              ),
              Text(
                l10n.affiliateBonusEarned(money(bonus.earnedTotal)),
                style: context.text.labelSmall,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// One money row: title + subtitle at the start, amount + status chip at
/// the end (recent commissions, payout history).
class _MoneyRow extends StatelessWidget {
  const _MoneyRow({
    super.key,
    required this.title,
    required this.amount,
    required this.status,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final String amount;
  final String status;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: AppSpacing.smd),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: context.text.titleSmall),
              if (subtitle != null && subtitle!.isNotEmpty)
                Text(
                  subtitle!,
                  style: context.text.bodySmall?.copyWith(
                    color: context.extra.textMuted,
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        StatusChip(
          label: _statusLabel(context.l10n, status),
          tone: _tone(status),
        ),
        const SizedBox(width: AppSpacing.sm),
        Text(amount, style: context.text.titleSmall),
      ],
    ),
  );
}

String _commissionLabel(AppLocalizations l10n, String key) => switch (key) {
  'pending' => l10n.commissionPending,
  'approved' => l10n.commissionApproved,
  'paid' => l10n.commissionPaid,
  _ => l10n.commissionAvailable,
};

class AffiliateProductsPage extends ConsumerWidget {
  const AffiliateProductsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final products = ref.watch(affiliateProductsProvider);
    final currency = ref.watch(currencyLabelProvider);
    return Scaffold(
      appBar: brandAppBar(context, title: l10n.affiliateProducts),
      body: products.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: [
            for (var i = 0; i < 3; i++) ...[
              const AppShimmer(height: 180, radius: AppRadius.md),
              const SizedBox(height: AppSpacing.smd),
            ],
          ],
        ),
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
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.gutter,
                    AppSpacing.gutter,
                    AppSpacing.gutter,
                    AppSpacing.xl,
                  ),
                  itemCount: list.length + 1,
                  itemBuilder: (context, i) {
                    if (i == 0) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: Text(
                          l10n.affiliateProductsIntro,
                          style: context.text.bodyMedium?.copyWith(
                            color: context.colors.onSurfaceVariant,
                          ),
                        ),
                      );
                    }
                    final p = list[i - 1];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.smd),
                      child: AffiliateProductCard(
                        key: ValueKey('aff:${p.id}'),
                        product: p,
                        currency: currency,
                      ),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

/// Products & links card (`63:4694`): thumb, name, price; the read-only
/// link (always LTR) with a Pop "copy"; "share"; and "QR code", which
/// expands a violet panel with the QR and "save image". There is no
/// barcode anywhere in the app.
class AffiliateProductCard extends StatefulWidget {
  const AffiliateProductCard({
    super.key,
    required this.product,
    required this.currency,
  });

  final AffiliateProduct product;
  final String currency;

  @override
  State<AffiliateProductCard> createState() => _AffiliateProductCardState();
}

class _AffiliateProductCardState extends State<AffiliateProductCard> {
  bool _qr = false;
  bool _saving = false;

  Future<void> _saveQr() async {
    final p = widget.product;
    setState(() => _saving = true);
    try {
      const side = 720.0;
      const pad = 48.0;
      final painter = QrPainter(
        data: p.link,
        version: QrVersions.auto,
        gapless: true,
      );
      final recorder = ui.PictureRecorder();
      final canvas = Canvas(recorder)
        ..drawRect(
          const Rect.fromLTWH(0, 0, side, side),
          Paint()..color = Colors.white,
        )
        ..translate(pad, pad);
      painter.paint(canvas, const Size.square(side - pad * 2));
      final image = await recorder.endRecording().toImage(
        side.toInt(),
        side.toInt(),
      );
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      if (bytes == null) return;
      await SharePlus.instance.share(
        ShareParams(
          files: [
            XFile.fromData(
              bytes.buffer.asUint8List(),
              mimeType: 'image/png',
              name: 'qr-${p.slug}.png',
            ),
          ],
          fileNameOverrides: ['qr-${p.slug}.png'],
        ),
      );
    } on Object {
      if (mounted) {
        context.showSnack(context.l10n.errorGeneric, tone: SnackTone.error);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final p = widget.product;
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              NetworkImageX(
                url: p.image,
                width: 64,
                height: 64,
                radius: AppRadius.sm,
                cacheWidth: 128,
                semanticLabel: p.name,
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: context.text.titleSmall,
                    ),
                    PriceText(amount: p.price, currency: widget.currency),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.smd),
          Row(
            children: [
              Expanded(
                child: Semantics(
                  label: l10n.copyLink,
                  value: p.link,
                  excludeSemantics: true,
                  child: Container(
                    height: 44,
                    alignment: AlignmentDirectional.centerStart,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    decoration: BoxDecoration(
                      color: c.surfaceContainerHighest,
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                    child: Text(
                      p.link.replaceFirst(RegExp(r'^https?://'), ''),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textDirection: TextDirection.ltr,
                      style: context.text.bodyMedium,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              AppButton(
                label: l10n.copy,
                icon: Icons.copy_rounded,
                variant: AppButtonVariant.pop,
                expand: false,
                onPressed: () => _copy(context, p.link),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: AppButton(
                  label: l10n.share,
                  icon: Icons.share_outlined,
                  variant: AppButtonVariant.outlined,
                  dense: true,
                  onPressed: () => SharePlus.instance.share(
                    ShareParams(text: '${p.name}\n${p.link}'),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Semantics(
                  expanded: _qr,
                  child: AppButton(
                    label: l10n.affiliateQr,
                    icon: Icons.qr_code_2_rounded,
                    variant: AppButtonVariant.outlined,
                    dense: true,
                    onPressed: () => setState(() => _qr = !_qr),
                  ),
                ),
              ),
            ],
          ),
          if (_qr) ...[
            const SizedBox(height: AppSpacing.smd),
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: x.violetSoft,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.smd),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                    child: SizedBox.square(
                      dimension: 168,
                      child: Semantics(
                        label: l10n.affiliateQr,
                        image: true,
                        child: QrImageView(
                          data: p.link,
                          backgroundColor: Colors.white,
                          padding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    l10n.affiliateQrHint,
                    textAlign: TextAlign.center,
                    style: context.text.bodySmall?.copyWith(
                      color: x.violetText,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  AppButton(
                    label: l10n.affiliateQrSave,
                    icon: Icons.download_rounded,
                    variant: AppButtonVariant.outlined,
                    size: AppButtonSize.s,
                    expand: false,
                    loading: _saving,
                    onPressed: _saveQr,
                  ),
                ],
              ),
            ),
          ],
        ],
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
      if (mounted) {
        context.showSnack(l10n.payoutRequested, tone: SnackTone.success);
      }
    } on ApiException catch (e) {
      if (mounted) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    final payouts = ref.watch(affiliatePayoutsProvider);
    final currency = ref.watch(currencyLabelProvider);
    final available = ref
        .watch(affiliateDashboardProvider)
        .asData
        ?.value
        .stats
        .commissions['available'];
    return Scaffold(
      appBar: brandAppBar(context, title: l10n.affiliatePayouts),
      body: payouts.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: const [
            AppShimmer(height: 150, radius: AppRadius.lg),
            SizedBox(height: AppSpacing.md),
            AppShimmer(height: 180, radius: AppRadius.md),
          ],
        ),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(affiliatePayoutsProvider),
        ),
        data: (list) => ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.gutter,
            AppSpacing.gutter,
            AppSpacing.gutter,
            AppSpacing.xl,
          ),
          children: [
            Container(
              padding: const EdgeInsets.all(AppSpacing.mdl),
              decoration: BoxDecoration(
                color: x.violet,
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (available != null)
                    Text(
                      l10n.payoutAvailable(
                        l10n.money(available, currency),
                      ),
                      style: context.text.displaySmall?.copyWith(
                        color: Colors.white,
                      ),
                    ),
                  const SizedBox(height: AppSpacing.md),
                  AppButton(
                    label: l10n.payoutRequest,
                    icon: Icons.payments_outlined,
                    variant: AppButtonVariant.sun,
                    size: AppButtonSize.l,
                    loading: _busy,
                    onPressed: _request,
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.mdl),
            Semantics(
              header: true,
              child: Text(
                l10n.payoutHistory,
                style: context.text.headlineSmall,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            if (list.isEmpty)
              SectionCard(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
                  child: Text(
                    l10n.payoutsEmpty,
                    textAlign: TextAlign.center,
                    style: context.text.bodyMedium?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                  ),
                ),
              )
            else
              SectionCard(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.md,
                  vertical: AppSpacing.xs,
                ),
                child: Column(
                  children: [
                    for (final (i, p) in list.indexed) ...[
                      if (i > 0) const Divider(height: 1),
                      _MoneyRow(
                        key: ValueKey('payout:${p.id}'),
                        title: formatDate(p.requestedAt).split(' ').first,
                        subtitle: p.note,
                        amount: l10n.money(p.amount, currency),
                        status: p.status,
                      ),
                    ],
                  ],
                ),
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
      appBar: brandAppBar(context, title: l10n.ambassadorTitle),
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
    final x = context.extra;
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.gutter),
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: x.sun,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              ExcludeSemantics(
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.campaign_outlined,
                    color: AppColors.slate950,
                    size: 22,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Text(
                  l10n.ambassadorIntro,
                  style: context.text.titleSmall?.copyWith(
                    color: AppColors.slate950,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        SectionCard(
          padding: const EdgeInsets.all(AppSpacing.mdl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
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
                title: Text(
                  l10n.ambassadorTerms,
                  style: context.text.bodyMedium,
                ),
                value: _terms,
                onChanged: (v) => setState(() => _terms = v ?? false),
              ),
              const SizedBox(height: AppSpacing.sm),
              AppButton(
                label: l10n.ambassadorJoin,
                size: AppButtonSize.l,
                loading: _busy,
                onPressed: _terms && _disclosure.text.trim().isNotEmpty
                    ? () => _run(
                        () => ref
                            .read(affiliateRepositoryProvider)
                            .joinAmbassador(
                              disclosure: _disclosure.text.trim(),
                            ),
                      )
                    : null,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _enrolled(BuildContext context, AmbassadorPortal a) {
    final l10n = context.l10n;
    final x = context.extra;
    final products =
        ref.watch(affiliateProductsProvider).asData?.value ??
        const <AffiliateProduct>[];
    final channel = _channel ?? (a.channels.isEmpty ? null : a.channels.first);
    Widget metric(String label, int value) => Expanded(
      child: Column(
        children: [
          Text(
            '$value',
            style: context.text.headlineMedium?.copyWith(color: Colors.white),
          ),
          Text(
            label,
            textAlign: TextAlign.center,
            style: context.text.bodySmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
            ),
          ),
        ],
      ),
    );
    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.gutter,
        AppSpacing.gutter,
        AppSpacing.xl,
      ),
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: x.violet,
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l10n.ambassadorReport(a.report['window_days'] ?? 30),
                style: context.text.titleMedium?.copyWith(color: Colors.white),
              ),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: [
                  metric(l10n.ambassadorValid, a.report['valid_orders'] ?? 0),
                  metric(
                    l10n.ambassadorPending,
                    a.report['pending_orders'] ?? 0,
                  ),
                  metric(
                    l10n.ambassadorExcluded,
                    a.report['excluded_orders'] ?? 0,
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.smd),
        SectionCard(
          title: l10n.ambassadorNewLink,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
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
              const SizedBox(height: AppSpacing.smd),
              if (a.channels.isNotEmpty)
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
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
                  child: Text(
                    _error!,
                    style: context.text.bodySmall?.copyWith(
                      color: context.colors.error,
                    ),
                  ),
                ),
              const SizedBox(height: AppSpacing.smd),
              AppButton(
                label: l10n.ambassadorCreateLink,
                variant: AppButtonVariant.pop,
                loading: _busy,
                onPressed: _productId == null || channel == null
                    ? null
                    : () => _run(
                        () => ref
                            .read(affiliateRepositoryProvider)
                            .createLink(
                              productId: _productId!,
                              channel: channel,
                            )
                            .then((_) {}),
                      ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.smd),
        SectionCard(
          title: l10n.ambassadorLinks,
          child: a.links.isEmpty
              ? Text(
                  l10n.ambassadorNoLinks,
                  style: context.text.bodyMedium?.copyWith(
                    color: context.colors.onSurfaceVariant,
                  ),
                )
              : Column(
                  children: [
                    for (final (i, l) in a.links.indexed) ...[
                      if (i > 0) const Divider(height: 1),
                      Padding(
                        key: ValueKey('link:${l.id}'),
                        padding: const EdgeInsets.symmetric(
                          vertical: AppSpacing.xs,
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    l.url,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    textDirection: TextDirection.ltr,
                                    style: context.text.titleSmall,
                                  ),
                                  Text(
                                    '${l.channel} · ${l10n.affiliateClicks}: ${l.clicks}',
                                    style: context.text.bodySmall?.copyWith(
                                      color: context.extra.textMuted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              tooltip: l10n.copyLink,
                              color: x.pop,
                              icon: const Icon(Icons.copy_rounded),
                              onPressed: () => _copy(context, l.url),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
        ),
        if (a.ideas.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.mdl),
          Semantics(
            header: true,
            child: Text(
              l10n.ambassadorIdeas,
              style: context.text.headlineSmall,
            ),
          ),
          for (final i in a.ideas)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.sm),
              child: NoticeCard(
                icon: Icons.lightbulb_outline_rounded,
                tone: StateTone.sun,
                title: i.title,
                text: i.body,
              ),
            ),
        ],
      ],
    );
  }
}
