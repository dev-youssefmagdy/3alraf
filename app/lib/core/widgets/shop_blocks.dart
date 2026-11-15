import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Colour of a [ColorHeaderBlock].
enum HeaderTone { pop, sun, violet, gold, mint, brand }

/// Page header colour block (catalog / brands / bundles / blog): radius 24,
/// padding 20, a decorative circle at the inline-end, Display/M title, an
/// optional subtitle line and an optional [child] below (chips, steps).
/// Categories use pop, brands sun, search violet, bundles gold.
class ColorHeaderBlock extends StatelessWidget {
  const ColorHeaderBlock({
    super.key,
    required this.title,
    this.subtitle,
    this.tone = HeaderTone.pop,
    this.child,
    this.margin = const EdgeInsets.fromLTRB(
      AppSpacing.gutter,
      AppSpacing.gutter,
      AppSpacing.gutter,
      0,
    ),
    this.radius = AppRadius.lg,
  });

  final String title;
  final String? subtitle;
  final HeaderTone tone;
  final Widget? child;
  final EdgeInsetsGeometry margin;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = headerColors(context, tone);
    return Container(
      margin: margin,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(radius),
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -48,
            start: -36,
            child: ExcludeSemantics(
              child: Container(
                width: 140,
                height: 140,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: fg.withValues(alpha: 0.08),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.mdl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Semantics(
                  header: true,
                  child: Text(
                    title,
                    style: context.text.displaySmall?.copyWith(color: fg),
                  ),
                ),
                if (subtitle != null && subtitle!.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    subtitle!,
                    style: context.text.bodyMedium?.copyWith(
                      color: fg.withValues(alpha: 0.88),
                    ),
                  ),
                ],
                if (child != null) ...[
                  const SizedBox(height: AppSpacing.smd),
                  child!,
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Background / foreground pair of a header tone (text never white on sun).
(Color, Color) headerColors(BuildContext context, HeaderTone tone) {
  final x = context.extra;
  return switch (tone) {
    HeaderTone.pop => (x.pop, Colors.white),
    HeaderTone.sun => (x.sun, AppColors.slate950),
    HeaderTone.violet => (x.violet, Colors.white),
    HeaderTone.gold => (x.gold, AppColors.slate950),
    HeaderTone.mint => (x.mint, AppColors.slate950),
    HeaderTone.brand => (x.brandStrong, Colors.white),
  };
}

/// White pill chip on a colour header (sub-categories, gift summary):
/// selected = brand fill with a check.
class HeaderChip extends StatelessWidget {
  const HeaderChip({
    super.key,
    required this.label,
    required this.onTap,
    this.selected = false,
    this.icon,
  });

  final String label;
  final VoidCallback? onTap;
  final bool selected;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final fg = selected ? c.onPrimary : AppColors.slate950;
    return Semantics(
      button: onTap != null,
      selected: selected,
      child: Material(
        color: selected ? c.primary : Colors.white,
        shape: const StadiumBorder(),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 40),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (selected || icon != null) ...[
                    Icon(icon ?? Icons.check_rounded, size: 16, color: fg),
                    const SizedBox(width: 6),
                  ],
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.text.labelMedium?.copyWith(color: fg),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// White radius-16 card with a 1px outlineVariant border and padding 16.
/// [title] renders as H2; [number] adds the 28 brand step circle before it
/// (checkout cards); [trailing] sits at the inline-end of the title row.
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.child,
    this.title,
    this.number,
    this.trailing,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.margin = EdgeInsets.zero,
    this.titleStyle,
  });

  final String? title;
  final int? number;
  final Widget? trailing;
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final TextStyle? titleStyle;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    // A Material (not a decorated box) so list tiles / checkboxes inside
    // paint their ink on the card.
    return Padding(
      padding: margin,
      child: Material(
        color: c.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(color: c.outlineVariant),
        ),
        child: Padding(
          padding: padding,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (title != null) ...[
                Row(
                  children: [
                    if (number != null) ...[
                      StepNumber(number: number!),
                      const SizedBox(width: AppSpacing.sm),
                    ],
                    Expanded(
                      child: Semantics(
                        header: true,
                        child: Text(
                          title!,
                          style: titleStyle ?? context.text.headlineSmall,
                        ),
                      ),
                    ),
                    ?trailing,
                  ],
                ),
                const SizedBox(height: AppSpacing.smd),
              ],
              child,
            ],
          ),
        ),
      ),
    );
  }
}

