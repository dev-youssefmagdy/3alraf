import 'package:app/features/tenant/presentation/country_list.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('single active tenant is auto-selected: no selection screen', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true});
    await env.pumpApp(tester);
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.text('اختر بلدك'), findsNothing);
    expect(env.prefs.getString('tenant_slug'), 'egy');
    expect(env.requestsTo('/store').single.headers['X-Tenant'], 'egy');
  });

  testWidgets('first launch shows onboarding, skip continues to Home', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv();
    await env.pumpApp(tester);
    expect(find.text('تخطي'), findsOneWidget);
    await tester.tap(find.text('تخطي'));
    await tester.pumpAndSettle();
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(env.prefs.getBool('onboarding_done'), isTrue);
  });

  testWidgets('many tenants: selection screen, pick, confirm, Home', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      tenants: [tenantJson('egy'), tenantJson('sa')],
      prefs: {'onboarding_done': true},
    );
    await env.pumpApp(tester);
    expect(find.text('اختر بلدك'), findsOneWidget);
    expect(find.text('Store egy'), findsOneWidget);
    expect(find.text('Store sa'), findsOneWidget);
    expect(find.byType(TextField), findsNothing); // <= 8: no search

    final continueBtn = find.widgetWithText(FilledButton, 'متابعة');
    expect(tester.widget<FilledButton>(continueBtn).onPressed, isNull);
    await tester.tap(find.text('Store sa'));
    await tester.pump();
    await tester.tap(continueBtn);
    await tester.pumpAndSettle();
    expect(find.text('التسوّق من Store sa؟'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'تأكيد'));
    await tester.pumpAndSettle();

    expect(find.byType(NavigationBar), findsOneWidget);
    expect(env.prefs.getString('tenant_slug'), 'sa');
  });

  testWidgets('more than 8 tenants shows a working search', (tester) async {
    bigScreen(tester);
    final env = TestEnv(
      tenants: [
        for (var i = 0; i < kCountrySearchThreshold + 2; i++) tenantJson('c$i'),
      ],
      prefs: {'onboarding_done': true},
    );
    await env.pumpApp(tester);
    expect(find.byType(TextField), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'c3');
    await tester.pump();
    expect(find.text('Store c3'), findsOneWidget);
    expect(find.text('Store c4'), findsNothing);
    await tester.enterText(find.byType(TextField), 'zzz');
    await tester.pump();
    expect(find.text('لا توجد متاجر مطابقة'), findsOneWidget);
  });

  testWidgets('saved tenant no longer active: cleared + notice', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      tenants: [tenantJson('egy'), tenantJson('sa')],
      prefs: {'onboarding_done': true, 'tenant_slug': 'gone'},
    );
    await env.pumpApp(tester);
    expect(find.text('اختر بلدك'), findsOneWidget);
    expect(
      find.text('متجرك السابق لم يعد متاحًا. اختر بلدًا آخر للمتابعة.'),
      findsOneWidget,
    );
    expect(env.prefs.getString('tenant_slug'), isNull);
  });

  testWidgets('unreachable and nothing saved: full-screen retry', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(tenantsDown: true, prefs: {'onboarding_done': true});
    await env.pumpApp(tester);
    expect(find.text('إعادة المحاولة'), findsOneWidget);
    env.tenantsDown = false;
    await tester.tap(find.text('إعادة المحاولة'));
    await settle(tester);
    expect(find.byType(NavigationBar), findsOneWidget);
  });

  testWidgets('offline with saved tenant: Home with retry banner', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      tenantsDown: true,
      prefs: {'onboarding_done': true, 'tenant_slug': 'egy'},
    );
    await env.pumpApp(tester);
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.text('أنت غير متصل. نعرض بيانات محفوظة.'), findsOneWidget);
    env.tenantsDown = false;
    await tester.tap(find.widgetWithText(TextButton, 'إعادة المحاولة'));
    await settle(tester);
    expect(find.text('أنت غير متصل. نعرض بيانات محفوظة.'), findsNothing);
  });

  testWidgets('maintenance and forced update gates', (tester) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      appConfig: {'maintenance': true, 'maintenance_message': 'Back soon'},
    );
    await env.pumpApp(tester);
    expect(find.text('Back soon'), findsOneWidget);
    expect(find.byType(NavigationBar), findsNothing);
  });

  testWidgets(
    'switch country: confirm dialog, cancel keeps, confirm switches',
    (tester) async {
      bigScreen(tester);
      final env = TestEnv(
        tenants: [
          tenantJson('egy'),
          tenantJson('sa', locale: 'en'),
        ],
        prefs: {'onboarding_done': true, 'tenant_slug': 'egy'},
      );
      await env.init();
      await env.secrets.write('auth_token:egy', 'tok');
      await env.secrets.write('cart_token:egy', 'cart');
      await tester.pumpWidget(envApp(env));
      await tester.pumpAndSettle();
      expect(find.byType(NavigationBar), findsOneWidget);

      Future<void> openSwitcher() async {
        await tester.tap(find.text('حسابي').last);
        await tester.pumpAndSettle();
        await tester.tap(find.text('تغيير البلد'));
        await tester.pumpAndSettle();
      }

      await openSwitcher();
      expect(find.text('الحالي'), findsOneWidget); // current store marked
      await tester.tap(find.text('Store sa'));
      await tester.pumpAndSettle();
      expect(find.text('تغيير المتجر؟'), findsOneWidget);
      await tester.tap(find.text('إلغاء'));
      await tester.pumpAndSettle();
      expect(env.prefs.getString('tenant_slug'), 'egy');
      expect(env.secrets.data['auth_token:egy'], 'tok');

      await openSwitcher();
      await tester.tap(find.text('Store sa'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'تغيير'));
      await tester.pumpAndSettle();

      expect(env.prefs.getString('tenant_slug'), 'sa');
      expect(env.secrets.data.containsKey('auth_token:egy'), isFalse);
      expect(env.secrets.data.containsKey('cart_token:egy'), isFalse);
      expect(env.requestsTo('/auth/logout'), hasLength(1));
      // Locale switched to the new store's default (en) and Home shown.
      expect(find.text('Home'), findsWidgets);
      expect(find.byType(NavigationBar), findsOneWidget);
    },
  );
}
