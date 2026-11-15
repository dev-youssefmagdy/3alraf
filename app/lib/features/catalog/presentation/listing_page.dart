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
    final picked = await showAppBottomSheet<String>(
      context: context,
      builder: (ctx) => AppSheet(
        title: ctx.l10n.sortBy,
        child: RadioGroup<String>(
          groupValue: state.query.sort,
          onChanged: (v) => Navigator.of(ctx).pop(v),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (final s in sorts)
                RadioListTile<String>(
                  value: s,
                  contentPadding: EdgeInsets.zero,
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
      body: _body(context, state, info, title),
    );
  }

  HeaderTone get _tone => widget.scope.isSearch
      ? HeaderTone.violet
      : widget.scope.brand != null
      ? HeaderTone.sun
      : HeaderTone.pop;

  Future<void> _applyFrom(CatalogQuery q) async {
    await _controller.applyQuery(q);
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  /// Header block, toolbar and the active filter chips (scrolls away).
  List<Widget> _top(
    BuildContext context,
    ListingState state,
    ScopeInfo? info,
    String title,
  ) {
    final hasDescription =
        info?.description != null && info!.description!.trim().isNotEmpty;
    return [
      _ScopeHeader(
        title: title,
        tone: _tone,
        count: state.loading && state.items.isEmpty
            ? null
            : (info?.productsCount ?? state.page.total),
        subcategories: info?.children ?? const [],
      ),
      if (hasDescription)
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.gutter,
            AppSpacing.smd,
            AppSpacing.gutter,
            0,
          ),
          child: Text(
            info.description!,
            style: context.text.bodyMedium?.copyWith(
              color: context.colors.onSurfaceVariant,
            ),
          ),
        ),
      _Toolbar(
        state: state,
        onSort: () => _sort(state),
        onFilter: () => _filter(state),
      ),
      if (state.query.activeFilters > 0)
        _ActiveFilters(
          scope: widget.scope,
          query: state.query,
          onChange: _applyFrom,
        ),
    ];
  }

  Widget _body(
    BuildContext context,
    ListingState state,
    ScopeInfo? info,
    String title,
  ) {
    final l10n = context.l10n;
    if (state.loading && state.items.isEmpty) {
      return ListView(
        physics: const NeverScrollableScrollPhysics(),
        children: [
          ..._top(context, state, info, title),
          const SizedBox(height: 640, child: ProductGridSkeleton(count: 4)),
        ],
      );
    }
    if (state.error != null) {
      return ErrorState(error: state.error, onRetry: _controller.refresh);
    }
    final top = _top(context, state, info, title);
    if (state.items.isEmpty) {
      final filtered = state.query.activeFilters > 0;
      final suggestions = widget.scope.isSearch
          ? ref.watch(homeProvider).asData?.value.recommended ??
                const <ProductSummary>[]
          : const <ProductSummary>[];
      return RefreshIndicator(
        onRefresh: _controller.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: AppSpacing.xl),
          children: [
            ...top,
            StateBlock(
              icon: Icons.search_rounded,
              tone: StateTone.violet,
              title: l10n.noProducts,
              message: filtered
                  ? l10n.noProductsFiltered
                  : widget.scope.isSearch
                  ? l10n.searchNoResultsBody
                  : null,
              actionLabel: filtered ? l10n.resetFilters : null,
              actionVariant: AppButtonVariant.outlined,
              onAction: filtered
                  ? () => _controller.applyQuery(
                      state.query.copyWith(
                        brandIds: const {},
                        valueIds: const {},
                        clearPrice: true,
                      ),
                    )
                  : null,
            ),
            if (suggestions.isNotEmpty)
              ProductRail(
                title: l10n.relatedProducts,
                products: suggestions,
                scope: 'search-empty',
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
          SliverList.list(children: top),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.gutter,
              AppSpacing.sm,
              AppSpacing.gutter,
              AppSpacing.gutter,
            ),
            sliver: SliverGrid.builder(
              gridDelegate: productGridDelegate(
                context,
                withFlash: anyFlash(state.items),
                withSale: anySale(state.items),
              ),
              itemCount: state.items.length,
              itemBuilder: (_, i) => ProductTile(
                key: ValueKey(state.items[i].id),
                product: state.items[i],
                heroScope: 'list',
                index: i,
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
                ? Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.4),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Text(
                          l10n.loading,
                          style: context.text.bodySmall?.copyWith(
                            color: context.extra.textMuted,
                          ),
                        ),
                      ],
                    ),
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

/// Toolbar: "تصفية" outline pill with a pop count badge and the sort pill
/// "ترتيب حسب: …".
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
    final c = context.colors;
    final filters = state.query.activeFilters;
    final pillStyle = OutlinedButton.styleFrom(
      minimumSize: const Size(44, 44),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      textStyle: context.text.labelMedium,
      foregroundColor: c.onSurface,
    );
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.md,
        AppSpacing.gutter,
        0,
      ),
      child: Row(
        children: [
          OutlinedButton(
            key: const ValueKey('listing-filter'),
            style: pillStyle,
            onPressed: onFilter,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.filter_alt_outlined, size: 18),
                const SizedBox(width: 6),
                Text(l10n.filters),
                if (filters > 0) ...[
                  const SizedBox(width: 6),
                  Container(
                    constraints: const BoxConstraints(minWidth: 20),
                    height: 20,
                    alignment: Alignment.center,
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    decoration: BoxDecoration(
                      color: context.extra.pop,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$filters',
                      style: context.textX.labelXs.copyWith(
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: OutlinedButton(
              key: const ValueKey('listing-sort'),
              style: pillStyle,
              onPressed: onSort,
              child: Row(
                children: [
                  const Icon(Icons.swap_vert_rounded, size: 18),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      l10n.sortByLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.text.bodySmall,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      sortLabel(l10n, state.query.sort),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Spacer(),
                  const Icon(Icons.keyboard_arrow_down_rounded, size: 18),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Active filter chips (brand-50 with ×) + "إعادة ضبط التصفية". Brand and
/// value names come from the facets already used by the filter sheet.
class _ActiveFilters extends ConsumerWidget {
  const _ActiveFilters({
    required this.scope,
    required this.query,
    required this.onChange,
  });

  final ListingScope scope;
  final CatalogQuery query;
  final ValueChanged<CatalogQuery> onChange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final facets = scope.customPage != null
        ? null
        : ref.watch(facetsProvider(scope)).asData?.value;
    final currency = ref.watch(currencyLabelProvider);
    Widget chip(String label, VoidCallback onRemove) => InputChip(
      label: Text(label),
      backgroundColor: x.brandSoft,
      side: BorderSide.none,
      labelStyle: context.textX.labelXs.copyWith(
        color: c.primary,
        fontSize: 12,
      ),
      deleteIcon: Icon(Icons.close_rounded, size: 16, color: c.primary),
      onDeleted: onRemove,
      deleteButtonTooltipMessage: l10n.clear,
    );
    final chips = <Widget>[
      if (facets != null)
        for (final b in facets.brands)
          if (query.brandIds.contains(b.id))
            chip(
              b.name,
              () => onChange(
                query.copyWith(brandIds: {...query.brandIds}..remove(b.id)),
              ),
            ),
      if (facets != null)
        for (final a in facets.attributes)
          for (final v in a.values)
            if (query.valueIds.contains(v.id))
              chip(
                v.label,
                () => onChange(
                  query.copyWith(valueIds: {...query.valueIds}..remove(v.id)),
                ),
              ),
      if (query.priceMin != null || query.priceMax != null)
        chip(
          l10n.money(
            [query.priceMin ?? '0', ?query.priceMax].join(' - '),
            currency,
          ),
          () => onChange(query.copyWith(clearPrice: true)),
        ),
    ];
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.smd,
        AppSpacing.gutter,
        0,
      ),
      child: Wrap(
        spacing: AppSpacing.sm,
        runSpacing: AppSpacing.sm,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          ...chips,
          TextButton(
            onPressed: () => onChange(
              query.copyWith(
                brandIds: const {},
                valueIds: const {},
                clearPrice: true,
              ),
            ),
            style: TextButton.styleFrom(
              foregroundColor: x.pop,
              textStyle: context.textX.labelXs.copyWith(fontSize: 12),
              minimumSize: const Size(44, 36),
            ),
            child: Text(l10n.resetFilters),
          ),
        ],
      ),
    );
  }
}

/// Colour header block: Display/M title, product count and sub-category
/// chips ("الكل" selected first).
class _ScopeHeader extends StatelessWidget {
  const _ScopeHeader({
    required this.title,
    required this.tone,
    required this.count,
    required this.subcategories,
  });

  final String title;
  final HeaderTone tone;
  final int? count;
  final List<CategoryNode> subcategories;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return ColorHeaderBlock(
      key: const ValueKey('listing-header'),
      title: title,
      tone: tone,
      subtitle: count == null ? null : l10n.productsCount(count!),
      child: subcategories.isEmpty
          ? null
          : SizedBox(
              height:
                  40 * MediaQuery.textScalerOf(context).scale(1).clamp(1, 1.4),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: subcategories.length + 1,
                separatorBuilder: (_, _) =>
                    const SizedBox(width: AppSpacing.sm),
                itemBuilder: (_, i) => i == 0
                    ? HeaderChip(
                        label: l10n.allLabel,
                        selected: true,
                        onTap: null,
                      )
                    : HeaderChip(
                        label: subcategories[i - 1].name,
                        onTap: () => context.push(
                          AppRoutes.category(subcategories[i - 1].slug),
                        ),
                      ),
              ),
            ),
    );
  }
}
