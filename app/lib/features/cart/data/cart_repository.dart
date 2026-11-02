import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/interceptors.dart';
import '../../../core/network/json.dart';
import 'cart_models.dart';

/// A cart response plus the server message (e.g. "Added to cart.").
class CartResult {
  const CartResult(this.cart, this.message);

  final Cart cart;
  final String? message;
}

class CartRepository {
  const CartRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  CartResult _parse(Response<Object?> r) =>
      CartResult(Cart.fromJson(asMap(r.data)), r.envelopeMessage);

  Future<Cart> show() => _guard(() async {
    final r = await _dio.get<Object?>('/cart');
    return _parse(r).cart;
  });

  Future<CartResult> add({
    int? productId,
    int variantId = 0,
    int? bundleId,
    int quantity = 1,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/cart/items',
      data: {
        'product_id': ?productId,
        'bundle_id': ?bundleId,
        if (bundleId == null) 'variant_id': variantId,
        'quantity': quantity,
      },
    );
    return _parse(r);
  });

  Future<Cart> setQuantity(int itemId, int quantity) => _guard(() async {
    final r = await _dio.put<Object?>(
      '/cart/items/$itemId',
      data: {'quantity': quantity},
    );
    return _parse(r).cart;
  });

  Future<Cart> remove(int itemId) => _guard(() async {
    final r = await _dio.delete<Object?>('/cart/items/$itemId');
    return _parse(r).cart;
  });

  /// `POST cart/quote`; a bad code is a 422 on `discount_code`.
  Future<Cart> quote(Map<String, Object?> selection) => _guard(() async {
    final r = await _dio.post<Object?>('/cart/quote', data: selection);
    return _parse(r).cart;
  });
}
