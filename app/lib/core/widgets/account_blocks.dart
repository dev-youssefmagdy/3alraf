import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import 'app_logo.dart';
import 'state_block.dart';

/// Tinted circle with a centred icon (the 120 blob of forgot / verify /
/// reset / gate pages, smaller icon circles elsewhere). Decorative.
class ToneBlob extends StatelessWidget {
  const ToneBlob({
    super.key,
    required this.icon,
    this.tone = StateTone.brand,
    this.size = 120,
    this.iconSize = 52,
  });

  final IconData icon;
  final StateTone tone;
  final double size;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    final (soft, strong) = stateToneColors(context, tone);
    return ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: soft, shape: BoxShape.circle),
        child: Icon(icon, size: iconSize, color: strong),
      ),
    );
  }
}

/// The store logo on a white circle (auth backdrop, splash, about hero).
class LogoCircle extends StatelessWidget {
  const LogoCircle({
    super.key,
    required this.semanticLabel,
    this.size = 96,
    this.shadow = true,
  });

  final String semanticLabel;
  final double size;
  final bool shadow;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: Colors.white,
      shape: BoxShape.circle,
      boxShadow: shadow ? AppShadows.raised : null,
    ),
    child: AppLogo(
      semanticLabel: semanticLabel,
      width: size * 0.78,
      height: size * 0.78,
      zoom: 1.35,
    ),
  );
}

/// Decorative blob for colour backdrops / heroes (never read out).
class DecorCircle extends StatelessWidget {
  const DecorCircle({super.key, required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
    ),
  );
}

/// App/App bar `Brand` variant (`42:79`): brand-800 with a white title,
/// used across the affiliate area.
PreferredSizeWidget brandAppBar(
  BuildContext context, {
  required String title,
  List<Widget>? actions,
  Widget? leading,
}) {
  final bg = context.extra.brandStrong;
  return AppBar(
    backgroundColor: bg,
    foregroundColor: Colors.white,
    iconTheme: const IconThemeData(color: Colors.white),
    actionsIconTheme: const IconThemeData(color: Colors.white),
    shape: const Border(),
    systemOverlayStyle: SystemUiOverlayStyle.light,
    titleTextStyle: context.text.titleLarge?.copyWith(color: Colors.white),
    leading: leading,
    title: Text(title),
    actions: actions,
  );
}

/// Rounded progress bar (tier / bonus progress): 8 high, pill track.
class AppProgressBar extends StatelessWidget {
  const AppProgressBar({
    super.key,
    required this.value,
    this.color,
    this.trackColor,
    this.height = 8,
    this.semanticLabel,
  });

  /// 0..1 (clamped).
  final double value;
  final Color? color;
  final Color? trackColor;
  final double height;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final v = value.isNaN ? 0.0 : value.clamp(0.0, 1.0);
    return Semantics(
      label: semanticLabel,
      value: '${(v * 100).round()}%',
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: SizedBox(
          height: height,
          child: Stack(
            fit: StackFit.expand,
            children: [
              ColoredBox(
                color: trackColor ?? context.colors.surfaceContainerHighest,
              ),
              FractionallySizedBox(
                alignment: AlignmentDirectional.centerStart,
                widthFactor: v,
                child: ColoredBox(color: color ?? context.colors.primary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Soft card with an icon and a message (delete-account warning, notices).
class NoticeCard extends StatelessWidget {
  const NoticeCard({
    super.key,
    required this.icon,
    required this.text,
    this.tone = StateTone.error,
    this.title,
  });

  final IconData icon;
  final String text;
  final String? title;
  final StateTone tone;

  @override
  Widget build(BuildContext context) {
    final (soft, strong) = stateToneColors(context, tone);
    final fg = tone == StateTone.error
        ? context.colors.onErrorContainer
        : context.colors.onSurface;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: soft,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: strong, size: 24),
          const SizedBox(width: AppSpacing.smd),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null) ...[
                  Text(
                    title!,
                    style: context.text.titleMedium?.copyWith(color: strong),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                ],
                Text(text, style: context.text.bodyMedium?.copyWith(color: fg)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Inner page with a form: Inner app bar (back + title), a scrolling body
/// limited to 520 wide with 16 padding, and an optional sticky [bottom]
/// (e.g. a [StickyActionBar] with Primary L "حفظ").
class FormPageScaffold extends StatelessWidget {
  const FormPageScaffold({
    super.key,
    required this.title,
    required this.children,
    this.bottom,
    this.showBack = true,
    this.actions,
    this.appBar,
  });

  final String title;
  final List<Widget> children;
  final Widget? bottom;
  final bool showBack;
  final List<Widget>? actions;

  /// Replaces the default Inner app bar (e.g. [brandAppBar]).
  final PreferredSizeWidget? appBar;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar:
        appBar ??
        AppBar(
          automaticallyImplyLeading: showBack,
          title: Text(title),
          actions: actions,
        ),
    bottomNavigationBar: bottom,
    body: SafeArea(
      bottom: bottom == null,
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.gutter,
              AppSpacing.mdl,
              AppSpacing.gutter,
              AppSpacing.lg,
            ),
            children: children,
          ),
        ),
      ),
    ),
  );
}

/// Centred blob + lead text heading a form page (forgot / verify / reset).
class FormLead extends StatelessWidget {
  const FormLead({
    super.key,
    required this.icon,
    required this.text,
    this.tone = StateTone.violet,
    this.title,
  });

  final IconData icon;
  final String text;
  final String? title;
  final StateTone tone;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
    child: Column(
      children: [
        ToneBlob(icon: icon, tone: tone),
        if (title != null) ...[
          const SizedBox(height: AppSpacing.md),
          Semantics(
            header: true,
            child: Text(
              title!,
              textAlign: TextAlign.center,
              style: context.text.headlineSmall,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        Text(
          text,
          textAlign: TextAlign.center,
          style: context.text.bodyMedium?.copyWith(
            color: context.colors.onSurfaceVariant,
          ),
        ),
      ],
    ),
  );
}
