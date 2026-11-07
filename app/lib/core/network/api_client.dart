import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/session_storage.dart';
import 'api_context.dart';
import 'interceptors.dart';

/// Builds the single [Dio] used by every repository.
///
/// Interceptor order matters: request hooks run top to bottom, error hooks
/// too, so [RetryInterceptor] sees raw failures before [EnvelopeInterceptor]
/// converts them to [ApiException].
Dio buildDio({
  required AppConfig config,
  required ApiContext context,
  required SessionStorage storage,
  void Function()? onUnauthorized,
  HttpClientAdapter? adapter,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: config.apiV1,
      connectTimeout: const Duration(seconds: 15),
      sendTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      validateStatus: (s) => s != null && s >= 200 && s < 300,
    ),
  );
  if (adapter != null) dio.httpClientAdapter = adapter;
  dio.interceptors.addAll([
    LocaleInterceptor(context),
    CartTokenInterceptor(storage),
    AuthInterceptor(storage, onUnauthorized: onUnauthorized),
    RetryInterceptor(dio),
    EnvelopeInterceptor(),
  ]);
  return dio;
}
