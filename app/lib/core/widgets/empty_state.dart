import 'package:flutter/material.dart';

import '../utils/context_ext.dart';
import 'state_block.dart';

/// Empty state (State block `Empty`). Pick a [tone]: pop for the wishlist,
/// violet for no results, brand otherwise.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    this.title,
    this.message,
    this.icon = Icons.inbox_outlined,
    this.actionLabel,
    this.onAction,
    this.tone = StateTone.brand,
  });

  final String? title;
  final String? message;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;
  final StateTone tone;

  @override
  Widget build(BuildContext context) => StateBlock(
    icon: icon,
    tone: tone,
    title: title ?? context.l10n.emptyTitle,
    message: message,
    actionLabel: actionLabel,
    onAction: onAction,
  );
}
