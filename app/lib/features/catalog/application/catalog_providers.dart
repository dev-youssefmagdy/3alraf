import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/storage/storage_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';
import '../data/product_models.dart';

final catalogRepositoryProvider = Provider<CatalogRepository>(
  (ref) => CatalogRepository(ref.watch(dioProvider)),
);

/// Every store-content provider calls this first so nothing survives a
/// language change (content is localised server side).
void watchStoreScope(Ref ref) {
  ref.watch(localeProvider);
}

/// Same, plus the session (prices are the same but `in_wishlist` and
/// `can_review` depend on the customer).
void watchCustomerScope(Ref ref) {
  watchStoreScope(ref);
  ref.watch(authControllerProvider.select((s) => s.signedIn));
}

final homeProvider = FutureProvider<HomeData>((ref) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).home();
});

final menuProvider = FutureProvider<List<CategoryNode>>((ref) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).menu();
});

final brandsProvider = FutureProvider<List<Brand>>((ref) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).brands();
});

final bundlesProvider = FutureProvider<List<Bundle>>((ref) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).bundles();
});

final bundleProvider = FutureProvider.autoDispose.family<Bundle, String>((
  ref,
  slug,
) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).bundle(slug);
});

final productProvider = FutureProvider.autoDispose
    .family<ProductDetail, String>((ref, slug) {
      watchCustomerScope(ref);
      return ref.watch(catalogRepositoryProvider).product(slug);
    });

final suggestProvider = FutureProvider.autoDispose
    .family<SuggestResult, String>((ref, q) {
      watchStoreScope(ref);
      return ref.watch(catalogRepositoryProvider).suggest(q);
    });

// -- listing -----------------------------------------------------------------

/// Which collection a listing page shows (value-equal, keys the family).
class ListingScope {
  const ListingScope({
    this.category,
    this.brand,
    this.tag,
    this.q,
    this.customPage,
  });

  final String? category;
  final String? brand;
  final String? tag;
  final String? q;
  final String? customPage;

  CatalogQuery toQuery() =>
      CatalogQuery(category: category, brand: brand, tag: tag, q: q);

  bool get isSearch => q != null;

  @override
  bool operator ==(Object other) =>
      other is ListingScope &&
      other.category == category &&
      other.brand == brand &&
      other.tag == tag &&
      other.q == q &&
      other.customPage == customPage;

  @override
  int get hashCode => Object.hash(category, brand, tag, q, customPage);
}

/// Header info for category/brand/tag pages (custom pages and search get
/// their title from the listing itself).
final scopeInfoProvider = FutureProvider.autoDispose
    .family<ScopeInfo?, ListingScope>((ref, scope) {
      watchStoreScope(ref);
      final repo = ref.watch(catalogRepositoryProvider);
      if (scope.category != null) return repo.category(scope.category!);
      if (scope.brand != null) return repo.brand(scope.brand!);
      if (scope.tag != null) return repo.tag(scope.tag!);
      return Future<ScopeInfo?>.value();
    });

/// Filter options for the scope (not narrowed by the active filters, so a
/// chosen option never disappears from the sheet).
final facetsProvider = FutureProvider.autoDispose.family<Facets, ListingScope>((
  ref,
  scope,
) {
  watchStoreScope(ref);
  return ref.watch(catalogRepositoryProvider).facets(scope.toQuery());
});

class ListingState {
  const ListingState({
    required this.query,
    this.items = const [],
    this.page = const PageInfo(),
    this.loading = true,
    this.loadingMore = false,
    this.error,
    this.loadMoreError,
    this.title,
  });

  final CatalogQuery query;
  final List<ProductSummary> items;
  final PageInfo page;
  final bool loading;
  final bool loadingMore;

  /// First-page failure (full error state).
  final Object? error;

  /// A later page failed (inline retry row; items stay).
  final Object? loadMoreError;

  /// Custom page title from the API.
  final String? title;

  bool get hasMore => page.hasMore;

  ListingState copyWith({
    CatalogQuery? query,
    List<ProductSummary>? items,
    PageInfo? page,
    bool? loading,
    bool? loadingMore,
    Object? error,
    bool clearError = false,
    Object? loadMoreError,
    bool clearLoadMoreError = false,
    String? title,
  }) => ListingState(
    query: query ?? this.query,
    items: items ?? this.items,
    page: page ?? this.page,
    loading: loading ?? this.loading,
    loadingMore: loadingMore ?? this.loadingMore,
    error: clearError ? null : (error ?? this.error),
    loadMoreError: clearLoadMoreError
        ? null
        : (loadMoreError ?? this.loadMoreError),
    title: title ?? this.title,
  );
}

