import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/api_context.dart';
import '../../../core/network/json.dart';
import 'affiliate_models.dart';

/// Affiliate endpoints. They authenticate with the *affiliate* token, sent
/// as an explicit `Authorization` header (which wins over the customer
/// token), and never trigger the customer 401 logout.
class AffiliateRepository {
  const AffiliateRepository(this._dio, this._token);

  final Dio _dio;
  final Future<String?> Function() _token;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<Options> _auth() async {
    final t = await _token();
    return Options(
      headers: {if (t != null && t.isNotEmpty) 'Authorization': 'Bearer $t'},
      extra: {kSkipUnauthorizedExtra: true},
    );
  }

  /// Login must not carry the customer token.
  Options get _anon => Options(
    headers: {'Authorization': null},
    extra: {kSkipUnauthorizedExtra: true},
  );

  Future<({String token, AffiliateProfile profile})> login(
    String email,
    String password,
  ) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/affiliate/login',
      data: {'email': email, 'password': password},
      options: _anon,
    );
    final d = asMap(r.data);
    return (
      token: str(d['token']),
      profile: AffiliateProfile.fromJson(asMap(d['affiliate'])),
    );
  });

  /// Applies; the account waits for admin approval (no token).
  Future<AffiliateProfile> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/affiliate/register',
      data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        if (phone != null && phone.isNotEmpty) 'phone': phone,
      },
      options: _anon,
    );
    return AffiliateProfile.fromJson(asMap(r.data));
  });

  Future<void> logout() async {
    try {
      await _dio.post<Object?>('/affiliate/logout', options: await _auth());
    } on DioException {
      // Local token is dropped regardless.
    }
  }

  Future<AffiliateProfile> me() => _guard(() async {
    final r = await _dio.get<Object?>('/affiliate/me', options: await _auth());
    return AffiliateProfile.fromJson(asMap(r.data));
  });

  Future<AffiliateDashboard> dashboard() => _guard(() async {
    final r = await _dio.get<Object?>(
      '/affiliate/dashboard',
      options: await _auth(),
    );
    return AffiliateDashboard.fromJson(asMap(r.data));
  });

  Future<List<AffiliateProduct>> products() => _guard(() async {
    final r = await _dio.get<Object?>(
      '/affiliate/products',
      options: await _auth(),
    );
    return asMapList(r.data).map(AffiliateProduct.fromJson).toList();
  });

  Future<List<Payout>> payouts() => _guard(() async {
    final r = await _dio.get<Object?>(
      '/affiliate/payouts',
      options: await _auth(),
    );
    return asMapList(r.data).map(Payout.fromJson).toList();
  });

  Future<void> requestPayout() => _guard(
    () async =>
        _dio.post<Object?>('/affiliate/payouts', options: await _auth()),
  );

  Future<AmbassadorPortal> ambassador() => _guard(() async {
    final r = await _dio.get<Object?>(
      '/affiliate/ambassador',
      options: await _auth(),
    );
    return AmbassadorPortal.fromJson(asMap(r.data));
  });

  Future<void> joinAmbassador({required String disclosure}) => _guard(
    () async => _dio.post<Object?>(
      '/affiliate/ambassador/join',
      data: {'disclosure': disclosure, 'terms_accepted': true},
      options: await _auth(),
    ),
  );

  Future<AmbassadorLink> createLink({
    required int productId,
    required String channel,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/affiliate/ambassador/links',
      data: {'product_id': productId, 'channel': channel},
      options: await _auth(),
    );
    return AmbassadorLink.fromJson(asMap(r.data));
  });
}
