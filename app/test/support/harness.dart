import 'dart:convert';

import 'package:app/app.dart';
import 'package:app/core/config/app_config.dart';
import 'package:app/core/network/api_client.dart';
import 'package:app/core/network/network_providers.dart';
import 'package:app/core/storage/key_value_store.dart';
import 'package:app/core/storage/preferences.dart';
import 'package:app/core/storage/storage_providers.dart';
import 'package:app/features/splash/presentation/splash_page.dart';
import 'package:app/features/tenant/application/startup_controller.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/network/fake_adapter.dart';

Map<String, Object?> tenantJson(
  String slug, {
  String name = '',
  String locale = 'ar',
  List<String> locales = const ['ar', 'en'],
}) => {
  'slug': slug,
  'name': name.isEmpty ? 'Store $slug' : name,
  'country': slug.toUpperCase(),
  'flag': '🏳',
  'currency': 'EGP',
  'default_locale': locale,
  'locales': locales,
  'languages': ['العربية'],
  'logo_url': null,
  'base_url': 'http://$slug.test/',
};

Map<String, Object?> storeJson(String slug, {String locale = 'ar'}) => {
  'slug': slug,
  'name': 'Store $slug',
  'country': slug.toUpperCase(),
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
  TestEnv({
    List<Map<String, Object?>>? tenants,
    this.tenantsDown = false,
    this.appConfig = const {},
    Map<String, Object?> prefs = const {},
    ResponseBody? Function(RequestOptions o)? extra,
  }) : tenants = tenants ?? [tenantJson('egy')],
       _extra = extra,
       initialPrefs = prefs;

  List<Map<String, Object?>> tenants;
  bool tenantsDown;
  Map<String, Object?> appConfig;
  final Map<String, Object?> initialPrefs;
  final ResponseBody? Function(RequestOptions o)? _extra;
  final MemoryKeyValueStore secrets = MemoryKeyValueStore();
  late final FakeAdapter adapter = FakeAdapter(_respond);
  late SharedPreferences prefs;

  ResponseBody _respond(RequestOptions o) {
    final path = o.path;
    if (path == '/tenants') {
      if (tenantsDown) {
        throw DioException.connectionError(
          requestOptions: o,
          reason: 'offline',
        );
      }
      return envelope(tenants);
    }
    if (path == '/app-config') {
      return envelope({
        'min_version': '1.0.0',
        'latest_version': '1.0.0',
        'store_urls': {'ios': null, 'android': null},
        'maintenance': false,
        'maintenance_message': null,
        'update_required': false,
        'update_available': false,
        ...appConfig,
      });
    }
    if (path == '/store') {
      final slug = o.headers['X-Tenant'] as String? ?? 'egy';
      return envelope(storeJson(slug));
    }
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
          apiBase: 'http://central.test',
          googleServerClientId: '',
          env: 'test',
        ),
        context: ref.watch(apiContextProvider),
        storage: ref.watch(sessionStorageProvider),
        onUnauthorized: (_) =>
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

/// Settles frames and lets the retry-interceptor back-off timers fire.
Future<void> settle(WidgetTester tester) async {
  await tester.pumpAndSettle();
  await tester.pump(const Duration(seconds: 3));
  await tester.pumpAndSettle();
}

String jsonOf(Object o) => jsonEncode(o);

Widget envApp(TestEnv env) =>
    ProviderScope(overrides: env.overrides, child: const AlrafApp());
