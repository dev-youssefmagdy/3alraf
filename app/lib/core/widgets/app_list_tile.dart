import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// App/List tile (`47:558`): 56 high, padding 16, gap 12; a 36 brand-50
/// icon circle + title (Body/M Strong) + optional [value] + muted forward
/// chevron. [danger] uses a red-50 circle and red text with no chevron.
class AppListTile extends StatelessWidget {
  const AppListTile({
    super.key,
    required this.icon,
    required this.title,
    this.value,
    this.onTap,
    this.danger = false,
    this.showChevron = true,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String? value;
  final VoidCallback? onTap;
  final bool danger;
  final bool showChevron;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final fg = danger ? c.error : c.primary;
    return InkWell(
      onTap: onTap,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 56),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.sm,
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: danger ? c.errorContainer : x.brandSoft,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 18, color: fg),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Text(
                  title,
                  style: context.text.titleSmall?.copyWith(
                    color: danger ? c.error : null,
                  ),
                ),
              ),
              if (value != null)
                Padding(
                  padding: const EdgeInsetsDirectional.only(start: 8),
                  child: Text(
                    value!,
                    style: context.text.bodySmall?.copyWith(color: x.textMuted),
                  ),
                ),
              ?trailing,
              if (!danger && showChevron && trailing == null)
                Icon(Icons.chevron_right_rounded, size: 20, color: x.textMuted),
            ],
          ),
        ),
      ),
    );
  }
}

/// White radius-16 card grouping [AppListTile]s with 1px dividers.
class AppListGroup extends StatelessWidget {
  const AppListGroup({super.key, required this.children, this.title});

  final List<Widget> children;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (title != null)
          Padding(
            padding: const EdgeInsetsDirectional.only(
              start: AppSpacing.xs,
              bottom: AppSpacing.sm,
            ),
            child: Text(
              title!,
              style: context.text.labelSmall?.copyWith(
                color: c.onSurfaceVariant,
              ),
            ),
          ),
        Material(
          color: c.surfaceContainerLowest,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
            side: BorderSide(color: c.outlineVariant),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            children: [
              for (var i = 0; i < children.length; i++) ...[
                if (i > 0) const Divider(height: 1),
                children[i],
              ],
            ],
          ),
        ),
      ],
    );
  }
}