final listingProvider = NotifierProvider.autoDispose
    .family<ListingController, ListingState, ListingScope>(
      ListingController.new,
    );

/// Infinite-scroll listing with sort/filter changes. Rebuilt (and so
/// refetched) when the language changes.
class ListingController extends Notifier<ListingState> {
  ListingController(this.scope);

  final ListingScope scope;
  int _generation = 0;

  CatalogRepository get _repo => ref.read(catalogRepositoryProvider);

  @override
  ListingState build() {
    watchStoreScope(ref);
    final initial = ListingState(query: scope.toQuery());
    Future.microtask(() => _load(initial.query, 1));
    return initial;
  }

  Future<void> _load(CatalogQuery query, int page) async {
    final gen = ++_generation;
    try {
      final PagedResult<ProductSummary> result;
      String? title;
      if (scope.customPage != null) {
        final r = await _repo.customPage(scope.customPage!, query, page: page);
        result = r.products;
        title = r.title;
      } else {
        result = await _repo.catalog(query, page: page);
      }
      if (gen != _generation || !ref.mounted) return;
      state = state.copyWith(
        query: query,
        items: page == 1 ? result.items : [...state.items, ...result.items],
        page: result.page,
        loading: false,
        loadingMore: false,
        clearError: true,
        clearLoadMoreError: true,
        title: title,
      );
    } on ApiException catch (e) {
      if (gen != _generation || !ref.mounted) return;
      state = page == 1
          ? state.copyWith(
              query: query,
              items: const [],
              loading: false,
              loadingMore: false,
              error: e,
            )
          : state.copyWith(loadingMore: false, loadMoreError: e);
    }
  }

  /// Sort / filter change: restarts from page 1.
  Future<void> applyQuery(CatalogQuery query) {
    state = state.copyWith(
      query: query,
      items: const [],
      loading: true,
      clearError: true,
      clearLoadMoreError: true,
    );
    return _load(query, 1);
  }

  Future<void> refresh() {
    state = state.copyWith(
      loading: state.items.isEmpty,
      clearError: true,
      clearLoadMoreError: true,
    );
    return _load(state.query, 1);
  }

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    state = state.copyWith(loadingMore: true, clearLoadMoreError: true);
    await _load(state.query, state.page.currentPage + 1);
  }
}

// -- reviews list -----------------------------------------------------------

class ReviewsState {
  const ReviewsState({
    this.items = const [],
    this.page = const PageInfo(),
    this.loading = true,
    this.error,
  });
  final List<Review> items;
  final PageInfo page;
  final bool loading;
  final Object? error;
}

final reviewsProvider = NotifierProvider.autoDispose
    .family<ReviewsController, ReviewsState, String>(ReviewsController.new);

class ReviewsController extends Notifier<ReviewsState> {
  ReviewsController(this.slug);
  final String slug;

  @override
  ReviewsState build() {
    watchStoreScope(ref);
    Future.microtask(() => _load(1));
    return const ReviewsState();
  }

  Future<void> _load(int page) async {
    try {
      final r = await ref
          .read(catalogRepositoryProvider)
          .reviews(slug, page: page);
      if (!ref.mounted) return;
      state = ReviewsState(
        items: page == 1 ? r.items : [...state.items, ...r.items],
        page: r.page,
        loading: false,
      );
    } on ApiException catch (e) {
      state = ReviewsState(
        items: state.items,
        page: state.page,
        loading: false,
        error: e,
      );
    }
  }

  Future<void> loadMore() async {
    if (state.loading || !state.page.hasMore) return;
    await _load(state.page.currentPage + 1);
  }
}

// -- recent searches ---------------------------------------------------------

final recentSearchesProvider =
    NotifierProvider<RecentSearchesController, List<String>>(
      RecentSearchesController.new,
    );

class RecentSearchesController extends Notifier<List<String>> {
  static const max = 8;

  @override
  List<String> build() => ref.read(preferencesProvider).recentSearches;

  Future<void> add(String term) async {
    final t = term.trim();
    if (t.length < 2) return;
    state = [t, ...state.where((e) => e != t)].take(max).toList();
    await ref.read(preferencesProvider).setRecentSearches(state);
  }

  Future<void> clear() async {
    state = const [];
    await ref.read(preferencesProvider).setRecentSearches(const []);
  }
}

/// Home popup banner: shown once per app session.
final popupShownProvider = NotifierProvider<PopupShown, bool>(PopupShown.new);

class PopupShown extends Notifier<bool> {
  @override
  bool build() => false;

  void markShown() => state = true;
}
