import 'package:flutter/material.dart';

import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import 'shell_scaffold.dart';

/// Temporary tab content until the feature phases land.
class PlaceholderPage extends StatelessWidget {
  const PlaceholderPage({
    super.key,
    required this.title,
    required this.icon,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final IconData icon;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(leading: const ShellMenuButton(), title: Text(title)),
      body: Padding(
        padding: const EdgeInsets.only(bottom: kRaisedNavClearance),
        child: StateBlock(
          icon: icon,
          tone: StateTone.violet,
          title: title,
          message: message ?? context.l10n.comingSoon,
          actionLabel: actionLabel,
          onAction: onAction,
        ),
      ),
    );
  }
}
