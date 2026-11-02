import 'package:app/core/network/network_providers.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:app/features/wishlist/presentation/wishlist_page.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

Map<String, Object?> customer({bool verified = false}) => {
  'id': 7,
  'name': 'Mona',
  'email': 'mona@x.test',
  'phone_full': null,
  'email_verified': verified,
};

Map<String, Object?> authData() => {
  'token': '12|secret',
  'token_type': 'Bearer',
  'abilities': ['customer'],
  'customer': customer(),
};

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

ResponseBody? Function(RequestOptions) authApi({bool loginFails = false}) =>
    (o) {
      switch (o.path) {
        case '/auth/login':
          return loginFails
              ? errorEnvelope(422, 'These credentials do not match.', {
                  'email': ['These credentials do not match.'],
                })
              : envelope(authData());
        case '/auth/register':
          return envelope(authData(), status: 201);
        case '/auth/me':
          return envelope(customer());
        case '/cart/merge':
          return envelope({'items': <Object>[]});
        case '/auth/forgot-password':
        case '/auth/reset-password':
        case '/auth/email/verification-notification':
          return envelope(null);
        case '/auth/email/verify':
          return envelope(customer(verified: true));
      }
      return null;
    };

Finder field(int i) => find.byType(TextFormField).at(i);

void main() {
  test(
    'login stores token, merges the guest cart then drops its token',
    () async {
      final env = TestEnv(extra: authApi());
      final c = await env.container();
      await c
          .read(tenantControllerProvider.notifier)
          .select(Tenant.fromJson(tenantJson('egy')));
      await env.secrets.write('cart_token:egy', 'guest-cart');

      await c
          .read(authControllerProvider.notifier)
          .login('a@b.co', 'password1');

      expect(env.secrets.data['auth_token:egy'], '12|secret');
      expect(c.read(authControllerProvider).signedIn, isTrue);
      expect(c.read(authControllerProvider).customer?.name, 'Mona');
      final merge = env.requestsTo('/cart/merge').single;
      expect(merge.headers['Authorization'], 'Bearer 12|secret');
      expect(merge.headers['X-Cart-Token'], 'guest-cart');
      expect(env.secrets.data.containsKey('cart_token:egy'), isFalse);
    },
  );

  test('session is restored from secure storage', () async {
    final env = TestEnv(extra: authApi());
    final c = await env.container();
    await env.secrets.write('auth_token:egy', 'saved');
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    expect(c.read(authControllerProvider).signedIn, isTrue);
    expect(c.read(authControllerProvider).customer?.email, 'mona@x.test');
  });

  test('logout revokes remotely and clears the token', () async {
    final env = TestEnv(extra: authApi());
    final c = await env.container();
    await env.secrets.write('auth_token:egy', 'saved');
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await c.read(authControllerProvider.notifier).logout();
    expect(env.requestsTo('/auth/logout'), hasLength(1));
    expect(env.secrets.data.containsKey('auth_token:egy'), isFalse);
    expect(c.read(authControllerProvider).signedIn, isFalse);
  });

  testWidgets('wrong credentials show the server error inline', (tester) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: authApi(loginFails: true),
    );
    await env.pumpApp(tester);
    await tester.tap(find.text('حسابي').last);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'تسجيل الدخول'));
    await tester.pumpAndSettle();
    await tester.enterText(field(0), 'mona@x.test');
    await tester.enterText(field(1), 'wrongpass');
    await tester.tap(find.widgetWithText(FilledButton, 'تسجيل الدخول'));
    await tester.pumpAndSettle();
    expect(find.text('These credentials do not match.'), findsOneWidget);
    expect(env.secrets.data.containsKey('auth_token:egy'), isFalse);
  });

  testWidgets('guest -> login -> returns to where the user came from', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: authApi());
    await env.pumpApp(tester);
    // /wishlist is a protected route: a guest lands on login directly.
    await tester.tap(find.text('المفضلة').last);
    await tester.pumpAndSettle();
    expect(find.byType(NavigationBar), findsNothing);
    await tester.enterText(field(0), 'mona@x.test');
    await tester.enterText(field(1), 'password1');
    await tester.tap(find.widgetWithText(FilledButton, 'تسجيل الدخول'));
    await tester.pumpAndSettle();
    // Back on the wishlist tab.
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byType(WishlistPage), findsOneWidget);
    expect(env.secrets.data['auth_token:egy'], '12|secret');
  });

  testWidgets('register then verify the e-mail with the code', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: authApi());
    await env.pumpApp(tester);
    await tester.tap(find.text('حسابي').last);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(OutlinedButton, 'إنشاء حساب'));
    await tester.pumpAndSettle();
    await tester.enterText(field(0), 'Mona');
    await tester.enterText(field(1), 'mona@x.test');
    await tester.enterText(field(3), 'password1');
    await tester.enterText(field(4), 'password1');
    await tester.tap(find.widgetWithText(FilledButton, 'إنشاء حساب'));
    await tester.pumpAndSettle();
    expect(find.text('تأكيد البريد الإلكتروني'), findsWidgets);

    await tester.enterText(field(0), '123456');
    await tester.tap(find.widgetWithText(FilledButton, 'تأكيد'));
    await tester.pumpAndSettle();
    expect(env.requestsTo('/auth/email/verify').single.data, {
      'code': '123456',
    });
    expect(find.byType(NavigationBar), findsOneWidget);
  });

  testWidgets('forgot password -> reset with code -> login screen', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: authApi());
    await env.pumpApp(tester);
    await tester.tap(find.text('حسابي').last);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'تسجيل الدخول'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('نسيت كلمة المرور؟'));
    await tester.pumpAndSettle();
    await tester.enterText(field(0), 'mona@x.test');
    await tester.tap(find.widgetWithText(FilledButton, 'إرسال الرمز'));
    await tester.pumpAndSettle();
    // Reset page, e-mail prefilled.
    expect(
      tester.widget<TextFormField>(field(0)).controller?.text,
      'mona@x.test',
    );
    await tester.enterText(field(1), '654321');
    await tester.enterText(field(2), 'newpassword1');
    await tester.enterText(field(3), 'newpassword1');
    await tester.tap(find.widgetWithText(FilledButton, 'تغيير كلمة المرور'));
    await tester.pumpAndSettle();
    final sent =
        env.requestsTo('/auth/reset-password').single.data
            as Map<Object?, Object?>;
    expect(sent['code'], '654321');
    expect(sent['password_confirmation'], 'newpassword1');
    expect(find.text('تسجيل الدخول'), findsWidgets);
  });

  testWidgets('a 401 on an authenticated call sends the user to login', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true, 'tenant_slug': 'egy'},
      extra: (o) => o.path == '/wishlist'
          ? errorEnvelope(401, 'Unauthenticated.')
          : authApi()(o),
    );
    await env.init();
    await env.secrets.write('auth_token:egy', 'stale');
    await tester.pumpWidget(envApp(env));
    await settle(tester);
    expect(find.byType(NavigationBar), findsOneWidget);

    final container = ProviderScope.containerOf(
      tester.element(find.byType(NavigationBar)),
    );
    expect(container.read(authControllerProvider).signedIn, isTrue);
    // Fire the request without awaiting (fake-async zone), ignore its error.
    container
        .read(dioProvider)
        .get<Object?>('/wishlist')
        .then<void>((_) {}, onError: (Object _) {});
    await settle(tester);

    expect(container.read(authControllerProvider).signedIn, isFalse);
    expect(env.secrets.data.containsKey('auth_token:egy'), isFalse);
    expect(find.byType(NavigationBar), findsNothing);
    expect(find.text('انتهت جلستك. سجّل الدخول مرة أخرى.'), findsOneWidget);
  });
}
