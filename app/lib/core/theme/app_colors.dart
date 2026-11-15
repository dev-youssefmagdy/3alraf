import 'package:flutter/material.dart';

/// Palette mirrored from the storefront `theme.css` (STOREFRONT_DESIGN.md §1
/// and §10.1). Hex values are exact; do not tweak them here.
abstract final class AppColors {
  // Brand rose.
  static const brand50 = Color(0xFFFBEAF0);
  static const brand100 = Color(0xFFF6D6E1);
  static const brand200 = Color(0xFFF0BFD0);
  static const brand300 = Color(0xFFECAEC4);
  static const brand400 = Color(0xFFE9A0B5);
  static const brand500 = Color(0xFFC96F91);
  static const brand600 = Color(0xFFA8456C);
  static const brand700 = Color(0xFF8F3A5C);
  static const brand800 = Color(0xFF78304C);
  static const brand900 = Color(0xFF5F263C);
  static const brand950 = Color(0xFF3F1828);

  // Pop magenta (logo).
  static const pop50 = Color(0xFFFFE8F2);
  static const pop100 = Color(0xFFFFC9E0);
  static const pop500 = Color(0xFFE5177A);
  static const pop600 = Color(0xFFC80F66);
  static const pop700 = Color(0xFFA10B52);

  // Sunshine.
  static const sun100 = Color(0xFFFFF3C4);
  static const sun300 = Color(0xFFFFD95A);
  static const sun400 = Color(0xFFFFC940);

  // Mint.
  static const mint100 = Color(0xFFD5F5EC);
  static const mint400 = Color(0xFF3CCFAE);
  static const mint700 = Color(0xFF0B7560);

  // Violet.
  static const violet100 = Color(0xFFECE7FF);
  static const violet400 = Color(0xFF8B6CFF);
  static const violet500 = Color(0xFF6C4BF4);
  static const violet700 = Color(0xFF4B2BC9);

  // Gold accent.
  static const gold = Color(0xFFD7B987);
  static const goldSoft = Color(0xFFF4E8D5);
  static const goldText = Color(0xFF7A5A22);

  // Plum-tinted neutrals.
  static const slate50 = Color(0xFFFFF9F7);
  static const slate100 = Color(0xFFF7ECEF);
  static const slate200 = Color(0xFFF0DDE4);
  static const slate300 = Color(0xFFDFC3CF);
  static const slate400 = Color(0xFF7A6672);
  static const slate500 = Color(0xFF756069);
  static const slate600 = Color(0xFF6A5560);
  static const slate700 = Color(0xFF5A4551);
  static const slate800 = Color(0xFF4D3944);
  static const slate900 = Color(0xFF45313B);
  static const slate950 = Color(0xFF2F2129);

  // Status.
  static const red50 = Color(0xFFFCE8EB);
  static const red600 = Color(0xFFC8384F);
  static const red700 = Color(0xFFA82D42);
  static const emerald50 = Color(0xFFE3F4EE);
  static const emerald600 = Color(0xFF2F806A);
  static const emerald700 = Color(0xFF25705C);
  static const amber50 = Color(0xFFFDF1DC);
  static const amber500 = Color(0xFFD99A36);
  static const amber700 = Color(0xFF8A5A12);

  // Legacy aliases (kept so older call sites keep compiling).
  static const brand = brand600;
  static const brandPressed = brand700;
  static const rose = brand500;
  static const tint = brand50;
  static const text = slate900;
  static const heading = slate950;
  static const muted = slate500;
  static const border = slate200;
  static const surface = slate50;
  static const card = Colors.white;
  static const success = emerald600;
  static const warning = amber500;
  static const danger = red600;
  static const ink = slate950;

  /// Dark-mode base used to build the 12–16 % soft tints.
  static const darkBase = Color(0xFF1F1519);
}

Color _tint(Color c, [double a = 0.16]) =>
    Color.alphaBlend(c.withValues(alpha: a), AppColors.darkBase);

