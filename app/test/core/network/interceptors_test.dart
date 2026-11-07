import 'package:app/core/config/app_config.dart';
import 'package:app/core/error/api_exception.dart';
import 'package:app/core/network/api_client.dart';
import 'package:app/core/network/api_context.dart';
import 'package:app/core/network/interceptors.dart';
import 'package:app/core/storage/key_value_store.dart';
import 'package:app/core/storage/session_storage.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'fake_adapter.dart';

Future<ApiException?> apiError(Future<Object?> Function() f) async {
  try {
    await f();
    return null;
  } catch (e) {
    return ApiException.from(e);
  }
}

void main() {
  const config = AppConfig(
    apiBase: 'https://example.test/',
    googleServerClientId: '',
    env: 'dev',
  );

  late ApiContext context;
  late SessionStorage storage;
  late int unauthorized;

  Dio build(FakeAdapter adapter) => buildDio(
    config: config,
    context: context,
    storage: storage,
    onUnauthorized: () => unauthorized++,
    adapter: adapter,
  );

  setUp(() {
    context = ApiContext(locale: 'ar');
    storage = SessionStorage(MemoryKeyValueStore());
    unauthorized = 0;
  });

  test('AppConfig builds a normalised /api/v1 base', () {
    expect(config.apiV1, 'https://example.test/api/v1');
  });

  group('headers', () {
    test('locale, accept, cart token and bearer are sent (and nothing '
        'store-specific)', () async {
      await storage.saveAuthToken('tok');
      await storage.saveCartToken('cart-1');
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({'success': true, 'data': {}}),
      );
      await build(adapter).get<dynamic>('/store');
      final h = adapter.requests.single.headers;
      expect(h['Accept-Language'], 'ar');
      expect(h['Accept'], 'application/json');
      expect(h['X-Cart-Token'], 'cart-1');
      expect(h['Authorization'], 'Bearer tok');
      expect(h.keys.where((k) => k.toLowerCase().startsWith('x-')).toList(), [
        'X-Cart-Token',
      ]);
    });

    test('no token stored -> no Authorization / X-Cart-Token', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({'success': true, 'data': {}}),
      );
      await build(adapter).get<dynamic>('/app-config');
      final h = adapter.requests.single.headers;
      expect(h.containsKey('Authorization'), isFalse);
      expect(h.containsKey('X-Cart-Token'), isFalse);
    });

    test('echoed X-Cart-Token is persisted', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json(
          {'success': true, 'data': {}},
          headers: {
            'x-cart-token': ['new-cart'],
          },
        ),
      );
      await build(adapter).get<dynamic>('/cart');
      expect(await storage.cartToken(), 'new-cart');
    });
  });

  group('envelope', () {
    test('unwraps data and exposes message + meta', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({
          'success': true,
          'message': 'ok',
          'data': [1, 2],
          'meta': {
            'pagination': {'current_page': 1, 'last_page': 3},
          },
        }),
      );
      final r = await build(adapter).get<dynamic>('/catalog');
      expect(r.data, [1, 2]);
      expect(r.envelopeMessage, 'ok');
      expect((r.envelopeMeta['pagination'] as Map)['last_page'], 3);
    });

    test('non-enveloped bodies pass through', () async {
      final adapter = FakeAdapter((_) => FakeAdapter.json({'status': 'ok'}));
      final r = await build(adapter).get<dynamic>('/health');
      expect(r.data, {'status': 'ok'});
    });

    test('422 maps to a validation ApiException with field errors', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({
          'success': false,
          'message': 'The email field is required.',
          'errors': {
            'email': ['The email field is required.'],
          },
        }, status: 422),
      );
      try {
        await build(adapter).post<dynamic>('/auth/login', data: {});
        fail('should throw');
      } on DioException catch (e) {
        final api = ApiException.from(e);
        expect(api.kind, ApiErrorKind.validation);
        expect(api.statusCode, 422);
        expect(api.firstErrorFor('email'), 'The email field is required.');
        expect(api.message, 'The email field is required.');
      }
    });

    test(
      '429 carries Retry-After; feature_disabled is distinguished',
      () async {
        var n = 0;
        final adapter = FakeAdapter((_) {
          n++;
          return n == 1
              ? FakeAdapter.json(
                  {'success': false, 'message': 'slow down'},
                  status: 429,
                  headers: {
                    'retry-after': ['7'],
                  },
                )
              : FakeAdapter.json({
                  'success': false,
                  'error': 'feature_disabled',
                  'message': 'off',
                }, status: 403);
        });
        final dio = build(adapter);
        final a = await apiError(() => dio.post<dynamic>('/x'));
        expect(a!.kind, ApiErrorKind.rateLimited);
        expect(a.retryAfter, const Duration(seconds: 7));
        final b = await apiError(() => dio.post<dynamic>('/y'));
        expect(b!.kind, ApiErrorKind.featureDisabled);
      },
    );
  });

  group('401 handling', () {
    test(
      'clears the token and notifies when a bearer request is rejected',
      () async {
        await storage.saveAuthToken('tok');
        final adapter = FakeAdapter(
          (_) => FakeAdapter.json({
            'success': false,
            'message': 'Unauthenticated.',
          }, status: 401),
        );
        final err = await apiError(
          () => build(adapter).get<dynamic>('/auth/me'),
        );
        expect(err!.kind, ApiErrorKind.unauthorized);
        expect(await storage.authToken(), isNull);
        expect(unauthorized, 1);
      },
    );

    test('guest 401 (no bearer) does not trigger logout', () async {
      final adapter = FakeAdapter(
        (_) =>
            FakeAdapter.json({'success': false, 'message': 'x'}, status: 401),
      );
      await build(adapter)
          .get<dynamic>('/auth/me')
          .then((_) => null)
          .catchError((Object _) => null);
      expect(unauthorized, 0);
    });

    test('skip flag keeps the token (e.g. wrong current password)', () async {
      await storage.saveAuthToken('tok');
      final adapter = FakeAdapter(
        (_) =>
            FakeAdapter.json({'success': false, 'message': 'x'}, status: 401),
      );
      await build(adapter)
          .get<dynamic>(
            '/auth/me',
            options: Options(extra: {kSkipUnauthorizedExtra: true}),
          )
          .then((_) => null)
          .catchError((Object _) => null);
      expect(await storage.authToken(), 'tok');
      expect(unauthorized, 0);
    });
  });

  group('retry', () {
    Dio retrying(FakeAdapter a) {
      final dio = buildDio(
        config: config,
        context: context,
        storage: storage,
        adapter: a,
      );
      dio.interceptors
        ..removeWhere((i) => i is RetryInterceptor)
        ..insert(4, RetryInterceptor(dio, delay: (_) async {}));
      return dio;
    }

    test('GET is retried on 503 then succeeds', () async {
      var n = 0;
      final adapter = FakeAdapter((_) {
        n++;
        return n < 3
            ? FakeAdapter.json({
                'success': false,
                'message': 'down',
              }, status: 503)
            : FakeAdapter.json({'success': true, 'data': 'ok'});
      });
      final r = await retrying(adapter).get<dynamic>('/home');
      expect(r.data, 'ok');
      expect(adapter.requests, hasLength(3));
    });

    test('gives up after max retries with a server ApiException', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({
          'success': false,
          'message': 'down',
        }, status: 503),
      );
      final err = await apiError(() => retrying(adapter).get<dynamic>('/home'));
      expect(err!.kind, ApiErrorKind.server);
      expect(adapter.requests, hasLength(3));
    });

    test('POST is never retried', () async {
      final adapter = FakeAdapter(
        (_) => FakeAdapter.json({
          'success': false,
          'message': 'down',
        }, status: 503),
      );
      await retrying(adapter)
          .post<dynamic>('/checkout')
          .then((_) => null)
          .catchError((Object _) => null);
      expect(adapter.requests, hasLength(1));
    });

    test('4xx is not retried', () async {
      final adapter = FakeAdapter(
        (_) =>
            FakeAdapter.json({'success': false, 'message': 'nf'}, status: 404),
      );
      await retrying(adapter)
          .get<dynamic>('/products/x')
          .then((_) => null)
          .catchError((Object _) => null);
      expect(adapter.requests, hasLength(1));
    });
  });
}
