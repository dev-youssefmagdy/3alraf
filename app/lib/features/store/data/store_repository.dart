import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import 'store_models.dart';

/// Bootstrap calls: `GET /app-config` (switches + shop basics) and the full
/// shop profile `GET /store`.
class StoreRepository {
  const StoreRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  static Map<String, Object?> _obj(Object? data) =>
      data is Map ? data.map((k, v) => MapEntry('$k', v)) : const {};

  /// `GET /app-config?version=` - force update / maintenance + `store`.
  Future<AppConfigInfo> fetchAppConfig(String version) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/app-config',
      queryParameters: {'version': version},
    );
    return AppConfigInfo.fromJson(_obj(r.data));
  });

  /// `GET /store` - branding, contact, features, app links.
  Future<StoreProfile> fetchProfile() => _guard(() async {
    final r = await _dio.get<Object?>('/store');
    return StoreProfile.fromJson(_obj(r.data));
  });
}
