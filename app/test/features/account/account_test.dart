import 'package:app/features/store/application/store_controller.dart';
import 'package:app/core/network/network_providers.dart';
import 'package:app/core/routing/app_router.dart';
import 'package:app/core/theme/theme_mode_provider.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/push/application/push_registrar.dart';
import 'package:app/features/push/data/push_service.dart';
import 'package:app/features/shell/presentation/app_drawer.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/commerce_fixtures.dart';
import '../../support/harness.dart';
import '../auth/auth_test.dart' show authApi, authData, customer;

class FakePush implements PushTokenProvider {
  FakePush(this.value);
  final String? value;
  @override
  Future<String?> token() async => value;
}

ResponseBody? Function(RequestOptions) api({
  Map<String, Object?>? onPut,
  void Function(RequestOptions)? spy,
}) {
  final base = authApi();
  return (o) {
    spy?.call(o);
    switch ((o.method, o.path)) {
      case ('PUT', '/auth/me'):
        return envelope({...customer(), ...?onPut});
      case ('DELETE', '/auth/me'):
        final data = o.data;
        if (data is Map && data['password'] == 'wrong') {
          return errorEnvelope(422, 'Wrong password.', {
            'password': ['Wrong password.'],
          });
        }
        return envelope(null);
      case ('POST', '/devices'):
      case ('DELETE', '/devices'):
        return envelope(null);
      case ('GET', '/orders/tok-1'):
        return envelope(orderJson());
      case ('GET', '/pages'):
        return envelope([
          {'slug': 'terms', 'title': 'Terms'},
        ]);
      case ('GET', '/custom-pages'):
        return envelope([
          {'slug': 'offers', 'title': 'Offers'},
        ]);
    }
    return base(o);
  };
}

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

Future<void> go(WidgetTester tester, String location) async {
  final c = ProviderScope.containerOf(
    tester.element(find.byType(Scaffold).first),
  );
  c.read(routerProvider).go(location);
  await settle(tester);
}

