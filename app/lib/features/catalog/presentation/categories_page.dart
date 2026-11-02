import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../shell/presentation/shell_scaffold.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import 'catalog_widgets.dart';

/// Categories tab: the menu tree; parents expand to reveal children.
class CategoriesPage extends ConsumerWidget {
  const CategoriesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final menu = ref.watch(menuProvider);
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(l10n.navCategories),
      ),
      body: Column(
        children: [
          const SearchPill(),
          Expanded(
            child: menu.when(
              loading: () => ListView(
                physics: const NeverScrollableScrollPhysics(),
                padding: const EdgeInsets.all(AppSpacing.md),
                children: [
                  for (var i = 0; i < 8; i++)
                    const Padding(
                      padding: EdgeInsets.only(bottom: AppSpacing.sm),
                      child: AppShimmer(height: 56, radius: AppRadius.sm),
                    ),
                ],
              ),
              error: (e, _) => ErrorState(
                error: e,
                onRetry: () => ref.invalidate(menuProvider),
              ),
              data: (nodes) => nodes.isEmpty
                  ? EmptyState(
                      title: l10n.categoriesEmpty,
                      icon: Icons.grid_view_rounded,
                    )
                  : RefreshIndicator(
                      onRefresh: () async {
                        ref.invalidate(menuProvider);
                        await ref
                            .read(menuProvider.future)
                            .catchError((_) => const <CategoryNode>[]);
                      },
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                        children: [
                          for (final n in nodes)
                            CategoryTile(node: n, depth: 0),
                        ],
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

/// One tree row; recursion handles any depth.
class CategoryTile extends StatelessWidget {
  const CategoryTile({super.key, required this.node, required this.depth});

  final CategoryNode node;
  final int depth;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final leading = SizedBox(
      width: 44,
      height: 44,
      child: ClipOval(
        child: node.image != null
            ? NetworkImageX(url: node.image, cacheWidth: 44)
            : ColoredBox(
                color: context.extra.tint,
                child: Center(
                  child: Text(
                    node.name.isEmpty ? '' : node.name.characters.first,
                    style: TextStyle(
                      color: context.colors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
      ),
    );
    final padding = EdgeInsetsDirectional.only(
      start: AppSpacing.md + depth * AppSpacing.md,
      end: AppSpacing.md,
    );
    if (node.children.isEmpty) {
      return ListTile(
        contentPadding: padding,
        leading: leading,
        title: Text(node.name),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: () => context.push(AppRoutes.category(node.slug)),
      );
    }
    return ExpansionTile(
      tilePadding: padding,
      shape: const Border(),
      collapsedShape: const Border(),
      leading: leading,
      title: Text(node.name),
      children: [
        ListTile(
          contentPadding: padding.add(
            const EdgeInsetsDirectional.only(start: AppSpacing.md),
          ),
          title: Text(
            l10n.allInCategory(node.name),
            style: TextStyle(
              color: context.colors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
          onTap: () => context.push(AppRoutes.category(node.slug)),
        ),
        for (final c in node.children) CategoryTile(node: c, depth: depth + 1),
      ],
    );
  }
}
