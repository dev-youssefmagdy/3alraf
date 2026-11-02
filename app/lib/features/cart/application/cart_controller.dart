import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../../tenant/application/tenant_controller.dart';
import '../data/cart_models.dart';
import '../data/cart_repository.dart';

final cartRepositoryProvider = Provider<CartRepository>(
  (ref) => CartRepository(ref.watch(dioProvider)),
);

class CartState {
  const CartState({
    this.cart,
    this.loading = false,
    this.error,
    this.code,
    this.codeError,
    this.busy = false,
    this.applyingCode = false,
  });

  /// Last server cart (with the discount applied when [code] is set).
  final Cart? cart;
  final bool loading;
  final ApiException? error;

  /// Discount code the server accepted.
  final String? code;
  final String? codeError;
  final bool busy;
  final bool applyingCode;

  int get count => cart?.count ?? 0;

  CartState copyWith({
    Cart? cart,
    bool? loading,
    Object? error = _keep,
    Object? code = _keep,
    Object? codeError = _keep,
    bool? busy,
    bool? applyingCode,
  }) => CartState(
    cart: cart ?? this.cart,
    loading: loading ?? this.loading,
    error: identical(error, _keep) ? this.error : error as ApiException?,
    code: identical(code, _keep) ? this.code : code as String?,
    codeError: identical(codeError, _keep)
        ? this.codeError
        : codeError as String?,
    busy: busy ?? this.busy,
    applyingCode: applyingCode ?? this.applyingCode,
  );
}

const Object _keep = Object();

final cartControllerProvider = NotifierProvider<CartController, CartState>(
  CartController.new,
);

/// Units in the cart, for the animated tab badge.
final cartCountProvider = Provider<int>(
  (ref) => ref.watch(cartControllerProvider.select((s) => s.count)),
);

/// The cart of the active store (customer cart when signed in, else the guest
/// cart of the persisted `X-Cart-Token`). Rebuilt - and so emptied and
/// reloaded - on tenant switch, locale change and sign-in/out. Totals are
/// always the server's; nothing is computed here.
class CartController extends Notifier<CartState> {
  int _gen = 0;

  CartRepository get _repo => ref.read(cartRepositoryProvider);

  @override
  CartState build() {
    final slug = ref.watch(tenantSlugProvider);
    ref.watch(localeProvider);
    ref.watch(authControllerProvider.select((s) => s.signedIn));
    _gen++;
    if (slug == null) return const CartState();
    Future.microtask(load);
    return const CartState(loading: true);
  }

  bool _stale(int gen) => !ref.mounted || gen != _gen;

  Future<void> load() async {
    final gen = _gen;
    state = state.copyWith(loading: state.cart == null, error: null);
    try {
      final cart = await _repo.show();
      if (_stale(gen)) return;
      state = state.copyWith(cart: cart, loading: false);
      if (state.code != null) await _requote(gen);
    } on ApiException catch (e) {
      if (_stale(gen)) return;
      state = state.copyWith(loading: false, error: e);
    }
  }

  /// Adds a product line or a bundle. Throws [ApiException] (e.g. 422 stock)
  /// so the calling screen can show it; returns the server message.
  Future<String?> add({
    int? productId,
    int variantId = 0,
    int? bundleId,
    int quantity = 1,
  }) async {
    final gen = _gen;
    final res = await _repo.add(
      productId: productId,
      variantId: variantId,
      bundleId: bundleId,
      quantity: quantity,
    );
    if (_stale(gen)) return res.message;
    state = state.copyWith(cart: res.cart, error: null);
    if (state.code != null) await _requote(gen);
    return res.message;
  }

  /// Re-reads the cart after a server-side add that returned no cart
  /// (gift finder).
  Future<void> refresh() => load();

  /// Stepper change: the count updates at once, the server cart replaces it.
  /// Returns an error to show (rolled back), or null.
  Future<ApiException?> setQuantity(CartLine line, int quantity) async {
    final gen = _gen;
    final before = state.cart;
    if (before == null || quantity < 1) return null;
    state = state.copyWith(
      cart: before.withItems([
        for (final l in before.items)
          l.id == line.id ? l.withQuantity(quantity) : l,
      ]),
      busy: true,
    );
    try {
      final cart = await _repo.setQuantity(line.id, quantity);
      if (_stale(gen)) return null;
      state = state.copyWith(cart: cart, busy: false);
      if (state.code != null) await _requote(gen);
      return null;
    } on ApiException catch (e) {
      if (_stale(gen)) return e;
      state = state.copyWith(cart: before, busy: false);
      return e;
    }
  }

  Future<ApiException?> remove(CartLine line) async {
    final gen = _gen;
    final before = state.cart;
    if (before == null) return null;
    state = state.copyWith(
      cart: before.withItems([
        for (final l in before.items)
          if (l.id != line.id) l,
      ]),
      busy: true,
    );
    try {
      final cart = await _repo.remove(line.id);
      if (_stale(gen)) return null;
      state = state.copyWith(cart: cart, busy: false);
      if (state.code != null) await _requote(gen);
      return null;
    } on ApiException catch (e) {
      if (_stale(gen)) return e;
      state = state.copyWith(cart: before, busy: false);
      return e;
    }
  }

  /// Validates + prices a discount code with `cart/quote`. A rejected code
  /// (422 on `discount_code`) is kept as [CartState.codeError].
  Future<bool> applyCode(String raw) async {
    final code = raw.trim();
    if (code.isEmpty) return false;
    final gen = _gen;
    state = state.copyWith(applyingCode: true, codeError: null);
    try {
      final cart = await _repo.quote({'discount_code': code});
      if (_stale(gen)) return false;
      state = state.copyWith(
        cart: cart,
        code: code,
        applyingCode: false,
        codeError: null,
      );
      return true;
    } on ApiException catch (e) {
      if (_stale(gen)) return false;
      state = state.copyWith(
        applyingCode: false,
        codeError: e.firstErrorFor('discount_code') ?? e.message,
      );
      return false;
    }
  }

  Future<void> clearCode() async {
    final gen = _gen;
    state = state.copyWith(code: null, codeError: null);
    try {
      final cart = await _repo.show();
      if (_stale(gen)) return;
      state = state.copyWith(cart: cart);
    } on ApiException {
      // keep the previous cart
    }
  }

  Future<void> _requote(int gen) async {
    final code = state.code;
    if (code == null) return;
    try {
      final cart = await _repo.quote({'discount_code': code});
      if (_stale(gen)) return;
      state = state.copyWith(cart: cart);
    } on ApiException {
      // The code no longer applies (e.g. minimum not met): drop it.
      if (_stale(gen)) return;
      state = state.copyWith(code: null);
    }
  }

  /// Sets the empty cart after a successful checkout.
  void clearLocal() {
    state = state.copyWith(cart: const Cart(), code: null, codeError: null);
  }
}
