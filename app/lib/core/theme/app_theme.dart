import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Spacing (8-pt grid) and radii shared across widgets.
abstract final class AppSpacing {
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 16;
  static const double lg = 24;
  static const double xl = 32;
}

abstract final class AppRadius {
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 24;
}

abstract final class AppTheme {
  static const String fontFamily = 'Cairo';

  static const _lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: AppColors.brand,
    onPrimary: Colors.white,
    primaryContainer: AppColors.tint,
    onPrimaryContainer: AppColors.brandPressed,
    secondary: AppColors.gold,
    onSecondary: AppColors.heading,
    secondaryContainer: AppColors.goldSoft,
    onSecondaryContainer: AppColors.heading,
    tertiary: AppColors.rose,
    onTertiary: Colors.white,
    error: AppColors.danger,
    onError: Colors.white,
    surface: AppColors.surface,
    onSurface: AppColors.text,
    onSurfaceVariant: AppColors.muted,
    outline: AppColors.border,
    outlineVariant: AppColors.border,
    surfaceContainerLowest: AppColors.card,
    surfaceContainerLow: AppColors.card,
    surfaceContainer: Color(0xFFFFF3F0),
    surfaceContainerHigh: AppColors.tint,
    surfaceContainerHighest: Color(0xFFF6E2E9),
    inverseSurface: AppColors.heading,
    onInverseSurface: Colors.white,
    surfaceTint: AppColors.brand,
  );

  static const _darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: Color(0xFFE08CAB),
    onPrimary: Color(0xFF3B1226),
    primaryContainer: Color(0xFF6E2C49),
    onPrimaryContainer: AppColors.tint,
    secondary: AppColors.gold,
    onSecondary: Color(0xFF2F2129),
    secondaryContainer: Color(0xFF4A3B2A),
    onSecondaryContainer: AppColors.goldSoft,
    tertiary: AppColors.rose,
    onTertiary: Color(0xFF3B1226),
    error: Color(0xFFFF8A9A),
    onError: Color(0xFF3B0A14),
    surface: Color(0xFF1F161B),
    onSurface: Color(0xFFF3E6EB),
    onSurfaceVariant: Color(0xFFC9B5BE),
    outline: Color(0xFF4A3842),
    outlineVariant: Color(0xFF3A2B33),
    surfaceContainerLowest: Color(0xFF191116),
    surfaceContainerLow: Color(0xFF261A21),
    surfaceContainer: Color(0xFF2B1E26),
    surfaceContainerHigh: Color(0xFF352630),
    surfaceContainerHighest: Color(0xFF402F3A),
    inverseSurface: Color(0xFFF3E6EB),
    onInverseSurface: Color(0xFF2F2129),
    surfaceTint: Color(0xFFE08CAB),
  );

  static ThemeData get light => _build(_lightScheme, AppExtraColors.light);
  static ThemeData get dark => _build(_darkScheme, AppExtraColors.dark);

  static ThemeData _build(ColorScheme scheme, AppExtraColors extra) {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: fontFamily,
      scaffoldBackgroundColor: scheme.surface,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      extensions: [extra],
    );
    final text = base.textTheme.apply(
      bodyColor: scheme.onSurface,
      displayColor: scheme.brightness == Brightness.light
          ? AppColors.heading
          : scheme.onSurface,
      fontFamily: fontFamily,
    );
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.sm),
    );
    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.sm),
      borderSide: BorderSide(color: c, width: w),
    );
    return base.copyWith(
      textTheme: text,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: true,
        titleTextStyle: text.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      cardTheme: CardThemeData(
        color: scheme.surfaceContainerLowest,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(color: scheme.outline),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 48),
          shape: shape,
          textStyle: text.labelLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 48),
          shape: shape,
          side: BorderSide(color: scheme.primary),
          textStyle: text.labelLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, 48),
          shape: shape,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surfaceContainerLowest,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: 14,
        ),
        border: border(scheme.outline),
        enabledBorder: border(scheme.outline),
        focusedBorder: border(scheme.primary, 1.6),
        errorBorder: border(scheme.error),
        focusedErrorBorder: border(scheme.error, 1.6),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        side: BorderSide(color: scheme.outline),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        indicatorColor: scheme.primaryContainer,
        height: 68,
        labelTextStyle: WidgetStatePropertyAll(
          text.labelSmall?.copyWith(fontWeight: FontWeight.w600),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: shape,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        showDragHandle: true,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.lg),
          ),
        ),
      ),
      dividerTheme: DividerThemeData(color: scheme.outline, space: 1),
    );
  }
}
