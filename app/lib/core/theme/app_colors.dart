import 'package:flutter/material.dart';

/// Brand tokens, mirrored from the website's `theme.css`.
abstract final class AppColors {
  static const brand = Color(0xFFA8456C);
  static const brandPressed = Color(0xFF8F3A5C);
  static const rose = Color(0xFFC96F91);
  static const tint = Color(0xFFFBEAF0);
  static const gold = Color(0xFFD7B987);
  static const goldSoft = Color(0xFFF4E8D5);
  static const text = Color(0xFF45313B);
  static const heading = Color(0xFF2F2129);
  static const muted = Color(0xFF7A6672);
  static const border = Color(0xFFF0DDE4);
  static const surface = Color(0xFFFFF9F7);
  static const card = Color(0xFFFFFFFF);
  static const success = Color(0xFF2F806A);
  static const warning = Color(0xFFD99A36);
  static const danger = Color(0xFFC8384F);
}

/// App-specific colours that Material's [ColorScheme] has no slot for.
@immutable
class AppExtraColors extends ThemeExtension<AppExtraColors> {
  const AppExtraColors({
    required this.success,
    required this.warning,
    required this.gold,
    required this.goldSoft,
    required this.tint,
  });

  final Color success;
  final Color warning;
  final Color gold;
  final Color goldSoft;
  final Color tint;

  static const light = AppExtraColors(
    success: AppColors.success,
    warning: AppColors.warning,
    gold: AppColors.gold,
    goldSoft: AppColors.goldSoft,
    tint: AppColors.tint,
  );

  static const dark = AppExtraColors(
    success: Color(0xFF5DBFA5),
    warning: Color(0xFFE8B35E),
    gold: AppColors.gold,
    goldSoft: Color(0xFF3A2F26),
    tint: Color(0xFF3A2430),
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
    warning: warning ?? this.warning,
    gold: gold ?? this.gold,
    goldSoft: goldSoft ?? this.goldSoft,
    tint: tint ?? this.tint,
  );

  @override
  AppExtraColors lerp(ThemeExtension<AppExtraColors>? other, double t) {
    if (other is! AppExtraColors) return this;
    return AppExtraColors(
      success: Color.lerp(success, other.success, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      gold: Color.lerp(gold, other.gold, t)!,
      goldSoft: Color.lerp(goldSoft, other.goldSoft, t)!,
      tint: Color.lerp(tint, other.tint, t)!,
    );
  }
}
