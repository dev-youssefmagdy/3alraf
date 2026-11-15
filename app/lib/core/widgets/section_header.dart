import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// App/Section header (`46:329`, Default): H2 title + "عرض الكل" brand link
/// with a 16 chevron that mirrors with the text direction. [trailing]
/// replaces the link (e.g. a countdown).
class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.onViewAll,
    this.trailing,
    this.padding = const EdgeInsets.fromLTRB(
      AppSpacing.gutter,
      AppSpacing.section,
      AppSpacing.gutter,
      AppSpacing.smd,
    ),
  });

  final String title;
  final VoidCallback? onViewAll;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 32),
        child: Row(
          children: [
            Expanded(
              child: Semantics(
                header: true,
                child: Text(title, style: context.text.headlineSmall),
              ),
            ),
            if (trailing != null)
              trailing!
            else if (onViewAll != null)
              ViewAllLink(onPressed: onViewAll!),
          ],
        ),
      ),
    );
  }
}

/// "عرض الكل" + forward chevron (mirrors in RTL).
class ViewAllLink extends StatelessWidget {
  const ViewAllLink({super.key, required this.onPressed, this.color});

  final VoidCallback onPressed;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final fg = color ?? context.colors.primary;
    return TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(
        foregroundColor: fg,
        minimumSize: const Size(44, 36),
        padding: const EdgeInsets.symmetric(horizontal: 8),
        textStyle: context.text.labelMedium,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(context.l10n.viewAll),
          const SizedBox(width: 2),
          const Icon(Icons.chevron_right_rounded, size: 16),
        ],
      ),
    );
  }
}
