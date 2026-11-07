import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../data/order_models.dart';
import '../data/orders_repository.dart';

final ordersRepositoryProvider = Provider<OrdersRepository>(
  (ref) => OrdersRepository(ref.watch(dioProvider)),
);

/// One order by public token (detail + timeline). Scoped to store + session.
final orderProvider = FutureProvider.autoDispose.family<Order, String>((
  ref,
  token,
) {
  ref.watch(localeProvider);
  ref.watch(authControllerProvider.select((s) => s.signedIn));
  return ref.watch(ordersRepositoryProvider).show(token);
});

class OrdersState {
  const OrdersState({
    this.orders = const [],
    this.page = 0,
    this.hasMore = true,
    this.loading = false,
    this.loadingMore = false,
    this.error,
    this.loadMoreFailed = false,
  });

  final List<Order> orders;
  final int page;
  final bool hasMore;
  final bool loading;
  final bool loadingMore;
  final ApiException? error;
  final bool loadMoreFailed;
}

final ordersControllerProvider =
    NotifierProvider.autoDispose<OrdersController, OrdersState>(
      OrdersController.new,
    );

/// Paged "my orders" list (infinite scroll).
class OrdersController extends Notifier<OrdersState> {
  int _gen = 0;

  @override
  OrdersState build() {
    ref.watch(localeProvider);
    final signedIn = ref.watch(
      authControllerProvider.select((s) => s.signedIn),
    );
    _gen++;
    if (!signedIn) return const OrdersState(hasMore: false);
    Future.microtask(refresh);
    return const OrdersState(loading: true);
  }

  Future<void> refresh() async {
    final gen = _gen;
    state = OrdersState(orders: state.orders, loading: true);
    try {
      final p = await ref.read(ordersRepositoryProvider).list();
      if (!ref.mounted || gen != _gen) return;
      state = OrdersState(
        orders: p.orders,
        page: p.currentPage,
        hasMore: p.hasMore,
      );
    } on ApiException catch (e) {
      if (!ref.mounted || gen != _gen) return;
      state = OrdersState(orders: state.orders, hasMore: false, error: e);
    }
  }

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    final gen = _gen;
    final before = state;
    state = OrdersState(
      orders: before.orders,
      page: before.page,
      hasMore: true,
      loadingMore: true,
    );
    try {
      final p = await ref
          .read(ordersRepositoryProvider)
          .list(page: before.page + 1);
      if (!ref.mounted || gen != _gen) return;
      state = OrdersState(
        orders: [...before.orders, ...p.orders],
        page: p.currentPage,
        hasMore: p.hasMore,
      );
    } on ApiException {
      if (!ref.mounted || gen != _gen) return;
      state = OrdersState(
        orders: before.orders,
        page: before.page,
        hasMore: true,
        loadMoreFailed: true,
      );
    }
  }
}
