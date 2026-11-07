import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';

import '../error/api_exception.dart';
import '../storage/session_storage.dart';
import 'api_context.dart';

/// Adds `Accept` and `Accept-Language` (current locale) to every request.
class LocaleInterceptor extends Interceptor {
  LocaleInterceptor(this._context);

  final ApiContext _context;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept'] = 'application/json';
    options.headers['Accept-Language'] = _context.locale;
    handler.next(options);
  }
}

/// Sends the guest cart token and persists the one echoed by the server
/// (`X-Cart-Token` response header).
class CartTokenInterceptor extends Interceptor {
  CartTokenInterceptor(this._storage);

  final SessionStorage _storage;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _storage.cartToken();
    if (token != null && token.isNotEmpty) {
      options.headers['X-Cart-Token'] = token;
    }
    handler.next(options);
  }

  Future<void> _capture(Response<dynamic> response) async {
    final token = response.headers.value('x-cart-token');
    if (token == null || token.isEmpty) return;
    if (await _storage.cartToken() != token) {
      await _storage.saveCartToken(token);
    }
  }

  @override
  Future<void> onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) async {
    await _capture(response);
    handler.next(response);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final r = err.response;
    if (r != null) await _capture(r);
    handler.next(err);
  }
}

/// Adds the Bearer token and reacts to 401 by clearing the session.
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._storage, {this.onUnauthorized});

  final SessionStorage _storage;

  /// Called after a 401 on an authenticated request, once the stored token
  /// has been removed.
  final void Function()? onUnauthorized;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // An explicit Authorization header (e.g. affiliate token) wins.
    if (!options.headers.containsKey('Authorization')) {
      final token = await _storage.authToken();
      if (token != null && token.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final o = err.requestOptions;
    if (err.response?.statusCode == 401 &&
        o.headers.containsKey('Authorization') &&
        o.extra[kSkipUnauthorizedExtra] != true) {
      await _storage.clearAuthToken();
      onUnauthorized?.call();
    }
    handler.next(err);
  }
}

/// Retries idempotent GETs on connection errors, timeouts and 502/503/504.
class RetryInterceptor extends Interceptor {
  RetryInterceptor(
    this._dio, {
    this.maxRetries = 2,
    this.delay = _defaultDelay,
  });

  final Dio _dio;
  final int maxRetries;
  final Future<void> Function(int attempt) delay;

  static const _retryKey = 'retry_count';

  static Future<void> _defaultDelay(int attempt) => Future<void>.delayed(
    Duration(milliseconds: 300 * pow(2, attempt - 1).toInt()),
  );

  bool _retryable(DioException e) {
    if (e.requestOptions.method.toUpperCase() != 'GET') return false;
    if (e.requestOptions.extra['no_retry'] == true) return false;
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.connectionError:
        return true;
      case DioExceptionType.badResponse:
        final s = e.response?.statusCode;
        return s == 502 || s == 503 || s == 504;
      default:
        return false;
    }
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final count = (err.requestOptions.extra[_retryKey] as int?) ?? 0;
    if (!_retryable(err) || count >= maxRetries) {
      return handler.next(err);
    }
    await delay(count + 1);
    final options = err.requestOptions..extra[_retryKey] = count + 1;
    try {
      handler.resolve(await _dio.fetch<dynamic>(options));
    } on DioException catch (e) {
      handler.next(e);
    }
  }
}

/// Unwraps the `{success, message, data, meta}` envelope.
///
/// * success: `response.data` becomes the envelope's `data`; `message` and
///   `meta` are exposed via [EnvelopeResponse].
/// * failure (non-2xx or `success:false`): rejected with a [DioException]
///   whose `error` is a typed [ApiException].
/// Non-enveloped bodies (PDF bytes, `/health`) pass through untouched.
class EnvelopeInterceptor extends Interceptor {
  static const messageKey = 'envelope_message';
  static const metaKey = 'envelope_meta';

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    final body = response.data;
    if (body is Map && body.containsKey('success')) {
      if (body['success'] == false) {
        return handler.reject(
          DioException(
            requestOptions: response.requestOptions,
            response: response,
            type: DioExceptionType.badResponse,
            error: ApiException.fromResponse(response.statusCode, body),
          ),
        );
      }
      response.extra[messageKey] = body['message'];
      final meta = body['meta'];
      response.extra[metaKey] = meta is Map
          ? Map<String, dynamic>.from(meta)
          : const <String, dynamic>{};
      response.data = body['data'];
    }
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.error is ApiException) return handler.next(err);
    handler.next(
      DioException(
        requestOptions: err.requestOptions,
        response: err.response,
        type: err.type,
        error: ApiException.fromDio(err),
        stackTrace: err.stackTrace,
        message: err.message,
      ),
    );
  }
}

/// Convenience accessors for the unwrapped envelope.
extension EnvelopeResponse on Response<dynamic> {
  String? get envelopeMessage =>
      extra[EnvelopeInterceptor.messageKey] as String?;

  Map<String, dynamic> get envelopeMeta =>
      (extra[EnvelopeInterceptor.metaKey] as Map<String, dynamic>?) ??
      const <String, dynamic>{};
}
