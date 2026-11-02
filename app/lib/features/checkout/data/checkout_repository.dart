import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/interceptors.dart';
import '../../../core/network/json.dart';
import '../../cart/data/cart_models.dart';
import '../../orders/data/order_models.dart';
import 'checkout_models.dart';

class CheckoutRepository {
  const CheckoutRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<List<ShippingOption>> shipping(int cityId) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/checkout/shipping',
      queryParameters: {'city_id': cityId},
    );
    return asMapList(r.data).map(ShippingOption.fromJson).toList();
  });

  Future<List<PackagingOption>> packaging() => _guard(() async {
    final r = await _dio.get<Object?>('/checkout/packaging');
    return asMapList(r.data).map(PackagingOption.fromJson).toList();
  });

  Future<PaymentMethods> paymentMethods() => _guard(() async {
    final r = await _dio.get<Object?>('/checkout/payment-methods');
    return PaymentMethods(
      methods: asMapList(r.data).map(PaymentMethod.fromJson).toList(),
      codCollectShippingUpfront: boolOf(
        r.envelopeMeta['cod_collect_shipping_upfront'],
      ),
    );
  });

  /// Request body: JSON, or multipart when a proof file is attached.
  Future<Object> _body(CheckoutSelection s) async {
    final json = s.toJson();
    final proof = s.proofPath;
    if (proof == null) return json;
    return FormData.fromMap({
      for (final e in json.entries) e.key: '${e.value}',
      'proof': await MultipartFile.fromFile(proof),
    });
  }

  Future<Cart> quote(CheckoutSelection s) => _guard(() async {
    final r = await _dio.post<Object?>('/cart/quote', data: s.toJson());
    return Cart.fromJson(asMap(r.data));
  });

  /// 200 = placeable; 422 -> [ApiException] with field errors.
  Future<Cart> validate(CheckoutSelection s) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/checkout/validate',
      data: await _body(s),
    );
    return Cart.fromJson(asMap(r.data));
  });

  Future<PlacedOrder> place(CheckoutSelection s) => _guard(() async {
    final r = await _dio.post<Object?>('/checkout', data: await _body(s));
    final meta = r.envelopeMeta;
    return PlacedOrder(
      order: Order.fromJson(asMap(r.data)),
      nextAction: nextActionOf(strOrNull(meta['next_action'])),
      paymentUrl: strOrNull(meta['payment_url']),
    );
  });
}
