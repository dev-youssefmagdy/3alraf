import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../application/auth_controller.dart';

/// Login / Register layout (`50:598`, `50:669`): a brand-800 backdrop with
/// decorative blobs, a 96 white logo circle, then a white auth card
/// (radius 24, padding 20, `shadowOverlay`) holding the H1 [title], the
/// [subtitle] and [children]. [footer] sits under the card (e.g. the white
/// "continue as guest" link).
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    required this.children,
    this.subtitle,
    this.showBack = true,
    this.footer,
  });

  final String title;
  final String? subtitle;
  final List<Widget> children;
  final bool showBack;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final c = context.colors;
    final canPop = showBack && Navigator.of(context).canPop();
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: x.brandStrong,
        body: Stack(
          children: [
            PositionedDirectional(
              top: -70,
              end: -80,
              child: DecorCircle(
                size: 240,
                color: x.pop.withValues(alpha: 0.55),
              ),
            ),
            PositionedDirectional(
              top: 170,
              start: -70,
              child: DecorCircle(
                size: 150,
                color: x.gold.withValues(alpha: 0.7),
              ),
            ),
            SafeArea(
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.gutter,
                      AppSpacing.sm,
                      AppSpacing.gutter,
                      AppSpacing.lg,
                    ),
                    children: [
                      SizedBox(
                        height: 44,
                        child: canPop
                            ? Align(
                                alignment: AlignmentDirectional.centerStart,
                                child: BackButton(color: Colors.white),
                              )
                            : null,
                      ),
                      Center(
                        child: LogoCircle(semanticLabel: context.l10n.appName),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Container(
                        padding: const EdgeInsets.all(AppSpacing.mdl),
                        decoration: BoxDecoration(
                          color: c.surfaceContainerLowest,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          boxShadow: AppShadows.overlay,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Semantics(
                              header: true,
                              child: Text(
                                title,
                                style: context.text.headlineMedium,
                              ),
                            ),
                            if (subtitle != null) ...[
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                subtitle!,
                                style: context.text.bodyMedium?.copyWith(
                                  color: c.onSurfaceVariant,
                                ),
                              ),
                            ],
                            const SizedBox(height: AppSpacing.mdl),
                            ...children,
                          ],
                        ),
                      ),
                      if (footer != null) ...[
                        const SizedBox(height: AppSpacing.sm),
                        footer!,
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// "ليس لديك حساب؟ إنشاء حساب" switch line under the auth card buttons.
class AuthSwitchLine extends StatelessWidget {
  const AuthSwitchLine({
    super.key,
    required this.prompt,
    required this.action,
    required this.onTap,
  });

  final String prompt;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Wrap(
    alignment: WrapAlignment.center,
    crossAxisAlignment: WrapCrossAlignment.center,
    children: [
      Text(
        prompt,
        style: context.text.bodyMedium?.copyWith(
          color: context.colors.onSurfaceVariant,
        ),
      ),
      TextButton(
        onPressed: onTap,
        style: TextButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 6),
        ),
        child: Text(action),
      ),
    ],
  );
}

/// Six 56-high digit boxes in LTR order over one hidden [TextFormField]
/// (so paste / autofill / screen readers see a single code field). The
/// box after the last digit gets the 2px pop focus border.
class OtpField extends StatefulWidget {
  const OtpField({
    super.key,
    required this.controller,
    required this.label,
    this.errorText,
    this.validator,
    this.onSubmitted,
    this.length = 6,
  });

  final TextEditingController controller;
  final String label;
  final String? errorText;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onSubmitted;
  final int length;

  @override
  State<OtpField> createState() => _OtpFieldState();
}

class _OtpFieldState extends State<OtpField> {
  final _focus = FocusNode();
  String? _validation;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
    _focus.addListener(_changed);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    _focus.dispose();
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  String? _validate(String? v) {
    final e = widget.validator?.call(v);
    if (e != _validation) {
      _validation = e;
      WidgetsBinding.instance.addPostFrameCallback((_) => _changed());
    }
    return e;
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final text = widget.controller.text;
    final error = widget.errorText ?? _validation;
    const hidden = OutlineInputBorder(borderSide: BorderSide.none);
    final active = _focus.hasFocus
        ? text.length.clamp(0, widget.length - 1)
        : -1;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Text(widget.label, style: context.text.titleSmall),
        ),
        SizedBox(
          height: 56,
          child: Stack(
            children: [
              ExcludeSemantics(
                child: Directionality(
                  textDirection: TextDirection.ltr,
                  child: Row(
                    children: [
                      for (var i = 0; i < widget.length; i++) ...[
                        if (i > 0) const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Container(
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: c.surfaceContainerLowest,
                              borderRadius: BorderRadius.circular(AppRadius.sm),
                              border: Border.all(
                                color: error != null
                                    ? c.error
                                    : i == active
                                    ? x.popBright
                                    : c.outline,
                                width: error != null || i == active ? 2 : 1,
                              ),
                            ),
                            child: Text(
                              i < text.length ? text[i] : '',
                              style: context.text.headlineSmall,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              Positioned.fill(
                child: TextFormField(
                  controller: widget.controller,
                  focusNode: _focus,
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  showCursor: false,
                  enableInteractiveSelection: false,
                  textDirection: TextDirection.ltr,
                  style: const TextStyle(
                    color: Colors.transparent,
                    fontSize: 1,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(widget.length),
                  ],
                  validator: _validate,
                  onFieldSubmitted: widget.onSubmitted,
                  decoration: InputDecoration(
                    labelText: null,
                    filled: false,
                    border: hidden,
                    enabledBorder: hidden,
                    focusedBorder: hidden,
                    errorBorder: hidden,
                    focusedErrorBorder: hidden,
                    disabledBorder: hidden,
                    contentPadding: EdgeInsets.zero,
                    errorStyle: const TextStyle(fontSize: 0, height: 0),
                    semanticCounterText: widget.label,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Row(
              children: [
                Icon(Icons.error_outline_rounded, size: 14, color: c.error),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    error,
                    style: context.text.bodySmall?.copyWith(color: c.error),
                  ),
                ),
              ],
            ),
          ),
      ],
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
              child: Text(
                l10n.orDivider,
                style: context.text.bodySmall?.copyWith(
                  color: context.extra.textMuted,
                ),
              ),
            ),
            const Expanded(child: Divider()),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(
          label: l10n.signInWithGoogle,
          icon: Icons.g_mobiledata_rounded,
          variant: AppButtonVariant.outlined,
          size: AppButtonSize.l,
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