/// 28 brand circle with a white number.
class StepNumber extends StatelessWidget {
  const StepNumber({super.key, required this.number, this.size = 28});

  final int number;
  final double size;

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: context.colors.primary,
        shape: BoxShape.circle,
      ),
      child: Text(
        '$number',
        style: context.text.labelSmall?.copyWith(
          color: context.colors.onPrimary,
        ),
      ),
    ),
  );
}

/// Choice card (`7:233`): radio at the start, title + subtitle, an optional
/// [trailing] (price / fee) at the end. Selected = brand-50 fill with a
/// 1.5px brand border; disabled = 50 % opacity.
class ChoiceCard extends StatelessWidget {
  const ChoiceCard({
    super.key,
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
    this.trailing,
    this.icon,
    this.badge,
    this.body,
    this.enabled = true,
    this.showRadio = true,
    this.leading,
  });

  /// Optional media (e.g. packaging photo) between the radio and the text.
  final Widget? leading;

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback? onTap;
  final Widget? trailing;
  final IconData? icon;

  /// Shown after the title (e.g. "default" badge).
  final Widget? badge;

  /// Extra content under the subtitle.
  final Widget? body;
  final bool enabled;
  final bool showRadio;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final active = enabled && onTap != null;
    final card = Material(
      color: selected ? x.brandSoft : c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(
          color: selected ? c.primary : c.outlineVariant,
          width: selected ? 1.5 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: active ? onTap : null,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (showRadio) ...[
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: _Radio(selected: selected),
                ),
                const SizedBox(width: AppSpacing.smd),
              ],
              if (leading != null) ...[
                leading!,
                const SizedBox(width: AppSpacing.smd),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(title, style: context.text.titleMedium),
                        if (icon != null)
                          Icon(icon, size: 18, color: x.mintStrong),
                        ?badge,
                      ],
                    ),
                    if (subtitle != null && subtitle!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle!,
                        style: context.text.bodySmall?.copyWith(
                          color: c.onSurfaceVariant,
                        ),
                      ),
                    ],
                    if (body != null) ...[
                      const SizedBox(height: AppSpacing.sm),
                      body!,
                    ],
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: AppSpacing.sm),
                trailing!,
              ],
            ],
          ),
        ),
      ),
    );
    return Semantics(
      selected: selected,
      inMutuallyExclusiveGroup: showRadio,
      enabled: active,
      child: Opacity(opacity: enabled ? 1 : 0.5, child: card),
    );
  }
}

class _Radio extends StatelessWidget {
  const _Radio({required this.selected});
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: c.surfaceContainerLowest,
        border: Border.all(
          color: selected ? c.primary : c.outline,
          width: selected ? 2 : 1.5,
        ),
      ),
      alignment: Alignment.center,
      child: selected
          ? Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: c.primary,
              ),
            )
          : null,
    );
  }
}

/// Dashed rounded box used for "add address" rows and the proof upload
/// target (`7:366`). Pass [onTap] to make it a button.
class DashedBox extends StatelessWidget {
  const DashedBox({
    super.key,
    required this.child,
    this.onTap,
    this.color,
    this.fill,
    this.radius = AppRadius.md,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.semanticLabel,
  });

  final Widget child;
  final VoidCallback? onTap;
  final Color? color;
  final Color? fill;
  final double radius;
  final EdgeInsetsGeometry padding;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final border = color ?? context.colors.primary;
    return Semantics(
      button: onTap != null,
      label: semanticLabel,
      child: CustomPaint(
        painter: _DashedPainter(color: border, radius: radius),
        child: Material(
          color: fill ?? Colors.transparent,
          borderRadius: BorderRadius.circular(radius),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(padding: padding, child: child),
          ),
        ),
      ),
    );
  }
}

