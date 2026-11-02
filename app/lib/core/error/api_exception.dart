import 'package:dio/dio.dart';

enum ApiErrorKind {
  network,
  timeout,
  unauthorized,
  forbidden,
  featureDisabled,
  notFound,
  validation,
  rateLimited,
  server,
  cancelled,
  unknown,
}

/// Typed failure produced by the networking layer. Repositories surface this
/// (never a raw [DioException]) to the presentation layer.
class ApiException implements Exception {
  const ApiException({
    required this.kind,
    required this.message,
    this.statusCode,
    this.errors = const {},
    this.retryAfter,
    this.code,
  });

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;

  /// Field -> messages, for 422 responses.
  final Map<String, List<String>> errors;
  final Duration? retryAfter;

  /// Machine code from the envelope (`error`), e.g. `feature_disabled`.
  final String? code;

  bool get isUnauthorized => kind == ApiErrorKind.unauthorized;
  bool get isNetwork =>
      kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  String? firstErrorFor(String field) {
    final list = errors[field];
    return (list == null || list.isEmpty) ? null : list.first;
  }

  /// Extracts the [ApiException] carried by a [DioException] (set by the
  /// envelope interceptor), or maps any other object to [ApiErrorKind.unknown].
  static ApiException from(Object error) {
    if (error is ApiException) return error;
    if (error is DioException) {
      final inner = error.error;
      if (inner is ApiException) return inner;
      return fromDio(error);
    }
    return ApiException(kind: ApiErrorKind.unknown, message: '$error');
  }

  /// Builds an exception from a raw (non-enveloped) Dio failure.
  static ApiException fromDio(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.transformTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException(
          kind: ApiErrorKind.timeout,
          message: 'timeout',
        );
      case DioExceptionType.connectionError:
        return const ApiException(
          kind: ApiErrorKind.network,
          message: 'network',
        );
      case DioExceptionType.cancel:
        return const ApiException(
          kind: ApiErrorKind.cancelled,
          message: 'cancelled',
        );
      case DioExceptionType.badResponse:
      case DioExceptionType.badCertificate:
      case DioExceptionType.unknown:
        return fromResponse(
          e.response?.statusCode,
          e.response?.data,
          e.response?.headers.value('retry-after'),
        );
    }
  }

  /// Maps an HTTP status + (possibly enveloped) body to an exception.
  static ApiException fromResponse(
    int? status,
    Object? body, [
    String? retryAfterHeader,
  ]) {
    String? message;
    String? code;
    final errors = <String, List<String>>{};
    if (body is Map) {
      final m = body['message'];
      if (m is String && m.isNotEmpty) message = m;
      final c = body['error'];
      if (c is String) code = c;
      final errs = body['errors'];
      if (errs is Map) {
        errs.forEach((k, v) {
          if (v is List) {
            errors['$k'] = v.map((e) => '$e').toList();
          } else if (v != null) {
            errors['$k'] = ['$v'];
          }
        });
      }
    }
    final kind = switch (status) {
      401 => ApiErrorKind.unauthorized,
      403 =>
        code == 'feature_disabled'
            ? ApiErrorKind.featureDisabled
            : ApiErrorKind.forbidden,
      404 => ApiErrorKind.notFound,
      422 => ApiErrorKind.validation,
      429 => ApiErrorKind.rateLimited,
      final int s when s >= 500 => ApiErrorKind.server,
      null => ApiErrorKind.network,
      _ => ApiErrorKind.unknown,
    };
    final secs = int.tryParse(retryAfterHeader ?? '');
    return ApiException(
      kind: kind,
      message: message ?? 'HTTP ${status ?? '-'}',
      statusCode: status,
      errors: errors,
      retryAfter: secs == null ? null : Duration(seconds: secs),
      code: code,
    );
  }

  @override
  String toString() => 'ApiException($kind, $statusCode, $message)';
}
