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
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: TextField(
          controller: _controller,
          autofocus: true,
          textInputAction: TextInputAction.search,
          onChanged: _onChanged,
          onSubmitted: _submit,
          decoration: InputDecoration(
            hintText: l10n.searchHint,
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            filled: false,
            suffixIcon: _controller.text.isEmpty
                ? null
                : IconButton(
                    tooltip: l10n.clear,
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () {
                      _controller.clear();
                      _onChanged('');
                    },
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
      return EmptyState(
        title: l10n.searchEmptyTitle,
        message: l10n.searchEmptyBody,
        icon: Icons.search_rounded,
      );
    }
    return ListView(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.md,
            AppSpacing.sm,
            0,
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  l10n.recentSearches,
                  style: context.text.titleMedium,
                ),
              ),
              TextButton(
                onPressed: () =>
                    ref.read(recentSearchesProvider.notifier).clear(),
                child: Text(l10n.clear),
              ),
            ],
          ),
        ),
        for (final t in recents)
          ListTile(
            leading: const Icon(Icons.history_rounded),
            title: Text(t),
            onTap: () => onPick(t),
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
      data: (s) => ListView(
        children: [
          ListTile(
            leading: const Icon(Icons.search_rounded),
            title: Text(l10n.searchFor(term)),
            onTap: () => onSubmit(term),
          ),
          for (final c in s.categories)
            ListTile(
              leading: const Icon(Icons.grid_view_rounded),
              title: Text(c.name),
              subtitle: Text(l10n.navCategories),
              onTap: () => context.push(AppRoutes.category(c.slug)),
            ),
          for (final p in s.products)
            ListTile(
              leading: SizedBox(
                width: 48,
                height: 48,
                child: NetworkImageX(
                  url: p.image,
                  radius: AppRadius.sm,
                  cacheWidth: 48,
                ),
              ),
              title: Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis),
              subtitle: Text(l10n.currencyAmount(p.price, currency)),
              onTap: () => openProduct(context, p.slug),
            ),
          if (s.isEmpty)
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Center(child: Text(l10n.noSuggestions)),
            ),
        ],
      ),
    );
  }
}
