import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../startup/application/startup_controller.dart';
import '../data/push_service.dart';

final pushTokenProviderProvider = Provider<PushTokenProvider>(
  (ref) => const NoopPushTokenProvider(),
);

final deviceRepositoryProvider = Provider<DeviceRepository>(
  (ref) => DeviceRepository(ref.watch(dioProvider)),
);

final pushRegistrarProvider = Provider<PushRegistrar>(PushRegistrar.new);

/// Registers the push token with the backend after sign-in and removes it
/// on sign-out. Every failure is swallowed: push must never break auth.
class PushRegistrar {
  PushRegistrar(this._ref);

  final Ref _ref;
  String? _registered;
  String? _registeredLocale;

  Future<void> register() async {
    try {
      final token = await _ref.read(pushTokenProviderProvider).token();
      if (token == null || token.isEmpty) return;
      final version = await _ref
          .read(appVersionProvider.future)
          .then<String?>((v) => v)
          .catchError((Object _) => null);
      final locale = _ref.read(localeProvider).languageCode;
      await _ref
          .read(deviceRepositoryProvider)
          .register(token: token, locale: locale, appVersion: version);
      _registered = token;
      _registeredLocale = locale;
    } on ApiException {
      // Best effort.
    } on Object {
      // Token provider failures are ignored too.
    }
  }

  /// Re-sends the token when the UI language changes after it was registered
  /// (the server uses the device locale to localise notifications). A no-op
  /// when nothing is registered, so signed-out states do not create
  /// anonymous device rows.
  Future<void> onLocaleChanged(String code) async {
    if (_registered == null || _registeredLocale == code) return;
    await register();
  }

  /// Call BEFORE the auth token is dropped so the server can unbind it.
  Future<void> unregister() async {
    try {
      final token =
          _registered ?? await _ref.read(pushTokenProviderProvider).token();
      _registered = null;
      _registeredLocale = null;
      if (token == null || token.isEmpty) return;
      await _ref.read(deviceRepositoryProvider).unregister(token);
    } on Object {
      // Best effort.
    }
  }
}