/// App colours that Material's [ColorScheme] has no slot for (§10.1).
@immutable
class AppExtraColors extends ThemeExtension<AppExtraColors> {
  const AppExtraColors({
    required this.success,
    required this.successSoft,
    required this.successText,
    required this.warning,
    required this.warningSoft,
    required this.warningText,
    required this.gold,
    required this.goldSoft,
    required this.goldText,
    required this.tint,
    required this.brandStrong,
    required this.brandDeep,
    required this.pop,
    required this.popSoft,
    required this.popBright,
    required this.sun,
    required this.sunSoft,
    required this.mint,
    required this.mintStrong,
    required this.mintSoft,
    required this.violet,
    required this.violetSoft,
    required this.violetText,
    required this.ink,
    required this.onInk,
    required this.textMuted,
    required this.cardTints,
    required this.ringColours,
  });

  final Color success;
  final Color successSoft;
  final Color successText;
  final Color warning;
  final Color warningSoft;
  final Color warningText;
  final Color gold;
  final Color goldSoft;
  final Color goldText;

  /// brand-50: active nav pill, soft icon circles, selected cards.
  final Color tint;
  Color get brandSoft => tint;

  /// brand-800: hero, profile header, Brand app bar, trust band.
  final Color brandStrong;

  /// brand-950: splash gradient end.
  final Color brandDeep;

  /// pop-600 solid (sale badge, Pop button, flash block).
  final Color pop;
  final Color popSoft;

  /// pop-500: focus ring and decoration only, never behind text.
  final Color popBright;
  final Color sun;
  final Color sunSoft;
  final Color mint;
  final Color mintStrong;
  final Color mintSoft;
  final Color violet;
  final Color violetSoft;
  final Color violetText;

  /// slate-950: snackbar info, out-of-stock badge, add-selected bar.
  final Color ink;
  final Color onInk;
  final Color textMuted;

  /// Product media backgrounds, rotated by index.
  final List<Color> cardTints;

  /// Category circle rings, rotated by index.
  final List<Color> ringColours;

  Color cardTint(int index) => cardTints[index.abs() % cardTints.length];
  Color ring(int index) => ringColours[index.abs() % ringColours.length];

  static const light = AppExtraColors(
    success: AppColors.emerald600,
    successSoft: AppColors.emerald50,
    successText: AppColors.emerald700,
    warning: AppColors.amber500,
    warningSoft: AppColors.amber50,
    warningText: AppColors.amber700,
    gold: AppColors.gold,
    goldSoft: AppColors.goldSoft,
    goldText: AppColors.goldText,
    tint: AppColors.brand50,
    brandStrong: AppColors.brand800,
    brandDeep: AppColors.brand950,
    pop: AppColors.pop600,
    popSoft: AppColors.pop50,
    popBright: AppColors.pop500,
    sun: AppColors.sun400,
    sunSoft: AppColors.sun100,
    mint: AppColors.mint400,
    mintStrong: AppColors.mint700,
    mintSoft: AppColors.mint100,
    violet: AppColors.violet500,
    violetSoft: AppColors.violet100,
    violetText: AppColors.violet700,
    ink: AppColors.slate950,
    onInk: Colors.white,
    textMuted: AppColors.slate500,
    cardTints: [
      AppColors.mint100,
      AppColors.pop50,
      AppColors.sun100,
      AppColors.violet100,
    ],
    ringColours: [
      AppColors.brand600,
      AppColors.pop600,
      AppColors.sun400,
      AppColors.mint400,
      AppColors.violet500,
    ],
  );

