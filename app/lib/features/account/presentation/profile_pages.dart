import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/presentation/auth_widgets.dart';

/// Back to where the form was opened from (or the account tab after a deep
/// link opened it directly).
void _leave(BuildContext context) =>
    context.canPop() ? context.pop() : context.go(AppRoutes.account);

/// Shared scaffold for the account forms: scrollable, centred, max width.
class _FormScaffold extends StatelessWidget {
  const _FormScaffold({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(title)),
    body: SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: children,
          ),
        ),
      ),
    ),
  );
}

/// Edit name / e-mail / phone (`PUT auth/me`). Changing the e-mail needs the
/// current password, so that field only appears when the e-mail was edited.
class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  final _current = TextEditingController();
  late final String _originalEmail;
  FormErrors _errors = const FormErrors();
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final c = ref.read(authControllerProvider).customer;
    _originalEmail = c?.email ?? '';
    _name = TextEditingController(text: c?.name ?? '');
    _email = TextEditingController(text: c?.email ?? '')
      ..addListener(() => setState(() {}));
    _phone = TextEditingController(text: c?.phoneLocal ?? '');
  }

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _current]) {
      c.dispose();
    }
    super.dispose();
  }

  bool get _emailChanged => _email.text.trim() != _originalEmail;

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final l10n = context.l10n;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      final c = ref.read(authControllerProvider).customer;
      await ref
          .read(authControllerProvider.notifier)
          .updateProfile(
            name: _name.text.trim(),
            email: _email.text.trim(),
            phone: _phone.text.trim(),
            phoneCountryCode: c?.phoneCountryCode,
            currentPassword: _emailChanged ? _current.text : null,
          );
      if (!mounted) return;
      context.showSnack(l10n.profileSaved);
      _leave(context);
    } on ApiException catch (e) {
      if (!mounted) return;
      final fields = FormErrors.from(e);
      setState(() => _errors = fields);
      if (fields.fields.isEmpty) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    const gap = SizedBox(height: AppSpacing.md);
    return _FormScaffold(
      title: l10n.profileTitle,
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
              if (_emailChanged) ...[
                gap,
                Text(
                  l10n.profileEmailChangeHint,
                  style: context.text.bodySmall,
                ),
                const SizedBox(height: AppSpacing.sm),
                AppTextField(
                  label: l10n.currentPassword,
                  controller: _current,
                  obscure: true,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.password],
                  prefixIcon: Icons.lock_outline_rounded,
                  errorText: _errors['current_password'],
                  validator: (v) => requiredValidator(context, v),
                  onSubmitted: (_) => _save(),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(label: l10n.save, loading: _busy, onPressed: _save),
      ],
    );
  }
}

/// Change password in-app (`PUT auth/me` with the password trio).
class ChangePasswordPage extends ConsumerStatefulWidget {
  const ChangePasswordPage({super.key});

  @override
  ConsumerState<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends ConsumerState<ChangePasswordPage> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_current, _new, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final l10n = context.l10n;
    final me = ref.read(authControllerProvider).customer;
    if (me == null) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .updateProfile(
            name: me.name,
            email: me.email,
            currentPassword: _current.text,
            password: _new.text,
            passwordConfirmation: _confirm.text,
          );
      if (!mounted) return;
      context.showSnack(l10n.passwordChanged);
      _leave(context);
    } on ApiException catch (e) {
      if (!mounted) return;
      final fields = FormErrors.from(e);
      setState(() => _errors = fields);
      if (fields.fields.isEmpty) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    const gap = SizedBox(height: AppSpacing.md);
    return _FormScaffold(
      title: l10n.changePasswordTitle,
      children: [
        Form(
          key: _form,
          child: Column(
            children: [
              AppTextField(
                label: l10n.currentPassword,
                controller: _current,
                obscure: true,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.password],
                prefixIcon: Icons.lock_outline_rounded,
                errorText: _errors['current_password'],
                validator: (v) => requiredValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.newPassword,
                controller: _new,
                obscure: true,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.newPassword],
                prefixIcon: Icons.lock_reset_rounded,
                errorText: _errors['password'],
                validator: (v) => passwordValidator(context, v),
              ),
              gap,
              AppTextField(
                label: l10n.confirmPassword,
                controller: _confirm,
                obscure: true,
                textInputAction: TextInputAction.done,
                prefixIcon: Icons.lock_reset_rounded,
                errorText: _errors['password_confirmation'],
                validator: (v) => v == _new.text ? null : l10n.passwordMismatch,
                onSubmitted: (_) => _save(),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(label: l10n.save, loading: _busy, onPressed: _save),
      ],
    );
  }
}

/// Irreversible account deletion (App Store / Play requirement).
class DeleteAccountPage extends ConsumerStatefulWidget {
  const DeleteAccountPage({super.key});

  @override
  ConsumerState<DeleteAccountPage> createState() => _DeleteAccountPageState();
}

class _DeleteAccountPageState extends ConsumerState<DeleteAccountPage> {
  final _password = TextEditingController();
  FormErrors _errors = const FormErrors();
  bool _noPassword = false;
  bool _busy = false;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    final l10n = context.l10n;
    if (!_noPassword && _password.text.isEmpty) {
      setState(() => _errors = FormErrors({'password': l10n.fieldRequired}));
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l10n.deleteAccountConfirmTitle),
        content: Text(l10n.deleteAccountConfirmBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(l10n.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            style: TextButton.styleFrom(foregroundColor: c.colors.error),
            child: Text(l10n.deleteAccountAction),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() {
      _busy = true;
      _errors = const FormErrors();
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .deleteAccount(
            password: _noPassword ? null : _password.text,
            confirm: _noPassword,
          );
      if (!mounted) return;
      context.go(AppRoutes.home);
      context.showSnack(l10n.deleteAccountDone);
    } on ApiException catch (e) {
      if (!mounted) return;
      final fields = FormErrors.from(e);
      setState(() => _errors = fields);
      if (fields.fields.isEmpty) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return _FormScaffold(
      title: l10n.deleteAccountTitle,
      children: [
        Icon(
          Icons.warning_amber_rounded,
          size: 56,
          color: context.colors.error,
          semanticLabel: l10n.deleteAccountTitle,
        ),
        const SizedBox(height: AppSpacing.md),
        Text(l10n.deleteAccountWarning, style: context.text.bodyLarge),
        const SizedBox(height: AppSpacing.lg),
        if (!_noPassword)
          AppTextField(
            label: l10n.password,
            controller: _password,
            obscure: true,
            textInputAction: TextInputAction.done,
            prefixIcon: Icons.lock_outline_rounded,
            errorText: _errors['password'],
            onSubmitted: (_) => _delete(),
          ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l10n.deleteAccountNoPassword),
          value: _noPassword,
          onChanged: (v) => setState(() => _noPassword = v),
        ),
        const SizedBox(height: AppSpacing.md),
        FilledButton.icon(
          style: FilledButton.styleFrom(
            backgroundColor: context.colors.error,
            foregroundColor: context.colors.onError,
            minimumSize: const Size.fromHeight(48),
          ),
          onPressed: _busy ? null : _delete,
          icon: _busy
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.delete_forever_rounded),
          label: Text(l10n.deleteAccountAction),
        ),
      ],
    );
  }
}
