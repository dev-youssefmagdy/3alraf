import 'package:flutter/material.dart';

import '../error/api_exception.dart';
import '../utils/context_ext.dart';
import '../utils/error_messages.dart';
import 'app_button.dart';
import 'state_block.dart';

/// Error view with retry. Pass the caught [error] (usually an
/// `ApiException`) and it is turned into a localised message. Network
/// failures render the sun Offline variant.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, this.error, this.message, this.onRetry});

  final Object? error;
  final String? message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final text =
        message ??
        (error == null ? l10n.errorGeneric : errorMessage(l10n, error!));
    final offline =
        error != null && ApiException.from(error!).kind == ApiErrorKind.network;
    return StateBlock(
      icon: offline ? Icons.language_rounded : Icons.warning_amber_rounded,
      tone: offline ? StateTone.sun : StateTone.error,
      title: offline ? l10n.offlineTitle : l10n.errorTitle,
      message: text,
      actionLabel: onRetry == null ? null : l10n.retry,
      actionIcon: Icons.refresh_rounded,
      actionVariant: AppButtonVariant.outlined,
      onAction: onRetry,
    );
  }
}