void main() {
  group('profile / password / delete (controller)', () {
    test('updateProfile sends the password trio only when asked', () async {
      final calls = <RequestOptions>[];
      final env = TestEnv(extra: api(spy: calls.add));
      final c = await env.container();
      await env.secrets.write('auth_token', 'saved');
      await bootStore(c);
      final auth = c.read(authControllerProvider.notifier);

      await auth.updateProfile(
        name: 'Mona B',
        email: 'mona@x.test',
        phone: '010',
      );
      final profile = calls.firstWhere((o) => o.method == 'PUT').data as Map;
      expect(profile['name'], 'Mona B');
      expect(profile.containsKey('password'), isFalse);
      expect(profile.containsKey('current_password'), isFalse);

      calls.clear();
      await auth.updateProfile(
        name: 'Mona',
        email: 'mona@x.test',
        currentPassword: 'old',
        password: 'newpassword',
        passwordConfirmation: 'newpassword',
      );
      final pw = calls.firstWhere((o) => o.method == 'PUT').data as Map;
      expect(pw['current_password'], 'old');
      expect(pw['password'], 'newpassword');
      expect(pw['password_confirmation'], 'newpassword');
    });

    test(
      'deleteAccount sends password / confirm and wipes local state',
      () async {
        final calls = <RequestOptions>[];
        final env = TestEnv(extra: api(spy: calls.add));
        final c = await env.container();
        await env.secrets.write('auth_token', 'saved');
        await env.secrets.write('cart_token', 'guest');
        await bootStore(c);
        final auth = c.read(authControllerProvider.notifier);

        await expectLater(
          auth.deleteAccount(password: 'wrong'),
          throwsA(isA<Object>()),
        );
        expect(c.read(authControllerProvider).signedIn, isTrue);

        await auth.deleteAccount(password: 'secret');
        final del = calls.lastWhere(
          (o) => o.method == 'DELETE' && o.path == '/auth/me',
        );
        expect((del.data as Map)['password'], 'secret');
        expect(c.read(authControllerProvider).signedIn, isFalse);
        expect(env.secrets.data.containsKey('auth_token'), isFalse);
        expect(env.secrets.data.containsKey('cart_token'), isFalse);

        // Google-only account.
        await env.secrets.write('auth_token', 'again');
        await c.read(authControllerProvider.notifier).restore();
        await auth.deleteAccount(confirm: true);
        final del2 = calls.lastWhere(
          (o) => o.method == 'DELETE' && o.path == '/auth/me',
        );
        expect((del2.data as Map)['confirm'], isTrue);
      },
    );
  });

  group('push registration', () {
    test('no-op provider never calls /devices', () async {
      final env = TestEnv(extra: api());
      final c = await env.container();
      await bootStore(c);
      await c
          .read(authControllerProvider.notifier)
          .login('a@b.co', 'password1');
      await c.read(authControllerProvider.notifier).logout();
      expect(env.requestsTo('/devices'), isEmpty);
    });

    test('token is registered on login and removed on logout', () async {
      final env = TestEnv(extra: api());
      await env.init();
      final c = ProviderContainer(
        overrides: [
          ...env.overrides,
          pushTokenProviderProvider.overrideWithValue(FakePush('fcm-1')),
        ],
      );
      addTearDown(c.dispose);
      await bootStore(c);
      await c
          .read(authControllerProvider.notifier)
          .login('a@b.co', 'password1');
      await Future<void>.delayed(const Duration(milliseconds: 50));

      final reg = env.adapter.requests.firstWhere(
        (o) => o.method == 'POST' && o.path == '/devices',
      );
      final body = reg.data as Map;
      expect(body['token'], 'fcm-1');
      expect(body['platform'], isNotEmpty);
      expect(body['locale'], 'ar');
      expect(reg.headers['Authorization'], 'Bearer 12|secret');

      await c.read(authControllerProvider.notifier).logout();
      final unreg = env.adapter.requests.firstWhere(
        (o) => o.method == 'DELETE' && o.path == '/devices',
      );
      expect((unreg.data as Map)['token'], 'fcm-1');
      // unregister happens before the logout call revokes the token
      final order = env.adapter.requests
          .map((o) => '${o.method} ${o.path}')
          .toList();
      expect(
        order.indexOf('DELETE /devices'),
        lessThan(order.lastIndexOf('POST /auth/logout')),
      );
    });

    test(
      'changing the language re-registers the token with the new locale',
      () async {
        final env = TestEnv(extra: api());
        await env.init();
        final c = ProviderContainer(
          overrides: [
            ...env.overrides,
            pushTokenProviderProvider.overrideWithValue(FakePush('fcm-1')),
          ],
        );
        addTearDown(c.dispose);
        await bootStore(c);
        await c
            .read(authControllerProvider.notifier)
            .login('a@b.co', 'password1');
        await Future<void>.delayed(const Duration(milliseconds: 50));
        expect(env.requestsTo('/devices'), hasLength(1));

        await c.read(storeControllerProvider.notifier).chooseLocale('en');
        await Future<void>.delayed(const Duration(milliseconds: 50));

        final regs = env.requestsTo('/devices');
        expect(regs, hasLength(2));
        expect((regs.last.data as Map)['locale'], 'en');
        expect((regs.last.data as Map)['token'], 'fcm-1');
      },
    );
  });

  test('parseOrderToken accepts a bare token or a tracking link', () {
    const t = '2e81f703-717c-4d21-9762-f47f823bff9f';
    expect(parseOrderToken(t), t);
    expect(parseOrderToken('https://egy.3alraf.com/ar/order/$t?x=1'), t);
    expect(parseOrderToken('nope'), isNull);
  });

  testWidgets('guest can open /orders/{token} without being sent to login, '
      'cancel/return stay hidden', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    await go(tester, '/orders/tok-1');
    expect(find.text('ORD-000001'), findsWidgets);
    expect(find.text('تسجيل الدخول'), findsNothing);
    expect(find.text('إلغاء الطلب'), findsNothing);
    // the list itself is still protected
    await go(tester, '/orders');
    expect(find.text('تسجيل الدخول'), findsWidgets);
  });

  testWidgets('deep link with locale prefix opens the order page', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    await go(tester, '/en/order/tok-1');
    expect(find.text('ORD-000001'), findsWidgets);
  });

  testWidgets('profile edit: email change asks for the password and saves', (
    tester,
  ) async {
    bigScreen(tester);
    final calls = <RequestOptions>[];
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: api(spy: calls.add),
    );
    await env.init();
    await env.secrets.write('auth_token', 'saved');
    await tester.pumpWidget(envApp(env));
    await settle(tester);
    await go(tester, '/account/profile');

    expect(find.byType(TextFormField), findsNWidgets(3));
    await tester.enterText(find.byType(TextFormField).at(1), 'new@x.test');
    await tester.pump();
    expect(find.byType(TextFormField), findsNWidgets(4)); // current password
    await tester.enterText(find.byType(TextFormField).at(3), 'oldpass12');
    await tester.tap(find.widgetWithText(FilledButton, 'حفظ'));
    await settle(tester);

    final put = calls.firstWhere((o) => o.method == 'PUT');
    final body = put.data as Map;
    expect(body['email'], 'new@x.test');
    expect(body['current_password'], 'oldpass12');
  });

  testWidgets('delete account shows the server error inline for a wrong '
      'password, then deletes', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.init();
    await env.secrets.write('auth_token', 'saved');
    await tester.pumpWidget(envApp(env));
    await settle(tester);
    await go(tester, '/account/delete');

    Future<void> submit(String pw) async {
      await tester.enterText(find.byType(TextField).first, pw);
      await tester.tap(find.widgetWithText(FilledButton, 'احذف حسابي'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(TextButton, 'احذف حسابي'));
      await settle(tester);
    }

    await submit('wrong');
    expect(find.text('Wrong password.'), findsOneWidget);
    await submit('right');
    final c = ProviderScope.containerOf(
      tester.element(find.byType(Scaffold).first),
    );
    expect(c.read(authControllerProvider).signedIn, isFalse);
    expect(env.secrets.data.containsKey('auth_token'), isFalse);
  });

  testWidgets('drawer lists pages, collections, blog, affiliate, about, '
      'contact, rate app', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    final scaffold = tester.firstState<ScaffoldState>(find.byType(Scaffold));
    scaffold.openDrawer();
    await tester.pumpAndSettle();
    for (final t in [
      'المدونة',
      'برنامج التسويق بالعمولة',
      'تتبع طلب',
      'تواصل معنا',
      'حول التطبيق',
      'قيّم التطبيق',
      'المجموعات والعروض',
      'معلومات',
      'المظهر',
    ]) {
      await tester.scrollUntilVisible(
        find.text(t),
        100,
        scrollable: find.descendant(
          of: find.byType(Drawer),
          matching: find.byType(Scrollable),
        ),
      );
      expect(find.text(t), findsOneWidget, reason: t);
    }
  });

  testWidgets('theme setting switches to dark and is persisted', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    await go(tester, '/account');
    await tester.scrollUntilVisible(find.text('المظهر'), 100);
    await tester.tap(find.text('المظهر'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('داكن'));
    await tester.pumpAndSettle();
    final c = ProviderScope.containerOf(
      tester.element(find.byType(Scaffold).first),
    );
    expect(c.read(themeModeProvider), ThemeMode.dark);
    expect(env.prefs.getString('theme_mode'), 'dark');
    expect(
      Theme.of(tester.element(find.byType(Scaffold).first)).brightness,
      Brightness.dark,
    );
  });

  testWidgets('accessibility: account and about render at text scale 1.3', (
    tester,
  ) async {
    bigScreen(tester);
    tester.platformDispatcher.textScaleFactorTestValue = 1.3;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    for (final p in ['/account', '/about', '/contact', '/affiliate']) {
      await go(tester, p);
      expect(tester.takeException(), isNull, reason: p);
    }
    // icon-only controls carry tooltips for screen readers
    await go(tester, '/account');
    expect(find.byTooltip('القائمة'), findsWidgets);
  });

  test('unused import guard', () {
    expect(authData()['token'], isNotNull);
    expect(dioProvider, isNotNull);
  });
}
