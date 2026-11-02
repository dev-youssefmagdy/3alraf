import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../application/auth_controller.dart';

/// Shared layout of the auth screens: logo, title, subtitle, content.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    required this.children,
    this.subtitle,
    this.showBack = true,
  });

  final String title;
  final String? subtitle;
  final List<Widget> children;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(automaticallyImplyLeading: showBack),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                Center(
                  child: Image.asset(
                    'assets/branding/splash_logo.png',
                    width: 120,
                    excludeFromSemantics: true,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                Text(
                  title,
                  style: context.text.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    subtitle!,
                    style: context.text.bodyMedium?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
                const SizedBox(height: AppSpacing.lg),
                ...children,
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Splits an [ApiException] into inline field errors (422) or a snackbar.
class FormErrors {
  const FormErrors([this.fields = const {}]);

  final Map<String, String> fields;

  String? operator [](String key) => fields[key];

  static FormErrors from(ApiException e) => FormErrors({
    for (final entry in e.errors.entries)
      if (entry.value.isNotEmpty) entry.key: entry.value.first,
  });
}

/// Returns a safe in-app return path from a `from` query value.
String? safeReturnPath(String? from) {
  if (from == null || !from.startsWith('/') || from.startsWith('//')) {
    return null;
  }
  return from;
}

/// "Continue with Google" (hidden when Google is not configured).
class GoogleSignInButton extends ConsumerStatefulWidget {
  const GoogleSignInButton({super.key, required this.onSignedIn});

  final VoidCallback onSignedIn;

  @override
  ConsumerState<GoogleSignInButton> createState() => _GoogleSignInButtonState();
}

class _GoogleSignInButtonState extends ConsumerState<GoogleSignInButton> {
  bool _busy = false;

  Future<void> _go() async {
    setState(() => _busy = true);
    try {
      final done = await ref
          .read(authControllerProvider.notifier)
          .loginWithGoogle();
      if (done && mounted) widget.onSignedIn();
    } on ApiException catch (e) {
      if (mounted) context.showSnack(errorMessage(context.l10n, e));
    } on Object {
      if (mounted) context.showSnack(context.l10n.googleFailed);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!ref.watch(googleIdTokenProvider).isAvailable) {
      return const SizedBox.shrink();
    }
    final l10n = context.l10n;
    return Column(
      children: [
        const SizedBox(height: AppSpacing.md),
        Row(
          children: [
            const Expanded(child: Divider()),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: Text(l10n.orDivider),
            ),
            const Expanded(child: Divider()),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(
          label: l10n.signInWithGoogle,
          icon: Icons.g_mobiledata_rounded,
          variant: AppButtonVariant.outlined,
          loading: _busy,
          onPressed: _go,
        ),
      ],
    );
  }
}

String? requiredValidator(BuildContext context, String? v) =>
    (v == null || v.trim().isEmpty) ? context.l10n.fieldRequired : null;

final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

String? emailValidator(BuildContext context, String? v) {
  if (v == null || v.trim().isEmpty) return context.l10n.fieldRequired;
  return _emailRe.hasMatch(v.trim()) ? null : context.l10n.invalidEmail;
}

String? passwordValidator(BuildContext context, String? v) {
  if (v == null || v.isEmpty) return context.l10n.fieldRequired;
  return v.length < 8 ? context.l10n.passwordTooShort : null;
}
