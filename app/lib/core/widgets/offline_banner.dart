import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// App/Offline banner (`47:631`): 40-high full-width sun bar under the app
/// bar with a globe icon, the message and a "retry" link.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key, this.onRetry});

  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF2F2129);
    final l10n = context.l10n;
    return Material(
      color: context.extra.sun,
      child: SafeArea(
        bottom: false,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 40),
          child: Padding(
            padding: const EdgeInsetsDirectional.only(start: 16, end: 4),
            child: Row(
              children: [
                const Icon(Icons.language_rounded, size: 18, color: ink),
                const SizedBox(width: 8),
                Expanded(
                  child: Semantics(
                    liveRegion: true,
                    child: Text(
                      l10n.offlineBanner,
                      style: context.text.labelSmall?.copyWith(color: ink),
                    ),
                  ),
                ),
                if (onRetry != null)
                  TextButton(
                    onPressed: onRetry,
                    style: TextButton.styleFrom(
                      foregroundColor: ink,
                      minimumSize: const Size(44, 40),
                      textStyle: context.text.labelSmall?.copyWith(
                        decoration: TextDecoration.underline,
                      ),
                    ),
                    child: Text(l10n.retry),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
