import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/api_context.dart';
import 'auth_models.dart';

/// Customer auth endpoints (`/auth/*`, `/cart/merge`).
class AuthRepository {
  const AuthRepository(this._dio);

  final Dio _dio;

  static const _deviceName = 'mobile-app';

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Map<String, Object?> _obj(Object? d) =>
      d is Map ? d.map((k, v) => MapEntry('$k', v)) : const {};

  Future<AuthResult> login(String email, String password) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/auth/login',
      data: {'email': email, 'password': password, 'device_name': _deviceName},
    );
    return AuthResult.fromJson(_obj(r.data));
  });

  Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/auth/register',
      data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        if (phone != null && phone.isNotEmpty) 'phone': phone,
        'device_name': _deviceName,
      },
    );
    return AuthResult.fromJson(_obj(r.data));
  });

  Future<AuthResult> google(String idToken) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/auth/google',
      data: {'id_token': idToken, 'device_name': _deviceName},
    );
    return AuthResult.fromJson(_obj(r.data));
  });

  Future<Customer> me() => _guard(() async {
    final r = await _dio.get<Object?>('/auth/me');
    return Customer.fromJson(_obj(r.data));
  });

  /// `PUT /auth/me`. `name` and `email` are always sent; the password trio
  /// only when changing the password (`current_password` is also needed when
  /// the e-mail changes).
  Future<Customer> updateProfile({
    required String name,
    required String email,
    String? phone,
    String? phoneCountryCode,
    String? currentPassword,
    String? password,
    String? passwordConfirmation,
  }) => _guard(() async {
    final r = await _dio.put<Object?>(
      '/auth/me',
      data: {
        'name': name,
        'email': email,
        'phone': ?phone,
        'phone_country_code': ?phoneCountryCode,
        'current_password': ?currentPassword,
        'password': ?password,
        'password_confirmation': ?passwordConfirmation,
      },
    );
    return Customer.fromJson(_obj(r.data));
  });

  /// `DELETE /auth/me`: password, or `confirm: true` for Google-only accounts.
  Future<void> deleteAccount({String? password, bool confirm = false}) =>
      _guard(
        () => _dio.delete<Object?>(
          '/auth/me',
          data: {
            if (password != null && password.isNotEmpty) 'password': password,
            if (confirm) 'confirm': true,
          },
        ),
      );

  /// Revokes the current token; never throws.
  Future<void> logout() async {
    try {
      await _dio.post<Object?>(
        '/auth/logout',
        options: Options(extra: {kSkipUnauthorizedExtra: true}),
      );
    } on DioException {
      // Token is wiped locally regardless.
    }
  }

  Future<void> forgotPassword(String email) => _guard(
    () => _dio.post<Object?>('/auth/forgot-password', data: {'email': email}),
  );

  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) => _guard(
    () => _dio.post<Object?>(
      '/auth/reset-password',
      data: {
        'email': email,
        'code': code,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    ),
  );

  Future<void> sendVerification() =>
      _guard(() => _dio.post<Object?>('/auth/email/verification-notification'));

  Future<Customer> verifyEmail(String code) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/auth/email/verify',
      data: {'code': code},
    );
    return Customer.fromJson(_obj(r.data));
  });

  /// `POST /cart/merge`: guest cart (X-Cart-Token) into the customer cart.
  Future<void> mergeCart() => _guard(() => _dio.post<Object?>('/cart/merge'));
}
