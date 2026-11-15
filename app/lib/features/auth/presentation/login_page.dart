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

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key, this.from, this.expired = false});

  /// Where to return after signing in (guest -> login -> back).
  final String? from;
  final bool expired;

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
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

  void _done() => context.go(safeReturnPath(widget.from) ?? AppRoutes.home);

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .login(_email.text.trim(), _password.text);
      if (mounted) _done();
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
    final qs = widget.from == null
        ? ''
        : '?from=${Uri.encodeQueryComponent(widget.from!)}';
    return AuthScaffold(
      title: l10n.login,
      subtitle: widget.expired ? l10n.sessionExpiredNotice : l10n.loginSubtitle,
      footer: Center(
        child: TextButton(
          onPressed: () => context.go(AppRoutes.home),
          style: TextButton.styleFrom(foregroundColor: Colors.white),
          child: Text(l10n.continueAsGuest),
        ),
      ),
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
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: TextButton(
            onPressed: () => context.push(AppRoutes.forgotPassword),
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
            ),
            child: Text(l10n.forgotPasswordLink),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        AppButton(
          label: l10n.login,
          size: AppButtonSize.l,
          loading: _busy,
          onPressed: _submit,
        ),
        GoogleSignInButton(onSignedIn: _done),
        const SizedBox(height: AppSpacing.sm),
        AuthSwitchLine(
          prompt: l10n.noAccountPrompt,
          action: l10n.register,
          onTap: () => context.pushReplacement('${AppRoutes.register}$qs'),
        ),
      ],
    );
  }
}
