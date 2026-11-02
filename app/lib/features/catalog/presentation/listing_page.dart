import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import 'catalog_widgets.dart';
import 'filter_sheet.dart';

/// Product listing for a category, brand, tag, custom page or search.
/// Mirrors `storefront/catalog.blade.php` with sort + filter sheets and
/// infinite pagination.
class ListingPage extends ConsumerStatefulWidget {
  const ListingPage({super.key, required this.scope});

  final ListingScope scope;

  @override
  ConsumerState<ListingPage> createState() => _ListingPageState();
}

class _ListingPageState extends ConsumerState<ListingPage> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scroll.hasClients && _scroll.position.extentAfter < 400) {
      ref.read(listingProvider(widget.scope).notifier).loadMore();
    }
  }

  ListingController get _controller =>
      ref.read(listingProvider(widget.scope).notifier);

  Future<void> _sort(ListingState state) async {
    final sorts = widget.scope.customPage != null
        ? catalogSorts.where((s) => s != 'top_rated').toList()
        : catalogSorts;
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: RadioGroup<String>(
          groupValue: state.query.sort,
          onChanged: (v) => Navigator.of(ctx).pop(v),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Text(ctx.l10n.sortBy, style: ctx.text.titleMedium),
              ),
              for (final s in sorts)
                RadioListTile<String>(
                  value: s,
                  title: Text(sortLabel(ctx.l10n, s)),
                ),
            ],
          ),
        ),
      ),
    );
    if (picked != null && picked != state.query.sort) {
      await _controller.applyQuery(state.query.copyWith(sort: picked));
      if (_scroll.hasClients) _scroll.jumpTo(0);
    }
  }

  Future<void> _filter(ListingState state) async {
    final result = await showFilterSheet(
      context,
      scope: widget.scope,
      current: state.query,
      priceOnly: widget.scope.customPage != null,
    );
    if (result != null) {
      await _controller.applyQuery(result);
      if (_scroll.hasClients) _scroll.jumpTo(0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final scope = widget.scope;
    final state = ref.watch(listingProvider(scope));
    // A short first page may not fill the viewport (no scroll event would
    // ever fire): after each batch, keep loading until it does.
    ref.listen(listingProvider(scope).select((s) => s.items.length), (_, _) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _onScroll());
    });
    final info = ref.watch(scopeInfoProvider(scope)).asData?.value;
    final title =
        info?.title ??
        state.title ??
        (scope.isSearch ? l10n.searchResultsFor(scope.q ?? '') : l10n.products);
    return Scaffold(
      appBar: AppBar(
        title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            tooltip: l10n.searchHint,
            icon: const Icon(Icons.search_rounded),
            onPressed: () => context.push(AppRoutes.search),
          ),
        ],
      ),
      body: Column(
        children: [
          _Toolbar(
            state: state,
            onSort: () => _sort(state),
            onFilter: () => _filter(state),
          ),
          Expanded(child: _body(context, state, info)),
        ],
      ),
    );
  }

  Widget _body(BuildContext context, ListingState state, ScopeInfo? info) {
    final l10n = context.l10n;
    if (state.loading && state.items.isEmpty) {
      return const ProductGridSkeleton();
    }
    if (state.error != null) {
      return ErrorState(error: state.error, onRetry: _controller.refresh);
    }
    final header = info == null ? null : _ScopeHeader(info: info);
    if (state.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _controller.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            ?header,
            SizedBox(
              height: 360,
              child: EmptyState(
                title: l10n.noProducts,
                message: state.query.activeFilters > 0
                    ? l10n.noProductsFiltered
                    : null,
                icon: Icons.search_off_rounded,
                actionLabel: state.query.activeFilters > 0
                    ? l10n.resetFilters
                    : null,
                onAction: state.query.activeFilters > 0
                    ? () => _controller.applyQuery(
                        state.query.copyWith(
                          brandIds: const {},
                          valueIds: const {},
                          clearPrice: true,
                        ),
                      )
                    : null,
              ),
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _controller.refresh,
      child: CustomScrollView(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          if (header != null) SliverToBoxAdapter(child: header),
          SliverPadding(
            padding: const EdgeInsets.all(AppSpacing.md),
            sliver: SliverGrid.builder(
              gridDelegate: productGridDelegate(context),
              itemCount: state.items.length,
              itemBuilder: (_, i) => ProductTile(
                key: ValueKey(state.items[i].id),
                product: state.items[i],
                heroScope: 'list',
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: state.loadMoreError != null
                ? Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Column(
                      children: [
                        Text(errorMessage(l10n, state.loadMoreError!)),
                        TextButton(
                          onPressed: _controller.loadMore,
                          child: Text(l10n.retry),
                        ),
                      ],
                    ),
                  )
                : state.loadingMore
                ? const Padding(
                    padding: EdgeInsets.all(AppSpacing.md),
                    child: Center(child: CircularProgressIndicator()),
                  )
                : const SizedBox(height: AppSpacing.lg),
          ),
        ],
      ),
    );
  }
}

String sortLabel(AppLocalizations l10n, String sort) => switch (sort) {
  'price_asc' => l10n.sortPriceAsc,
  'price_desc' => l10n.sortPriceDesc,
  'best_selling' => l10n.sortBestSelling,
  'top_rated' => l10n.sortTopRated,
  _ => l10n.sortNewest,
};

class _Toolbar extends StatelessWidget {
  const _Toolbar({
    required this.state,
    required this.onSort,
    required this.onFilter,
  });

  final ListingState state;
  final VoidCallback onSort;
  final VoidCallback onFilter;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final filters = state.query.activeFilters;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Row(
        children: [
          Expanded(
            child: Text(
              state.loading ? '' : l10n.productsCount(state.page.total),
              style: context.text.bodySmall,
            ),
          ),
          TextButton.icon(
            onPressed: onSort,
            icon: const Icon(Icons.swap_vert_rounded),
            label: Text(sortLabel(l10n, state.query.sort)),
          ),
          TextButton.icon(
            onPressed: onFilter,
            icon: Badge(
              isLabelVisible: filters > 0,
              label: Text('$filters'),
              child: const Icon(Icons.tune_rounded),
            ),
            label: Text(l10n.filters),
          ),
        ],
      ),
    );
  }
}

class _ScopeHeader extends StatelessWidget {
  const _ScopeHeader({required this.info});

  final ScopeInfo info;

  @override
  Widget build(BuildContext context) {
    final hasDescription =
        info.description != null && info.description!.trim().isNotEmpty;
    if (!hasDescription && info.children.isEmpty) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        AppSpacing.sm,
        AppSpacing.md,
        0,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasDescription)
            Text(info.description!, style: context.text.bodyMedium),
          if (info.children.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            SizedBox(
              height: 40,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: info.children.length,
                separatorBuilder: (_, _) =>
                    const SizedBox(width: AppSpacing.sm),
                itemBuilder: (_, i) => ActionChip(
                  label: Text(info.children[i].name),
                  onPressed: () =>
                      context.push(AppRoutes.category(info.children[i].slug)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