class _DashedPainter extends CustomPainter {
  _DashedPainter({required this.color, required this.radius});

  final Color color;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    final r = math.min(radius, size.shortestSide / 2);
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius(
          Offset.zero & size,
          Radius.circular(r),
        ).deflate(0.75),
      );
    for (final metric in path.computeMetrics()) {
      var d = 0.0;
      while (d < metric.length) {
        canvas.drawPath(metric.extractPath(d, d + 6), paint);
        d += 10;
      }
    }
  }

  @override
  bool shouldRepaint(_DashedPainter old) =>
      old.color != color || old.radius != radius;
}

/// App/Sticky action bar (`47:750`): white, padding 16, `shadowBar`, above
/// the system inset. Pass [leading] (e.g. [BarTotal]) and the main [action].
class StickyActionBar extends StatelessWidget {
  const StickyActionBar({
    super.key,
    required this.action,
    this.leading,
    this.dark = false,
    this.safeBottom = true,
  });

  final Widget? leading;
  final Widget action;

  /// Ink bar (gift finder "add selected").
  final bool dark;

  /// False when the bar stacks above the bottom nav (cart tab).
  final bool safeBottom;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: dark ? context.extra.ink : c.surfaceContainerLowest,
        boxShadow: AppShadows.bar,
      ),
      child: SafeArea(
        top: false,
        bottom: safeBottom,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              if (leading != null) ...[
                leading!,
                const SizedBox(width: AppSpacing.md),
              ],
              Expanded(child: action),
            ],
          ),
        ),
      ),
    );
  }
}

/// "الإجمالي" label over a Price/M amount, for sticky bars.
class BarTotal extends StatelessWidget {
  const BarTotal({
    super.key,
    required this.label,
    required this.amount,
    this.color,
    this.labelColor,
  });

  final String label;
  final String amount;
  final Color? color;
  final Color? labelColor;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(
        label,
        style: context.text.bodySmall?.copyWith(
          color: labelColor ?? context.colors.onSurfaceVariant,
        ),
      ),
      Text(
        amount,
        style: context.textX.priceM.copyWith(
          color: color ?? context.colors.primary,
        ),
      ),
    ],
  );
}

/// Small rounded count pill next to a page title ("4 منتجات").
class CountPill extends StatelessWidget {
  const CountPill({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
    decoration: BoxDecoration(
      color: context.extra.popSoft,
      borderRadius: BorderRadius.circular(AppRadius.pill),
    ),
    child: Text(
      label,
      style: context.textX.labelXs.copyWith(color: context.extra.pop),
    ),
  );
}

/// Display/M page title with an optional [CountPill] (Cart, Wishlist).
class PageTitleRow extends StatelessWidget {
  const PageTitleRow({
    super.key,
    required this.title,
    this.count,
    this.padding = const EdgeInsets.fromLTRB(
      AppSpacing.gutter,
      AppSpacing.gutter,
      AppSpacing.gutter,
      AppSpacing.smd,
    ),
  });

  final String title;
  final String? count;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => Padding(
    padding: padding,
    child: Wrap(
      spacing: AppSpacing.smd,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Semantics(
          header: true,
          child: Text(title, style: context.text.displaySmall),
        ),
        if (count != null) CountPill(label: count!),
      ],
    ),
  );
}

/// One-line coloured note with an icon (flash note, notices, errors).
class NoteBar extends StatelessWidget {
  const NoteBar({
    super.key,
    required this.text,
    required this.icon,
    required this.background,
    required this.foreground,
  });

  final String text;
  final IconData icon;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.md,
      vertical: AppSpacing.smd,
    ),
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: Row(
      children: [
        Icon(icon, size: 18, color: foreground),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            text,
            style: context.text.labelSmall?.copyWith(color: foreground),
          ),
        ),
      ],
    ),
  );
}

/// Extra bottom padding for scrolling content inside a shell tab so the
/// raised gift circle (≈20px over the bar) never covers the last item.
const double kRaisedNavClearance = 28;
