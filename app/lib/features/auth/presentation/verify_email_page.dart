import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../application/auth_controller.dart';
import 'auth_widgets.dart';

/// E-mail verification with the 6-digit code (skippable).
class VerifyEmailPage extends ConsumerStatefulWidget {
  const VerifyEmailPage({super.key, this.from});

  final String? from;

  @override
  ConsumerState<VerifyEmailPage> createState() => _VerifyEmailPageState();
}

class _VerifyEmailPageState extends ConsumerState<VerifyEmailPage> {
  final _form = GlobalKey<FormState>();
  final _code = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;
  bool _resending = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _leave() => context.go(safeReturnPath(widget.from) ?? AppRoutes.home);

  Future<void> _verify() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      final customer = await ref
          .read(authRepositoryProvider)
          .verifyEmail(_code.text.trim());
      ref.read(authControllerProvider.notifier).updateCustomer(customer);
      if (!mounted) return;
      context.showSnack(context.l10n.emailVerified);
      _leave();
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

  Future<void> _resend() async {
    setState(() => _resending = true);
    try {
      await ref.read(authRepositoryProvider).sendVerification();
      if (mounted) context.showSnack(context.l10n.codeSent);
    } on ApiException catch (e) {
      if (mounted) context.showSnack(errorMessage(context.l10n, e));
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final link = TextButton.styleFrom(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xs),
      textStyle: context.text.labelMedium,
    );
    return FormPageScaffold(
      title: l10n.verifyTitle,
      showBack: false,
      children: [
        FormLead(
          icon: Icons.check_rounded,
          tone: StateTone.mint,
          text: l10n.verifyBody,
        ),
        Form(
          key: _form,
          child: OtpField(
            label: l10n.codeLabel,
            controller: _code,
            errorText: _errors['code'],
            validator: (v) => (v ?? '').length == 6 ? null : l10n.invalidCode,
            onSubmitted: (_) => _verify(),
          ),
        ),
        const SizedBox(height: AppSpacing.mdl),
        AppButton(
          label: l10n.verifyAction,
          size: AppButtonSize.l,
          loading: _busy,
          onPressed: _verify,
        ),
        const SizedBox(height: AppSpacing.sm),
        Row(
          children: [
            Flexible(
              child: TextButton(
                style: link,
                onPressed: _resending ? null : _resend,
                child: Text(l10n.resendCode),
              ),
            ),
            const Spacer(),
            TextButton(
              style: link.copyWith(
                foregroundColor: WidgetStatePropertyAll(
                  context.colors.onSurfaceVariant,
                ),
              ),
              onPressed: _leave,
              child: Text(l10n.skipForNow),
            ),
          ],
        ),
      ],
    );
  }
}
