import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import 'app_button.dart';

/// Colour of the State block blob (`47:630`).
enum StateTone { brand, pop, violet, error, sun, mint }

/// Soft fill / strong foreground of a [StateTone] (blobs, icon circles).
(Color, Color) stateToneColors(BuildContext context, StateTone tone) {
  final c = context.colors;
  final x = context.extra;
  return switch (tone) {
    StateTone.brand => (x.brandSoft, c.primary),
    StateTone.pop => (x.popSoft, x.pop),
    StateTone.violet => (x.violetSoft, x.violet),
    StateTone.error => (c.errorContainer, c.error),
    StateTone.sun => (x.sunSoft, x.warningText),
    StateTone.mint => (x.mintSoft, x.mintStrong),
  };
}

/// Centred state: a 120 tinted blob with a 52 icon, H2 title, one Body/M
/// line and one button. [EmptyState], [ErrorState] and [OfflineState] are
/// presets of this widget.
class StateBlock extends StatelessWidget {
  const StateBlock({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.tone = StateTone.brand,
    this.actionLabel,
    this.actionIcon,
    this.onAction,
    this.actionVariant = AppButtonVariant.filled,
  });

  final IconData icon;
  final String title;
  final String? message;
  final StateTone tone;
  final String? actionLabel;
  final IconData? actionIcon;
  final VoidCallback? onAction;
  final AppButtonVariant actionVariant;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final (Color soft, Color strong) = stateToneColors(context, tone);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ExcludeSemantics(
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(color: soft, shape: BoxShape.circle),
                child: Icon(icon, size: 52, color: strong),
              ),
            ),
            const SizedBox(height: AppSpacing.mdl),
            Semantics(
              header: true,
              child: Text(
                title,
                textAlign: TextAlign.center,
                style: context.text.headlineSmall,
              ),
            ),
            if (message != null && message!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: context.text.bodyMedium?.copyWith(
                  color: c.onSurfaceVariant,
                ),
              ),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: AppSpacing.mdl),
              AppButton(
                label: actionLabel!,
                icon: actionIcon,
                variant: actionVariant,
                onPressed: onAction,
                expand: false,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Offline state (sun) with an optional retry.
class OfflineState extends StatelessWidget {
  const OfflineState({super.key, this.onRetry});

  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => StateBlock(
    icon: Icons.language_rounded,
    tone: StateTone.sun,
    title: context.l10n.offlineTitle,
    message: context.l10n.offlineBody,
    actionLabel: onRetry == null ? null : context.l10n.retry,
    actionVariant: AppButtonVariant.outlined,
    onAction: onRetry,
  );
}
