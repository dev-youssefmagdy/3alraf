import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../auth/application/auth_controller.dart';
import '../../catalog/application/catalog_providers.dart';
import '../../catalog/data/catalog_models.dart';

class WishlistState {
  const WishlistState({
    this.items = const [],
    this.loading = false,
    this.loaded = false,
    this.error,
  });

  final List<ProductSummary> items;
  final bool loading;
  final bool loaded;
  final Object? error;

  Set<int> get ids => {for (final p in items) p.id};
}

final wishlistProvider = NotifierProvider<WishlistController, WishlistState>(
  WishlistController.new,
);

/// Ids of wishlisted products (for the hearts on cards).
final wishlistIdsProvider = Provider<Set<int>>(
  (ref) => ref.watch(wishlistProvider.select((s) => s.ids)),
);

/// The signed-in customer's wishlist of the active store. Rebuilds (and so
/// is emptied) on locale change and sign-in/out.
class WishlistController extends Notifier<WishlistState> {
  @override
  WishlistState build() {
    final signedIn = ref.watch(
      authControllerProvider.select((s) => s.signedIn),
    );
    if (!signedIn) return const WishlistState();
    Future.microtask(load);
    return const WishlistState(loading: true);
  }

  Future<void> load() async {
    state = WishlistState(
      items: state.items,
      loading: true,
      loaded: state.loaded,
    );
    try {
      final items = await ref.read(catalogRepositoryProvider).wishlist();
      if (!ref.mounted) return;
      state = WishlistState(items: items, loaded: true);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      state = WishlistState(items: state.items, loaded: state.loaded, error: e);
    }
  }

  /// Heart tap. The caller guarantees a session (guests are sent to login).
  /// Optimistic for removal (the card disappears from the list at once);
  /// rolled back on failure. Returns the new state, `null` on failure.
  Future<bool?> toggle(int productId, {ProductSummary? product}) async {
    final before = state;
    final wasIn = before.ids.contains(productId);
    if (wasIn) {
      state = WishlistState(
        items: before.items.where((p) => p.id != productId).toList(),
        loaded: before.loaded,
      );
    } else if (product != null) {
      state = WishlistState(
        items: [product, ...before.items],
        loaded: before.loaded,
      );
    }
    try {
      final now = await ref
          .read(catalogRepositoryProvider)
          .toggleWishlist(productId);
      if (!ref.mounted) return now;
      // Added without a card (product page): reload to get its card.
      if (now && !wasIn && product == null) await load();
      return now;
    } on ApiException {
      if (ref.mounted) state = before;
      return null;
    }
  }
}
