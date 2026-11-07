import 'package:app/core/l10n/locale_provider.dart';
import 'package:app/core/routing/app_routes.dart';
import 'package:app/core/storage/legacy_storage_migration.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/startup/application/startup_controller.dart';
import 'package:app/features/store/application/store_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

const _offline = 'أنت غير متصل. نعرض بيانات محفوظة.';

void main() {
  group('StartupController', () {
    test('loads app-config, caches the store block, applies the default '
        'locale; only app-config + store are called', () async {
      final env = TestEnv(
        languageChosen: false,
        store: storeInfoJson(locale: 'en'),
      );
      final c = await env.container();
      final r = await c.read(startupControllerProvider.notifier).run();
      expect(r.storeLoaded, isTrue);
      expect(r.offline, isFalse);
      expect(r.needsLanguage, isTrue);
      expect(r.route(onboardingDone: true), AppRoutes.chooseLanguage);
      expect(c.read(localeProvider).languageCode, 'en');
      expect(c.read(storeInfoProvider)?.currencySymbol, 'EGP');
      expect(env.prefs.getString('store_info'), contains('Store egy'));
      await Future<void>.delayed(const Duration(milliseconds: 20));
      expect(c.read(storeProfileProvider)?.whatsapp, '201001234567');
      expect(env.adapter.requests.map((o) => o.path).toSet(), {
        '/app-config',
        '/store',
      });
    });

    test('saved language is kept and no picker is needed', () async {
      final env = TestEnv(prefs: {'locale': 'en'});
      final c = await env.container();
      final r = await c.read(startupControllerProvider.notifier).run();
      expect(r.needsLanguage, isFalse);
      expect(r.route(onboardingDone: true), AppRoutes.home);
      expect(r.route(onboardingDone: false), AppRoutes.onboarding);
      expect(c.read(localeProvider).languageCode, 'en');
    });

    test('a single store locale needs no picker', () async {
      final env = TestEnv(
        languageChosen: false,
        store: storeInfoJson(locales: ['ar']),
      );
      final c = await env.container();
      final r = await c.read(startupControllerProvider.notifier).run();
      expect(r.needsLanguage, isFalse);
    });

    test('offline with a cached store -> offline result, cached data used; '
        'recheck clears it', () async {
      final env = TestEnv(appConfigDown: true);
      await env.init();
      await env.prefs.setString(
        'store_info',
        '{"name":"Cached","currency":{"code":"EGP","symbol":"ج.م"},'
            '"default_locale":"ar","locales":[{"code":"ar","rtl":true}]}',
      );
      final c = createContainer(env);
      final r = await c.read(startupControllerProvider.notifier).run();
      expect(r.storeLoaded, isTrue);
      expect(r.offline, isTrue);
      expect(c.read(storeControllerProvider).name, 'Cached');
      expect(c.read(storeControllerProvider).currencyLabel, 'ج.م');

      env.appConfigDown = false;
      expect(
        await c.read(startupControllerProvider.notifier).recheck(),
        isTrue,
      );
      expect(c.read(startupControllerProvider).result?.offline, isFalse);
      expect(c.read(storeControllerProvider).name, 'Store egy');
    });

    test('offline and nothing cached -> failed (retry screen)', () async {
      final env = TestEnv(appConfigDown: true);
      final c = await env.container();
      await c.read(startupControllerProvider.notifier).run();
      expect(c.read(startupControllerProvider).failed, isTrue);
    });

    test(
      'older install: session survives the update (migration on start)',
      () async {
        final env = TestEnv(
          languageChosen: false,
          prefs: {legacySlugKey: 'egy', 'locale:egy': 'en'},
          extra: (o) => o.path == '/auth/me'
              ? envelope({'id': 1, 'name': 'Sara', 'email': 's@x.co'})
              : null,
        );
        await env.init();
        await env.secrets.write('auth_token:egy', 'tok');
        await env.secrets.write('cart_token:egy', 'cart');
        final c = createContainer(env);
        final r = await c.read(startupControllerProvider.notifier).run();
        expect(c.read(authControllerProvider).signedIn, isTrue);
        expect(env.secrets.data['auth_token'], 'tok');
        expect(env.secrets.data['cart_token'], 'cart');
        expect(r.needsLanguage, isFalse);
        expect(c.read(localeProvider).languageCode, 'en');
        expect(
          env.requestsTo('/auth/me').single.headers['Authorization'],
          'Bearer tok',
        );
      },
    );

    test('chooseLocale persists and ignores locales the store lacks', () async {
      final env = TestEnv(languageChosen: false);
      final c = await env.container();
      await bootStore(c, info: storeInfoJson(locales: ['ar']));
      await c.read(storeControllerProvider.notifier).chooseLocale('en');
      expect(c.read(localeProvider).languageCode, 'ar');
      expect(env.prefs.getString('locale'), isNull);
      await bootStore(c);
      await c.read(storeControllerProvider.notifier).chooseLocale('en');
      expect(c.read(localeProvider).languageCode, 'en');
      expect(env.prefs.getString('locale'), 'en');
    });
  });

  group('startup flow', () {
    testWidgets('first launch: language page -> onboarding -> Home', (
      tester,
    ) async {
      bigScreen(tester);
      final env = TestEnv(languageChosen: false);
      await env.pumpApp(tester);
      expect(find.text('اختر اللغة'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('lang-en')));
      await tester.pumpAndSettle();
      expect(env.prefs.getString('locale'), 'en');
      expect(find.text('Skip'), findsOneWidget);
      await tester.tap(find.text('Skip'));
      await tester.pumpAndSettle();
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(env.prefs.getBool('onboarding_done'), isTrue);
    });

    testWidgets('language already chosen: straight to Home, LTR for en', (
      tester,
    ) async {
      bigScreen(tester);
      final env = TestEnv(prefs: {'onboarding_done': true, 'locale': 'en'});
      await env.pumpApp(tester);
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(find.text('Choose your language'), findsNothing);
      expect(
        Directionality.of(tester.element(find.byType(NavigationBar))),
        TextDirection.ltr,
      );
    });

    testWidgets('unreachable and nothing cached: full-screen retry', (
      tester,
    ) async {
      bigScreen(tester);
      final env = TestEnv(
        appConfigDown: true,
        prefs: {'onboarding_done': true},
      );
      await env.pumpApp(tester);
      expect(find.text('إعادة المحاولة'), findsOneWidget);
      env.appConfigDown = false;
      await tester.tap(find.text('إعادة المحاولة'));
      await settle(tester);
      expect(find.byType(NavigationBar), findsOneWidget);
    });

    testWidgets('offline with cached store: Home with retry banner', (
      tester,
    ) async {
      bigScreen(tester);
      final env = TestEnv(
        appConfigDown: true,
        prefs: {
          'onboarding_done': true,
          'store_info':
              '{"name":"Cached","currency":{"code":"EGP","symbol":"EGP"},'
              '"default_locale":"ar","locales":[{"code":"ar","rtl":true}]}',
        },
      );
      await env.pumpApp(tester);
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(find.text(_offline), findsOneWidget);
      env.appConfigDown = false;
      await tester.tap(find.widgetWithText(TextButton, 'إعادة المحاولة'));
      await settle(tester);
      expect(find.text(_offline), findsNothing);
    });

    testWidgets('maintenance gate', (tester) async {
      bigScreen(tester);
      final env = TestEnv(
        prefs: {'onboarding_done': true},
        appConfig: {'maintenance': true, 'maintenance_message': 'Back soon'},
      );
      await env.pumpApp(tester);
      expect(find.text('Back soon'), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
    });
  });
}
