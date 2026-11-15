import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_colors.dart';

export 'app_colors.dart';

/// Spacing on the 4px grid (STOREFRONT_DESIGN.md §10.3).
abstract final class AppSpacing {
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double smd = 12;
  static const double md = 16;
  static const double mdl = 20;
  static const double lg = 24;
  static const double xl = 32;
  static const double xxl = 40;
  static const double xxxl = 48;

  /// Screen gutter.
  static const double gutter = 16;

  /// Gap between home sections.
  static const double section = 24;

  /// Gap between grid cells / rail items.
  static const double grid = 12;
}

abstract final class AppRadius {
  /// Checkbox, small boxes.
  static const double xs = 8;

  /// Inputs, product media, thumbnails.
  static const double sm = 12;

  /// Cards, list groups, cart lines, snackbars.
  static const double md = 16;

  /// Colour blocks, hero slide, auth card, sheets.
  static const double lg = 24;

  /// Onboarding art.
  static const double xl = 32;

  /// Pills: buttons, chips, badges, search, steppers.
  static const double pill = 999;
}

/// BoxShadows (cards use a 1px outlineVariant border instead of a shadow).
abstract final class AppShadows {
  static const _ink = AppColors.slate950;

  static const raised = [
    BoxShadow(
      color: Color(0x142F2129), // 8 %
      offset: Offset(0, 6),
      blurRadius: 16,
    ),
  ];

  static const overlay = [
    BoxShadow(
      color: Color(0x242F2129), // 14 %
      offset: Offset(0, 16),
      blurRadius: 40,
    ),
  ];

  static const bar = [
    BoxShadow(color: Color(0x142F2129), offset: Offset(0, -4), blurRadius: 16),
  ];

  /// Playful hard shadow under Primary / Pop / Sun buttons.
  static List<BoxShadow> sticker({bool pressed = false, Color? color}) => [
    BoxShadow(
      color: color ?? AppColors.brand800,
      offset: Offset(0, pressed ? 2 : 4),
    ),
  ];

  static Color get ink => _ink;
}

/// Display font for headings, display numbers and prices.
abstract final class AppFonts {
  static const body = 'Cairo';
  static const display = 'Baloo Bhaijaan 2';

  static TextStyle baloo(double size, double line, FontWeight weight) =>
      TextStyle(
        fontFamily: display,
        fontSize: size,
        height: line / size,
        fontWeight: weight,
        // The bundled Baloo file is a variable font: drive the axis too.
        fontVariations: [FontVariation('wght', weight.value.toDouble())],
        letterSpacing: 0,
      );

  static TextStyle cairo(double size, double line, FontWeight weight) =>
      TextStyle(
        fontFamily: body,
        fontSize: size,
        height: line / size,
        fontWeight: weight,
        letterSpacing: 0,
      );
}

/// Text styles Material's [TextTheme] has no slot for (§10.2).
@immutable
class AppTextStyles extends ThemeExtension<AppTextStyles> {
  const AppTextStyles({
    required this.labelXs,
    required this.priceL,
    required this.priceM,
    required this.priceOld,
  });

  /// Cairo 700 11/14: nav labels, badges.
  final TextStyle labelXs;

  /// Baloo 800 30/36: product / bundle price.
  final TextStyle priceL;

  /// Baloo 800 18/24: card price, totals.
  final TextStyle priceM;

  /// Cairo 400 12/16 struck, muted.
  final TextStyle priceOld;

  static AppTextStyles of(Color muted, Color text) => AppTextStyles(
    labelXs: AppFonts.cairo(11, 14, FontWeight.w700).copyWith(color: text),
    priceL: AppFonts.baloo(30, 36, FontWeight.w800).copyWith(color: text),
    priceM: AppFonts.baloo(18, 24, FontWeight.w800).copyWith(color: text),
    priceOld: AppFonts.cairo(12, 16, FontWeight.w400).copyWith(
      color: muted,
      decoration: TextDecoration.lineThrough,
      decorationColor: muted,
    ),
  );

  @override
  AppTextStyles copyWith({
    TextStyle? labelXs,
    TextStyle? priceL,
    TextStyle? priceM,
    TextStyle? priceOld,
  }) => AppTextStyles(
    labelXs: labelXs ?? this.labelXs,
    priceL: priceL ?? this.priceL,
    priceM: priceM ?? this.priceM,
    priceOld: priceOld ?? this.priceOld,
  );

  @override
  AppTextStyles lerp(ThemeExtension<AppTextStyles>? other, double t) {
    if (other is! AppTextStyles) return this;
    return AppTextStyles(
      labelXs: TextStyle.lerp(labelXs, other.labelXs, t)!,
      priceL: TextStyle.lerp(priceL, other.priceL, t)!,
      priceM: TextStyle.lerp(priceM, other.priceM, t)!,
      priceOld: TextStyle.lerp(priceOld, other.priceOld, t)!,
    );
  }
}