  /// Strong colours stay; soft fills become 12–16 % tints on #1F1519.
  static final dark = AppExtraColors(
    success: const Color(0xFF5DBFA5),
    successSoft: _tint(AppColors.emerald600),
    successText: const Color(0xFF7FD3BC),
    warning: const Color(0xFFE8B35E),
    warningSoft: _tint(AppColors.amber500),
    warningText: const Color(0xFFF0C27A),
    gold: AppColors.gold,
    goldSoft: _tint(AppColors.gold),
    goldText: const Color(0xFFE6CFA6),
    tint: _tint(AppColors.brand500),
    brandStrong: AppColors.brand800,
    brandDeep: AppColors.brand950,
    pop: AppColors.pop600,
    popSoft: _tint(AppColors.pop500),
    popBright: AppColors.pop500,
    sun: AppColors.sun400,
    sunSoft: _tint(AppColors.sun400, 0.14),
    mint: AppColors.mint400,
    mintStrong: AppColors.mint700,
    mintSoft: _tint(AppColors.mint400, 0.14),
    violet: AppColors.violet500,
    violetSoft: _tint(AppColors.violet500),
    violetText: const Color(0xFFB9A8FF),
    ink: const Color(0xFF3A2B33),
    onInk: Colors.white,
    textMuted: const Color(0xFFB8A3AD),
    cardTints: [
      _tint(AppColors.mint400, 0.12),
      _tint(AppColors.pop500, 0.12),
      _tint(AppColors.sun400, 0.12),
      _tint(AppColors.violet500, 0.12),
    ],
    ringColours: const [
      AppColors.brand500,
      AppColors.pop600,
      AppColors.sun400,
      AppColors.mint400,
      AppColors.violet500,
    ],
  );

  @override
  AppExtraColors copyWith({
    Color? success,
    Color? warning,
    Color? gold,
    Color? goldSoft,
    Color? tint,
  }) => AppExtraColors(
    success: success ?? this.success,
    successSoft: successSoft,
    successText: successText,
    warning: warning ?? this.warning,
    warningSoft: warningSoft,
    warningText: warningText,
    gold: gold ?? this.gold,
    goldSoft: goldSoft ?? this.goldSoft,
    goldText: goldText,
    tint: tint ?? this.tint,
    brandStrong: brandStrong,
    brandDeep: brandDeep,
    pop: pop,
    popSoft: popSoft,
    popBright: popBright,
    sun: sun,
    sunSoft: sunSoft,
    mint: mint,
    mintStrong: mintStrong,
    mintSoft: mintSoft,
    violet: violet,
    violetSoft: violetSoft,
    violetText: violetText,
    ink: ink,
    onInk: onInk,
    textMuted: textMuted,
    cardTints: cardTints,
    ringColours: ringColours,
  );

  @override
  AppExtraColors lerp(ThemeExtension<AppExtraColors>? other, double t) {
    if (other is! AppExtraColors) return this;
    Color l(Color a, Color b) => Color.lerp(a, b, t)!;
    List<Color> ll(List<Color> a, List<Color> b) => [
      for (var i = 0; i < a.length; i++) l(a[i], b[i % b.length]),
    ];
    return AppExtraColors(
      success: l(success, other.success),
      successSoft: l(successSoft, other.successSoft),
      successText: l(successText, other.successText),
      warning: l(warning, other.warning),
      warningSoft: l(warningSoft, other.warningSoft),
      warningText: l(warningText, other.warningText),
      gold: l(gold, other.gold),
      goldSoft: l(goldSoft, other.goldSoft),
      goldText: l(goldText, other.goldText),
      tint: l(tint, other.tint),
      brandStrong: l(brandStrong, other.brandStrong),
      brandDeep: l(brandDeep, other.brandDeep),
      pop: l(pop, other.pop),
      popSoft: l(popSoft, other.popSoft),
      popBright: l(popBright, other.popBright),
      sun: l(sun, other.sun),
      sunSoft: l(sunSoft, other.sunSoft),
      mint: l(mint, other.mint),
      mintStrong: l(mintStrong, other.mintStrong),
      mintSoft: l(mintSoft, other.mintSoft),
      violet: l(violet, other.violet),
      violetSoft: l(violetSoft, other.violetSoft),
      violetText: l(violetText, other.violetText),
      ink: l(ink, other.ink),
      onInk: l(onInk, other.onInk),
      textMuted: l(textMuted, other.textMuted),
      cardTints: ll(cardTints, other.cardTints),
      ringColours: ll(ringColours, other.ringColours),
    );
  }
}
