import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/api_context.dart';
import 'tenant_models.dart';

/// Central-host bootstrap calls plus the per-store profile.
class TenantRepository {
  const TenantRepository(this._dio);

  final Dio _dio;

  static final _central = Options(extra: {kCentralExtra: true});

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  /// `GET /tenants` - active stores.
  Future<List<Tenant>> fetchTenants() => _guard(() async {
    final r = await _dio.get<Object?>('/tenants', options: _central);
    final data = r.data;
    if (data is! List) return const [];
    return data
        .whereType<Map<Object?, Object?>>()
        .map((m) => Tenant.fromJson(m.map((k, v) => MapEntry('$k', v))))
        .where((t) => t.slug.isNotEmpty)
        .toList();
  });

  /// `GET /app-config?version=` - force update / maintenance.
  Future<AppConfigInfo> fetchAppConfig(String version) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/app-config',
      queryParameters: {'version': version},
      options: _central,
    );
    final data = r.data;
    return AppConfigInfo.fromJson(
      data is Map ? data.map((k, v) => MapEntry('$k', v)) : const {},
    );
  });

  /// `GET /store` for the active tenant (needs `ApiContext.tenantSlug`).
  Future<StoreInfo> fetchStore() => _guard(() async {
    final r = await _dio.get<Object?>('/store');
    final data = r.data;
    return StoreInfo.fromJson(
      data is Map ? data.map((k, v) => MapEntry('$k', v)) : const {},
    );
  });

  /// Best-effort token revocation for the tenant being left. Never throws
  /// and never triggers the 401 handler.
  Future<void> logoutRemote() async {
    try {
      await _dio.post<Object?>(
        '/auth/logout',
        options: Options(extra: {kSkipUnauthorizedExtra: true}),
      );
    } on DioException {
      // Offline or already expired: the local token is wiped anyway.
    }
  }
}
