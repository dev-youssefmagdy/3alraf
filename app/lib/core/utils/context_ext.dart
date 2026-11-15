import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';
import '../theme/app_theme.dart';

/// Money display (spec §10.2): Western digits with thousands separators,
/// decimals only when the amount has cents. Arabic reads `1,250 ج.م`,
/// English `EGP 1,250`. The server's amount string stays the source of
/// truth: anything that does not parse as a number is shown unchanged.
extension AppLocalizationsMoney on AppLocalizations {
  String money(String amount, String currency) {
    final ar = localeName.startsWith('ar');
    final cur = currency.trim();
    final egp = cur.toUpperCase() == 'EGP' || cur == 'ج.م' || cur == 'ج.م.';
    final symbol = egp ? (ar ? 'ج.م' : 'EGP') : cur;
    final value = num.tryParse(amount.replaceAll(',', '').trim());
    if (value == null) return currencyAmount(amount, symbol);
    final whole = value == value.roundToDouble();
    final text = NumberFormat(whole ? '#,##0' : '#,##0.00', 'en').format(value);
    if (symbol.isEmpty) return text;
    return ar ? '$text $symbol' : '$symbol $text';
  }
}

/// Snackbar tones (App/Snackbar `47:576`).
enum SnackTone { info, success, error }

extension BuildContextX on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
  ThemeData get theme => Theme.of(this);
  ColorScheme get colors => Theme.of(this).colorScheme;
  TextTheme get text => Theme.of(this).textTheme;
  AppExtraColors get extra =>
      Theme.of(this).extension<AppExtraColors>() ?? AppExtraColors.light;

  /// Price / XS label styles (Baloo prices, Label/XS).
  AppTextStyles get textX =>
      Theme.of(this).extension<AppTextStyles>() ??
      AppTextStyles.of(extra.textMuted, colors.onSurface);

  /// True when the platform asks for reduced motion.
  bool get reduceMotion => MediaQuery.maybeDisableAnimationsOf(this) ?? false;

  void showSnack(
    String message, {
    SnackTone tone = SnackTone.info,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    ScaffoldMessenger.of(this)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        appSnackBar(
          this,
          message,
          tone: tone,
          actionLabel: actionLabel,
          onAction: onAction,
        ),
      );
  }
}

/// App/Snackbar (`47:576`) for callers that captured a [ScaffoldMessenger]
/// before an `await` (the widget may be gone by the time it shows).
SnackBar appSnackBar(
  BuildContext context,
  String message, {
  SnackTone tone = SnackTone.info,
  String? actionLabel,
  VoidCallback? onAction,
}) {
  final x = context.extra;
  final (bg, icon) = switch (tone) {
    SnackTone.success => (x.success, Icons.check_circle_rounded),
    SnackTone.error => (context.colors.error, Icons.error_outline_rounded),
    SnackTone.info => (x.ink, Icons.info_outline_rounded),
  };
  return SnackBar(
    backgroundColor: bg,
    duration: const Duration(seconds: 4),
    content: Row(
      children: [
        Icon(icon, size: 20, color: Colors.white),
        const SizedBox(width: 10),
        Expanded(child: Text(message)),
      ],
    ),
    action: actionLabel == null || onAction == null
        ? null
        : SnackBarAction(
            label: actionLabel,
            onPressed: onAction,
            textColor: tone == SnackTone.error ? Colors.white : x.sun,
          ),
  );
}
