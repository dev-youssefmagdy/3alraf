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
import '../application/auth_controller.dart';
import 'auth_widgets.dart';

/// Step 1: e-mail -> 6-digit code.
class ForgotPasswordPage extends ConsumerStatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  ConsumerState<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends ConsumerState<ForgotPasswordPage> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      final email = _email.text.trim();
      await ref.read(authRepositoryProvider).forgotPassword(email);
      if (!mounted) return;
      context.showSnack(context.l10n.codeSent);
      context.pushReplacement(
        '${AppRoutes.resetPassword}?email=${Uri.encodeQueryComponent(email)}',
      );
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
      title: l10n.forgotTitle,
      subtitle: l10n.forgotBody,
      children: [
        Form(
          key: _form,
          child: AppTextField(
            label: l10n.email,
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.done,
            prefixIcon: Icons.mail_outline_rounded,
            errorText: _errors['email'],
            validator: (v) => emailValidator(context, v),
            onSubmitted: (_) => _submit(),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(label: l10n.sendCode, loading: _busy, onPressed: _submit),
      ],
    );
  }
}

/// Step 2: code + new password.
class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({super.key, this.email});

  final String? email;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  final _form = GlobalKey<FormState>();
  late final _email = TextEditingController(text: widget.email ?? '');
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_email, _code, _password, _confirm]) {
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
          .read(authRepositoryProvider)
          .resetPassword(
            email: _email.text.trim(),
            code: _code.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirm.text,
          );
      if (!mounted) return;
      context.showSnack(context.l10n.resetDone);
      context.go(AppRoutes.login);
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
    return AuthScaffold(
      title: l10n.resetTitle,
      subtitle: l10n.resetBody,
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
                prefixIcon: Icons.mail_outline_rounded,
                errorText: _errors['email'],
                validator: (v) => emailValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.codeLabel,
                controller: _code,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.pin_outlined,
                errorText: _errors['code'],
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(6),
                ],
                validator: (v) =>
                    (v ?? '').length == 6 ? null : l10n.invalidCode,
              ),
              gap,
              AppTextField(
                label: l10n.newPassword,
                controller: _password,
                obscure: true,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.newPassword],
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
        AppButton(label: l10n.resetAction, loading: _busy, onPressed: _submit),
      ],
    );
  }
}
