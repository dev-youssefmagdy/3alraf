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

class RegisterPage extends ConsumerStatefulWidget {
  const RegisterPage({super.key, this.from});

  final String? from;

  @override
  ConsumerState<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends ConsumerState<RegisterPage> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;

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
          .read(authControllerProvider.notifier)
          .register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirm.text,
            phone: _phone.text.trim(),
          );
      if (!mounted) return;
      final from = safeReturnPath(widget.from);
      context.go(
        from == null
            ? AppRoutes.verifyEmail
            : '${AppRoutes.verifyEmail}?from=${Uri.encodeQueryComponent(from)}',
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

  void _signedInWithGoogle() =>
      context.go(safeReturnPath(widget.from) ?? AppRoutes.home);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    const gap = SizedBox(height: AppSpacing.md);
    return AuthScaffold(
      title: l10n.register,
      subtitle: l10n.registerSubtitle,
      children: [
        Form(
          key: _form,
          child: Column(
            children: [
              AppTextField(
                label: l10n.fullName,
                controller: _name,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.name],
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
                autofillHints: const [AutofillHints.email],
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
                autofillHints: const [AutofillHints.telephoneNumber],
                prefixIcon: Icons.phone_outlined,
                errorText: _errors['phone'],
              ),
              gap,
              AppTextField(
                label: l10n.password,
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
        gap,
        AppButton(label: l10n.register, loading: _busy, onPressed: _submit),
        GoogleSignInButton(onSignedIn: _signedInWithGoogle),
        gap,
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(l10n.haveAccountPrompt),
            TextButton(
              onPressed: () => context.pushReplacement(AppRoutes.login),
              child: Text(l10n.login),
            ),
          ],
        ),
      ],
    );
  }
}
