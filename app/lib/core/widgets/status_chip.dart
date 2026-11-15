import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Badge tones (`5:420`). Soft tones use a 50-fill with dark text; [pop],
/// [violet], [sun] and [ink] are solid.
enum StatusTone {
  neutral,
  success,
  warning,
  danger,
  brand,
  pop,
  sun,
  violet,
  gold,
  mint,
  ink,
}

/// Badge / status pill: 22 high, Label/XS, optional 14 icon.
class StatusChip extends StatelessWidget {
  const StatusChip({
    super.key,
    required this.label,
    this.tone = StatusTone.neutral,
    this.icon,
  });

  final String label;
  final StatusTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final (Color bg, Color fg) = switch (tone) {
      StatusTone.success => (x.successSoft, x.successText),
      StatusTone.warning => (x.warningSoft, x.warningText),
      StatusTone.danger => (c.errorContainer, c.onErrorContainer),
      StatusTone.brand => (x.brandSoft, c.primary),
      StatusTone.neutral => (c.surfaceContainerHighest, c.onSurfaceVariant),
      StatusTone.pop => (x.pop, Colors.white),
      StatusTone.sun => (x.sun, const Color(0xFF2F2129)),
      StatusTone.violet => (x.violet, Colors.white),
      StatusTone.gold => (x.goldSoft, x.goldText),
      StatusTone.mint => (x.mintSoft, x.mintStrong),
      StatusTone.ink => (x.ink, x.onInk),
    };
    return Container(
      constraints: const BoxConstraints(minHeight: 22),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: fg),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.textX.labelXs.copyWith(color: fg),
            ),
          ),
        ],
      ),
    );
  }
}
