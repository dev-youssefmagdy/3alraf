import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import '../utils/error_messages.dart';
import 'app_button.dart';

/// Error view with retry. Pass the caught [error] (usually an
/// `ApiException`) and it is turned into a localised message.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, this.error, this.message, this.onRetry});

  final Object? error;
  final String? message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final text =
        message ??
        (error == null
            ? context.l10n.errorGeneric
            : errorMessage(context.l10n, error!));
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.error_outline_rounded,
              size: 56,
              color: context.colors.error,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              text,
              textAlign: TextAlign.center,
              style: context.text.bodyLarge,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: AppSpacing.md),
              AppButton(
                label: context.l10n.retry,
                icon: Icons.refresh_rounded,
                onPressed: onRetry,
                expand: false,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