abstract final class AppTheme {
  static const String fontFamily = AppFonts.body;
  static const String displayFontFamily = AppFonts.display;

  static const _lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: AppColors.brand600,
    onPrimary: Colors.white,
    primaryContainer: AppColors.brand50,
    onPrimaryContainer: AppColors.brand800,
    secondary: AppColors.pop600,
    onSecondary: Colors.white,
    secondaryContainer: AppColors.pop50,
    onSecondaryContainer: AppColors.pop700,
    tertiary: AppColors.violet500,
    onTertiary: Colors.white,
    tertiaryContainer: AppColors.violet100,
    onTertiaryContainer: AppColors.violet700,
    error: AppColors.red600,
    onError: Colors.white,
    errorContainer: AppColors.red50,
    onErrorContainer: AppColors.red700,
    surface: AppColors.slate50,
    onSurface: AppColors.slate950,
    onSurfaceVariant: AppColors.slate600,
    outline: AppColors.slate300,
    outlineVariant: AppColors.slate200,
    surfaceContainerLowest: Colors.white,
    surfaceContainerLow: Colors.white,
    surfaceContainer: Color(0xFFFFF3F0),
    surfaceContainerHigh: AppColors.brand50,
    surfaceContainerHighest: AppColors.slate100,
    inverseSurface: AppColors.slate950,
    onInverseSurface: Colors.white,
    inversePrimary: AppColors.brand300,
    scrim: Colors.black,
    shadow: AppColors.slate950,
    surfaceTint: Colors.transparent,
  );

  static const _darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: Color(0xFFE08CAB),
    onPrimary: Color(0xFF3B1226),
    primaryContainer: Color(0xFF4A2234),
    onPrimaryContainer: AppColors.brand50,
    secondary: Color(0xFFFF6FAE),
    onSecondary: Color(0xFF3B0620),
    secondaryContainer: Color(0xFF4A1530),
    onSecondaryContainer: AppColors.pop50,
    tertiary: AppColors.violet400,
    onTertiary: Color(0xFF1B0D5C),
    tertiaryContainer: Color(0xFF2E2456),
    onTertiaryContainer: AppColors.violet100,
    error: Color(0xFFFF8A9A),
    onError: Color(0xFF3B0A14),
    errorContainer: Color(0xFF4A1D25),
    onErrorContainer: AppColors.red50,
    surface: AppColors.darkBase,
    onSurface: Color(0xFFF3E6EB),
    onSurfaceVariant: Color(0xFFC9B5BE),
    outline: Color(0xFF5A4651),
    outlineVariant: Color(0xFF3A2B33),
    surfaceContainerLowest: Color(0xFF261A21),
    surfaceContainerLow: Color(0xFF2A1D25),
    surfaceContainer: Color(0xFF2E2129),
    surfaceContainerHigh: Color(0xFF352630),
    surfaceContainerHighest: Color(0xFF3A2B34),
    inverseSurface: Color(0xFFF3E6EB),
    onInverseSurface: AppColors.slate950,
    inversePrimary: AppColors.brand600,
    scrim: Colors.black,
    shadow: Colors.black,
    surfaceTint: Colors.transparent,
  );

  static ThemeData get light => _build(_lightScheme, AppExtraColors.light);
  static ThemeData get dark => _build(_darkScheme, AppExtraColors.dark);

  static TextTheme _textTheme(ColorScheme s) {
    final on = s.onSurface;
    final t = TextTheme(
      displayLarge: AppFonts.baloo(56, 64, FontWeight.w800),
      displayMedium: AppFonts.baloo(44, 52, FontWeight.w800),
      displaySmall: AppFonts.baloo(32, 40, FontWeight.w800),
      headlineLarge: AppFonts.baloo(32, 40, FontWeight.w700),
      headlineMedium: AppFonts.baloo(28, 36, FontWeight.w700),
      headlineSmall: AppFonts.baloo(22, 30, FontWeight.w700),
      titleLarge: AppFonts.cairo(18, 28, FontWeight.w700),
      titleMedium: AppFonts.cairo(16, 24, FontWeight.w700),
      titleSmall: AppFonts.cairo(14, 22, FontWeight.w600),
      bodyLarge: AppFonts.cairo(16, 26, FontWeight.w400),
      bodyMedium: AppFonts.cairo(14, 22, FontWeight.w400),
      bodySmall: AppFonts.cairo(12, 18, FontWeight.w400),
      labelLarge: AppFonts.cairo(16, 24, FontWeight.w700),
      labelMedium: AppFonts.cairo(14, 20, FontWeight.w700),
      labelSmall: AppFonts.cairo(12, 16, FontWeight.w700),
    ).apply(bodyColor: on, displayColor: on);
    // Meta / help text reads in the secondary colour.
    return t.copyWith(
      bodySmall: t.bodySmall?.copyWith(color: s.onSurfaceVariant),
    );
  }

  static ThemeData _build(ColorScheme scheme, AppExtraColors extra) {
    final light = scheme.brightness == Brightness.light;
    final text = _textTheme(scheme);
    final textX = AppTextStyles.of(extra.textMuted, scheme.onSurface);
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: fontFamily,
      textTheme: text,
      scaffoldBackgroundColor: scheme.surface,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      extensions: [extra, textX],
    );
    const pill = StadiumBorder();
    final labelM = text.labelMedium;
    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.sm),
      borderSide: BorderSide(color: c, width: w),
    );
    final disabledFg = scheme.onSurface.withValues(alpha: 0.38);
    return base.copyWith(
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surfaceContainerLowest,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: 56,
        titleSpacing: 4,
        shape: Border(bottom: BorderSide(color: scheme.outlineVariant)),
        titleTextStyle: text.titleLarge,
        systemOverlayStyle: light
            ? SystemUiOverlayStyle.dark
            : SystemUiOverlayStyle.light,
      ),
      cardTheme: CardThemeData(
        color: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      // Primary = brand solid. Use AppButton(variant: pop / sun / danger)
      // for the other solid fills and the sticker shadow.
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          disabledBackgroundColor: scheme.primary.withValues(alpha: 0.45),
          disabledForegroundColor: scheme.onPrimary.withValues(alpha: 0.85),
          minimumSize: const Size(64, 44),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: pill,
          textStyle: labelM,
          elevation: 0,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(64, 44),
          shape: pill,
          textStyle: labelM,
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: scheme.onSurface,
          backgroundColor: scheme.surfaceContainerLowest,
          disabledForegroundColor: disabledFg,
          minimumSize: const Size(64, 44),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: pill,
          side: BorderSide(color: scheme.outline),
          textStyle: labelM,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: scheme.primary,
          minimumSize: const Size(48, 44),
          shape: pill,
          textStyle: labelM,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(minimumSize: const Size(44, 44)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surfaceContainerLowest,
        isDense: false,
        constraints: const BoxConstraints(minHeight: 48),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: 12,
        ),
        hintStyle: text.bodyMedium?.copyWith(color: extra.textMuted),
        helperStyle: text.bodySmall,
        errorStyle: text.bodySmall?.copyWith(color: scheme.error),
        border: border(scheme.outline),
        enabledBorder: border(scheme.outline),
        disabledBorder: border(scheme.outlineVariant),
        focusedBorder: border(extra.popBright, 2),
        errorBorder: border(scheme.error, 2),
        focusedErrorBorder: border(scheme.error, 2),
      ),
      chipTheme: ChipThemeData(
        shape: const StadiumBorder(),
        side: BorderSide(color: scheme.outline),
        backgroundColor: scheme.surfaceContainerLowest,
        selectedColor: scheme.primary,
        secondarySelectedColor: scheme.primary,
        checkmarkColor: scheme.onPrimary,
        labelStyle: text.labelMedium?.copyWith(color: scheme.onSurface),
        secondaryLabelStyle: text.labelMedium?.copyWith(
          color: scheme.onPrimary,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        showCheckmark: true,
      ),
      checkboxTheme: CheckboxThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        side: BorderSide(color: scheme.outline, width: 1.5),
      ),
      switchTheme: SwitchThemeData(
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: scheme.primary,
        linearTrackColor: scheme.outlineVariant,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        indicatorColor: extra.brandSoft,
        height: 64,
        labelTextStyle: WidgetStatePropertyAll(textX.labelXs),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: extra.ink,
        contentTextStyle: text.titleSmall?.copyWith(color: Colors.white),
        actionTextColor: extra.sun,
        insetPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        modalBarrierColor: Colors.black.withValues(alpha: 0.5),
        showDragHandle: true,
        dragHandleColor: scheme.outline,
        dragHandleSize: const Size(40, 4),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.lg),
          ),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        titleTextStyle: text.headlineSmall,
      ),
      drawerTheme: DrawerThemeData(
        backgroundColor: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        width: 320,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: scheme.primary,
        titleTextStyle: text.titleSmall,
        subtitleTextStyle: text.bodySmall,
        minTileHeight: 56,
      ),
      badgeTheme: BadgeThemeData(
        backgroundColor: extra.pop,
        textColor: Colors.white,
        textStyle: textX.labelXs,
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: scheme.primary,
        unselectedLabelColor: scheme.onSurfaceVariant,
        indicatorColor: scheme.primary,
        labelStyle: text.labelMedium,
        unselectedLabelStyle: text.labelMedium,
      ),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant, space: 1),
      popupMenuTheme: PopupMenuThemeData(
        color: scheme.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
      ),
    );
  }
}
