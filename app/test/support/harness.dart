import 'dart:convert';

import 'package:app/app.dart';
import 'package:app/core/config/app_config.dart';
import 'package:app/core/network/api_client.dart';
import 'package:app/core/network/network_providers.dart';
import 'package:app/core/storage/key_value_store.dart';
import 'package:app/core/storage/preferences.dart';
import 'package:app/core/storage/storage_providers.dart';
import 'package:app/features/splash/presentation/splash_page.dart';
import 'package:app/features/startup/application/startup_controller.dart';
import 'package:app/features/store/application/store_controller.dart';
import 'package:app/features/store/data/store_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/network/fake_adapter.dart';

/// `GET /app-config` `store` block.
Map<String, Object?> storeInfoJson({
  String name = 'Store egy',
  String locale = 'ar',
  List<String> locales = const ['ar', 'en'],
}) => {
  'name': name,
  'country': 'EG',
  'currency': {'code': 'EGP', 'symbol': 'EGP'},
  'default_locale': locale,
  'locales': [
    for (final l in locales) {'code': l, 'rtl': l == 'ar'},
  ],
  'timezone': 'Africa/Cairo',
  'base_url': 'http://shop.test',
};

/// Binds a test container to the store (what the splash does on start).
Future<void> bootStore(ProviderContainer c, {Map<String, Object?>? info}) => c
    .read(storeControllerProvider.notifier)
    .apply(StoreInfo.fromJson(info ?? storeInfoJson()));

/// `GET /store` profile.
Map<String, Object?> storeJson({String locale = 'ar'}) => {
  'slug': 'egy',
  'name': 'Store egy',
  'country': 'EG',
  'currency': {'code': 'EGP', 'symbol': 'EGP'},
  'default_locale': locale,
  'locales': [
    {'code': 'ar', 'rtl': true},
    {'code': 'en', 'rtl': false},
  ],
  'logo_url': null,
  'colors': {'primary': '#A8456C'},
  'contact': {'whatsapp': '201001234567'},
  'features': {'gift_finder': true},
};

ResponseBody envelope(
  Object? data, {
  int status = 200,
  Map<String, List<String>> headers = const {},
}) => FakeAdapter.json(
  {
    'success': status < 400,
    'message': null,
    'data': data,
    'meta': <String, Object?>{},
  },
  status: status,
  headers: headers,
);

ResponseBody envelopeWithMeta(Object? data, Map<String, Object?> meta) =>
    FakeAdapter.json({
      'success': true,
      'message': null,
      'data': data,
      'meta': meta,
    });

ResponseBody errorEnvelope(int status, String message, [Object? errors]) =>
    FakeAdapter.json({
      'success': false,
      'message': message,
      'errors': ?errors,
      'data': null,
      'meta': <String, Object?>{},
    }, status: status);

/// Everything a test needs: fake backend + overrides.
class TestEnv {
  /// [languageChosen] seeds a saved locale so app tests skip the first-run
  /// language page (pass `false` to test it).
  TestEnv({
    Map<String, Object?>? store,
    this.appConfigDown = false,
    this.appConfig = const {},
    Map<String, Object?> prefs = const {},
    bool languageChosen = true,
    ResponseBody? Function(RequestOptions o)? extra,
  }) : store = store ?? storeInfoJson(),
       _extra = extra,
       initialPrefs = {if (languageChosen) 'locale': 'ar', ...prefs};

  /// `app-config.store`; `null` simulates a server without the block.
  Map<String, Object?>? store;
  bool appConfigDown;
  Map<String, Object?> appConfig;
  final Map<String, Object?> initialPrefs;
  final ResponseBody? Function(RequestOptions o)? _extra;
  final MemoryKeyValueStore secrets = MemoryKeyValueStore();
  late final FakeAdapter adapter = FakeAdapter(_respond);
  late SharedPreferences prefs;

  ResponseBody _respond(RequestOptions o) {
    final path = o.path;
    if (path == '/app-config') {
      if (appConfigDown) {
        throw DioException.connectionError(
          requestOptions: o,
          reason: 'offline',
        );
      }
      return envelope({
        'min_version': '1.0.0',
        'latest_version': '1.0.0',
        'store_urls': {'ios': null, 'android': null},
        'maintenance': false,
        'maintenance_message': null,
        'update_required': false,
        'update_available': false,
        'store': ?store,
        ...appConfig,
      });
    }
    if (path == '/store') return envelope(storeJson());
    if (path == '/auth/logout') return envelope(null);
    final r = _extra?.call(o);
    if (r != null) return r;
    return errorEnvelope(404, 'Not found.');
  }

  Future<void> init() async {
    SharedPreferences.setMockInitialValues(
      Map<String, Object>.from(initialPrefs),
    );
    prefs = await SharedPreferences.getInstance();
  }

  List<RequestOptions> requestsTo(String path) =>
      adapter.requests.where((r) => r.path == path).toList();

  List<Override> get overrides => [
    preferencesProvider.overrideWithValue(AppPreferences(prefs)),
    keyValueStoreProvider.overrideWithValue(secrets),
    splashMinDurationProvider.overrideWithValue(Duration.zero),
    appVersionProvider.overrideWith((ref) => '1.0.0'),
    dioProvider.overrideWith(
      (ref) => buildDio(
        config: const AppConfig(
          apiBase: 'http://shop.test',
          googleServerClientId: '',
          env: 'test',
        ),
        context: ref.watch(apiContextProvider),
        storage: ref.watch(sessionStorageProvider),
        onUnauthorized: () =>
            ref.read(unauthorizedEventsProvider.notifier).notify(),
        adapter: adapter,
      ),
    ),
  ];

  Future<ProviderContainer> container() async {
    await init();
    final c = ProviderContainer(overrides: overrides);
    addTearDown(c.dispose);
    return c;
  }

  Future<void> pumpApp(WidgetTester tester) async {
    await init();
    await tester.pumpWidget(
      ProviderScope(overrides: overrides, child: const AlrafApp()),
    );
    await settle(tester);
  }
}

/// Container for an env whose prefs/secrets were already seeded (`init()`).
ProviderContainer createContainer(TestEnv env) {
  final c = ProviderContainer(overrides: env.overrides);
  addTearDown(c.dispose);
  return c;
}

/// Settles frames and lets the retry-interceptor back-off timers fire.
Future<void> settle(WidgetTester tester) async {
  await tester.pumpAndSettle();
  await tester.pump(const Duration(seconds: 3));
  await tester.pumpAndSettle();
}

String jsonOf(Object o) => jsonEncode(o);

Widget envApp(TestEnv env) =>
    ProviderScope(overrides: env.overrides, child: const AlrafApp());
