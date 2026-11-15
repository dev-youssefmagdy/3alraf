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
                      padding: EdgeInsets.only(bottom: AppSpacing.smd),
                      child: AppShimmer(height: 84, radius: AppRadius.md),
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
                      child: ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.gutter,
                          AppSpacing.xs,
                          AppSpacing.gutter,
                          AppSpacing.lg + kRaisedNavClearance,
                        ),
                        itemCount: nodes.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: AppSpacing.smd),
                        itemBuilder: (_, i) =>
                            CategoryTile(node: nodes[i], depth: 0, index: i),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

/// One root category card (`52:1119`): 60 ring + photo, name H3 and a
/// chevron. Cards with children expand (brand border) to show the
/// sub-category chips, "all in …" first; a leaf opens its listing.
class CategoryTile extends StatefulWidget {
  const CategoryTile({
    super.key,
    required this.node,
    required this.depth,
    this.index = 0,
  });

  final CategoryNode node;
  final int depth;
  final int index;

  @override
  State<CategoryTile> createState() => _CategoryTileState();
}

class _CategoryTileState extends State<CategoryTile> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final node = widget.node;
    final hasChildren = node.children.isNotEmpty;
    final letter = node.name.isEmpty ? '' : node.name.characters.first;
    final avatar = Container(
      width: 60,
      height: 60,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: x.ring(widget.index),
      ),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: x.brandSoft,
          border: Border.all(color: c.surfaceContainerLowest, width: 2),
        ),
        child: node.image != null
            ? NetworkImageX(url: node.image, cacheWidth: 60)
            : Center(
                child: Text(
                  letter,
                  style: context.text.titleLarge?.copyWith(color: c.primary),
                ),
              ),
      ),
    );
    final header = InkWell(
      onTap: hasChildren
          ? () => setState(() => _open = !_open)
          : () => context.push(AppRoutes.category(node.slug)),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.smd),
        child: Row(
          children: [
            ExcludeSemantics(child: avatar),
            const SizedBox(width: AppSpacing.smd),
            Expanded(
              child: Text(
                node.name,
                style: context.text.titleLarge,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            Icon(
              hasChildren
                  ? (_open
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded)
                  : Icons.chevron_right_rounded,
              color: c.onSurfaceVariant,
            ),
          ],
        ),
      ),
    );
    return Semantics(
      expanded: hasChildren ? _open : null,
      child: Material(
        color: c.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: _open ? c.primary : c.outlineVariant,
            width: _open ? 1.5 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            header,
            if (hasChildren && _open)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.smd,
                  0,
                  AppSpacing.smd,
                  AppSpacing.md,
                ),
                child: Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
                  children: [
                    ChoiceChip(
                      selected: true,
                      label: Text(l10n.allInCategory(node.name)),
                      onSelected: (_) =>
                          context.push(AppRoutes.category(node.slug)),
                    ),
                    for (final child in node.children)
                      ChoiceChip(
                        selected: false,
                        label: Text(child.name),
                        onSelected: (_) =>
                            context.push(AppRoutes.category(child.slug)),
                      ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
