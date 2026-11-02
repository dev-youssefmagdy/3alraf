import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../../core/error/api_exception.dart';

/// Source of the device's push token (FCM registration token).
///
/// The app ships with [NoopPushTokenProvider] because no Firebase config
/// files (`android/app/google-services.json`, `ios/Runner/GoogleService-
/// Info.plist`) exist in the repository. To enable push:
///  1. add the two Firebase files and the `com.google.gms.google-services`
///     Gradle plugin,
///  2. `flutter pub add firebase_core firebase_messaging`,
///  3. implement this interface with `FirebaseMessaging.instance.getToken()`
///     (and `onTokenRefresh` -> `PushRegistrar.register`),
///  4. override `pushTokenProviderProvider` in `main.dart`.
abstract class PushTokenProvider {
  /// The current token, or `null` when push is unavailable / not permitted.
  Future<String?> token();
}

/// Default: push is not configured, nothing is registered.
class NoopPushTokenProvider implements PushTokenProvider {
  const NoopPushTokenProvider();

  @override
  Future<String?> token() async => null;
}

/// `POST /devices`, `DELETE /devices`.
class DeviceRepository {
  const DeviceRepository(this._dio);

  final Dio _dio;

  static String get platform => switch (defaultTargetPlatform) {
    TargetPlatform.iOS => 'ios',
    TargetPlatform.android => 'android',
    _ => 'web',
  };

  Future<void> register({
    required String token,
    required String locale,
    String? appVersion,
  }) async {
    try {
      await _dio.post<Object?>(
        '/devices',
        data: {
          'token': token,
          'platform': platform,
          'locale': locale,
          'app_version': ?appVersion,
        },
      );
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<void> unregister(String token) async {
    try {
      await _dio.delete<Object?>('/devices', data: {'token': token});
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }
}
