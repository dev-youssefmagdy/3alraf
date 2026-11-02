import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/interceptors.dart';
import '../../../core/network/json.dart';
import 'order_models.dart';

class OrdersRepository {
  const OrdersRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<OrdersPageData> list({int page = 1, int perPage = 10}) =>
      _guard(() async {
        final r = await _dio.get<Object?>(
          '/orders',
          queryParameters: {'page': page, 'per_page': perPage},
        );
        final pg = asMap(r.envelopeMeta['pagination']);
        return OrdersPageData(
          orders: asMapList(r.data).map(Order.fromJson).toList(),
          currentPage: intOf(pg['current_page'], page),
          lastPage: intOf(pg['last_page'], page),
        );
      });

  Future<Order> show(String token) => _guard(() async {
    final r = await _dio.get<Object?>('/orders/$token');
    return Order.fromJson(asMap(r.data));
  });

  /// New gateway link for an unpaid online order.
  Future<String> pay(String token) => _guard(() async {
    final r = await _dio.post<Object?>('/orders/$token/pay');
    final url = strOrNull(asMap(r.data)['payment_url']);
    if (url == null) {
      throw const ApiException(
        kind: ApiErrorKind.unknown,
        message: 'No payment url',
      );
    }
    return url;
  });

  /// Not authoritative: `paid` flips once the signed webhook marked it.
  Future<PaymentReturn> paymentReturn(String token) => _guard(() async {
    final r = await _dio.get<Object?>('/payments/return/$token');
    return PaymentReturn(
      order: Order.fromJson(asMap(r.data)),
      paid: boolOf(r.envelopeMeta['paid']),
    );
  });

  Future<Order> uploadProof(String token, String filePath) => _guard(() async {
    final form = FormData.fromMap({
      'proof': await MultipartFile.fromFile(filePath),
    });
    final r = await _dio.post<Object?>('/orders/$token/proof', data: form);
    return Order.fromJson(asMap(r.data));
  });

  Future<Order> cancel(String token, String reasonCode) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/orders/$token/cancel',
      data: {'reason_code': reasonCode},
    );
    return Order.fromJson(asMap(r.data));
  });

  Future<ReturnInfo> requestReturn(
    String token, {
    required Map<int, int> quantities,
    String? reason,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/orders/$token/return',
      data: {
        'items': [
          for (final e in quantities.entries)
            if (e.value > 0) {'order_item_id': e.key, 'quantity': e.value},
        ],
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
    );
    return ReturnInfo.fromJson(asMap(r.data));
  });

  /// Downloads the invoice PDF to a temp file and returns its path.
  Future<String> downloadInvoice(String token, String number) =>
      _guard(() async {
        final r = await _dio.get<List<int>>(
          '/orders/$token/invoice',
          options: Options(responseType: ResponseType.bytes),
        );
        final bytes = Uint8List.fromList(r.data ?? const []);
        final dir = await getTemporaryDirectory();
        final file = File('${dir.path}/invoice-$number.pdf');
        await file.writeAsBytes(bytes, flush: true);
        return file.path;
      });
}
