import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import 'catalog_widgets.dart';

/// Search entry: live suggestions (debounced), recent searches, submit
/// opens the results listing.
class SearchPage extends ConsumerStatefulWidget {
  const SearchPage({super.key});

  @override
  ConsumerState<SearchPage> createState() => _SearchPageState();
}

class _SearchPageState extends ConsumerState<SearchPage> {
  final _controller = TextEditingController();
  Timer? _debounce;
  String _term = '';

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      if (mounted) setState(() => _term = value.trim());
    });
    setState(() {}); // clear button visibility
  }

  void _submit(String value) {
    final q = value.trim();
    if (q.length < 2) return;
    ref.read(recentSearchesProvider.notifier).add(q);
    context.push(AppRoutes.results(q));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final recents = ref.watch(recentSearchesProvider);
    final c = context.colors;
    final pill = OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.pill),
      borderSide: BorderSide.none,
    );
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Padding(
          padding: const EdgeInsetsDirectional.only(end: AppSpacing.gutter),
          child: SizedBox(
            height: 44,
            child: TextField(
              controller: _controller,
              autofocus: true,
              textInputAction: TextInputAction.search,
              onChanged: _onChanged,
              onSubmitted: _submit,
              style: context.text.bodyMedium,
              decoration: InputDecoration(
                hintText: l10n.searchHint,
                filled: true,
                fillColor: c.surfaceContainerHighest,
                isDense: true,
                contentPadding: const EdgeInsetsDirectional.only(
                  start: AppSpacing.md,
                  end: AppSpacing.xs,
                ),
                border: pill,
                enabledBorder: pill,
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                  borderSide: BorderSide(
                    color: context.extra.popBright,
                    width: 2,
                  ),
                ),
                suffixIcon: _controller.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: l10n.clear,
                        icon: const Icon(Icons.close_rounded, size: 20),
                        onPressed: () {
                          _controller.clear();
                          _onChanged('');
                        },
                      ),
              ),
            ),
          ),
        ),
      ),
      body: _term.length >= 2
          ? _Suggestions(term: _term, onSubmit: _submit)
          : _Recents(
              recents: recents,
              onPick: (t) {
                _controller.text = t;
                _submit(t);
              },
            ),
    );
  }
}

class _Recents extends ConsumerWidget {
  const _Recents({required this.recents, required this.onPick});

  final List<String> recents;
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    if (recents.isEmpty) {
      return StateBlock(
        title: l10n.searchEmptyTitle,
        message: l10n.searchEmptyBody,
        icon: Icons.search_rounded,
        tone: StateTone.violet,
      );
    }
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.gutter),
      children: [
        Row(
          children: [
            Expanded(
              child: Semantics(
                header: true,
                child: Text(
                  l10n.recentSearches,
                  style: context.text.titleLarge,
                ),
              ),
            ),
            TextButton(
              style: TextButton.styleFrom(foregroundColor: context.extra.pop),
              onPressed: () =>
                  ref.read(recentSearchesProvider.notifier).clear(),
              child: Text(l10n.clear),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (final t in recents)
              ActionChip(
                avatar: const Icon(Icons.history_rounded, size: 16),
                label: Text(t),
                onPressed: () => onPick(t),
              ),
          ],
        ),
      ],
    );
  }
}

class _Suggestions extends ConsumerWidget {
  const _Suggestions({required this.term, required this.onSubmit});

  final String term;
  final ValueChanged<String> onSubmit;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final currency = ref.watch(currencyLabelProvider);
    final result = ref.watch(suggestProvider(term));
    return result.when(
      loading: () => const LinearProgressIndicator(),
      error: (e, _) => ErrorState(
        error: e,
        onRetry: () => ref.invalidate(suggestProvider(term)),
      ),
      data: (s) {
        final c = context.colors;
        Widget row({
          required Widget leading,
          required String title,
          Widget? subtitle,
          required VoidCallback onTap,
        }) => InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.smd),
            child: Row(
              children: [
                leading,
                const SizedBox(width: AppSpacing.smd),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.text.titleSmall,
                      ),
                      ?subtitle,
                    ],
                  ),
                ),
                Icon(
                  Icons.chevron_right_rounded,
                  color: context.extra.textMuted,
                ),
              ],
            ),
          ),
        );
        final rows = <Widget>[
          for (final cat in s.categories)
            row(
              leading: Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: context.extra.brandSoft,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: Icon(Icons.grid_view_rounded, color: c.primary),
              ),
              title: cat.name,
              subtitle: Text(
                l10n.navCategories,
                style: context.text.bodySmall?.copyWith(
                  color: context.extra.textMuted,
                ),
              ),
              onTap: () => context.push(AppRoutes.category(cat.slug)),
            ),
          for (final p in s.products)
            row(
              leading: NetworkImageX(
                url: p.image,
                width: 52,
                height: 52,
                radius: AppRadius.sm,
                cacheWidth: 104,
              ),
              title: p.name,
              subtitle: Text(
                l10n.money(p.price, currency),
                style: context.textX.priceM.copyWith(
                  color: context.extra.pop,
                  fontSize: 16,
                ),
              ),
              onTap: () => openProduct(context, p.slug),
            ),
        ];
        return ListView(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: [
            if (rows.isNotEmpty)
              SectionCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < rows.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      rows[i],
                    ],
                  ],
                ),
              )
            else
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Center(
                  child: Text(
                    l10n.noSuggestions,
                    style: context.text.bodyMedium?.copyWith(
                      color: context.extra.textMuted,
                    ),
                  ),
                ),
              ),
            const SizedBox(height: AppSpacing.smd),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed: () => onSubmit(term),
                icon: const Icon(Icons.search_rounded, size: 18),
                label: Text(l10n.searchFor(term)),
              ),
            ),
          ],
        );
      },
    );
  }
}
