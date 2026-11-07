import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/network_providers.dart';
import '../../catalog/application/catalog_providers.dart';
import '../data/content_models.dart';
import '../data/content_repository.dart';

final contentRepositoryProvider = Provider<ContentRepository>(
  (ref) => ContentRepository(ref.watch(dioProvider)),
);

final pagesProvider = FutureProvider<List<PageLink>>((ref) {
  watchStoreScope(ref);
  return ref.watch(contentRepositoryProvider).pages();
});

final customPagesProvider = FutureProvider<List<PageLink>>((ref) {
  watchStoreScope(ref);
  return ref.watch(contentRepositoryProvider).customPages();
});

final staticPageProvider = FutureProvider.autoDispose
    .family<StaticPage, String>((ref, slug) {
      watchStoreScope(ref);
      return ref.watch(contentRepositoryProvider).page(slug);
    });

final blogPostProvider = FutureProvider.autoDispose.family<BlogPost, String>((
  ref,
  slug,
) {
  watchStoreScope(ref);
  return ref.watch(contentRepositoryProvider).post(slug);
});

final launchProvider = FutureProvider.autoDispose.family<Launch, String>((
  ref,
  code,
) {
  watchStoreScope(ref);
  return ref.watch(contentRepositoryProvider).launch(code);
});

class BlogState {
  const BlogState({
    this.posts = const [],
    this.page = 0,
    this.hasMore = true,
    this.loading = false,
    this.loadingMore = false,
    this.error,
    this.loadMoreFailed = false,
  });

  final List<BlogPost> posts;
  final int page;
  final bool hasMore;
  final bool loading;
  final bool loadingMore;
  final ApiException? error;
  final bool loadMoreFailed;
}

final blogControllerProvider =
    NotifierProvider.autoDispose<BlogController, BlogState>(BlogController.new);

/// Paged blog list (infinite scroll), rebuilt on language change.
class BlogController extends Notifier<BlogState> {
  int _gen = 0;

  @override
  BlogState build() {
    watchStoreScope(ref);
    _gen++;
    Future.microtask(refresh);
    return const BlogState(loading: true);
  }

  Future<void> refresh() async {
    final gen = _gen;
    state = BlogState(posts: state.posts, loading: true);
    try {
      final p = await ref.read(contentRepositoryProvider).blog();
      if (!ref.mounted || gen != _gen) return;
      state = BlogState(
        posts: p.posts,
        page: p.currentPage,
        hasMore: p.hasMore,
      );
    } on ApiException catch (e) {
      if (!ref.mounted || gen != _gen) return;
      state = BlogState(posts: state.posts, hasMore: false, error: e);
    }
  }

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    final gen = _gen;
    final before = state;
    state = BlogState(
      posts: before.posts,
      page: before.page,
      loadingMore: true,
    );
    try {
      final p = await ref
          .read(contentRepositoryProvider)
          .blog(page: before.page + 1);
      if (!ref.mounted || gen != _gen) return;
      state = BlogState(
        posts: [...before.posts, ...p.posts],
        page: p.currentPage,
        hasMore: p.hasMore,
      );
    } on ApiException {
      if (!ref.mounted || gen != _gen) return;
      state = BlogState(
        posts: before.posts,
        page: before.page,
        loadMoreFailed: true,
      );
    }
  }
}
