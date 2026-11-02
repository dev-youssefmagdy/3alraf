import 'package:app/core/error/api_exception.dart';
import 'package:app/features/affiliate/application/affiliate_controller.dart';
import 'package:app/features/affiliate/data/affiliate_models.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:app/core/routing/app_router.dart';

import '../../support/harness.dart';

Map<String, Object?> affiliate() => {
  'id': 1,
  'name': 'Aff',
  'email': 'aff@x.test',
  'status': 'approved',
  'ref': 'uuid-1',
};

Map<String, Object?> dashboard() => {
  'affiliate': affiliate(),
  'stats': {
    'clicks': 5,
    'orders': 2,
    'conversion_percent': 40,
    'lifetime_sales': '500.00',
    'tier': {'name': 'Starter', 'percent': '5.00', 'min_sales': '0.00'},
    'next_tier': {'name': 'Silver', 'percent': '7.00', 'min_sales': '1000.00'},
    'commissions': {
      'pending': '1.00',
      'approved': '2.00',
      'paid': '3.00',
      'available': '2.00',
    },
  },
  'payouts': [
    {'id': 1, 'amount': '3.00', 'status': 'paid', 'requested_at': null},
  ],
  'recent_commissions': [
    {
      'order': 'ORD-1',
      'amount': '1.00',
      'percent': '5.00',
      'status': 'pending',
    },
  ],
};

ResponseBody? Function(RequestOptions) api({bool ambassadorOff = false}) =>
    (o) {
      switch ((o.method, o.path)) {
        case ('POST', '/affiliate/login'):
          return envelope({
            'token': 'aff-token',
            'abilities': ['affiliate'],
            'affiliate': affiliate(),
          });
        case ('GET', '/affiliate/me'):
          return envelope(affiliate());
        case ('GET', '/affiliate/dashboard'):
          return envelope(dashboard());
        case ('POST', '/affiliate/payouts'):
          return errorEnvelope(422, 'No approved commission available.', {
            'amount': ['No approved commission available.'],
          });
        case ('GET', '/affiliate/ambassador'):
          return ambassadorOff
              ? FakeFeatureOff.body()
              : envelope({
                  'enrolled': true,
                  'ambassador': {'status': 'active', 'disclosure': 'd'},
                  'channels': ['instagram'],
                  'ideas': <Object>[],
                  'links': [
                    {
                      'id': 1,
                      'channel': 'instagram',
                      'product_id': 1,
                      'clicks': 3,
                      'url': 'http://x/l/abc',
                    },
                  ],
                  'report': {'valid_orders': 4, 'window_days': 14},
                });
        case ('GET', '/affiliate/products'):
          return envelope([
            {
              'id': 1,
              'slug': 'mid',
              'name': 'P mid',
              'price': '250.00',
              'link': 'http://x/product/mid?aff=uuid-1',
            },
          ]);
        case ('GET', '/auth/me'):
          return envelope({'id': 7, 'name': 'M', 'email': 'm@x.test'});
        case ('POST', '/affiliate/logout'):
          return envelope(null);
      }
      return null;
    };

class FakeFeatureOff {
  static ResponseBody body() => errorEnvelope(403, 'Feature disabled.');
}

void main() {
  test('models parse dashboard, tiers, commissions, ambassador', () {
    final d = AffiliateDashboard.fromJson(dashboard());
    expect(d.stats.tier?.name, 'Starter');
    expect(d.stats.nextTier?.minSales, '1000.00');
    expect(d.stats.commissions['available'], '2.00');
    expect(d.recentCommissions.single.order, 'ORD-1');
    expect(d.payouts.single.status, 'paid');
    final p = AmbassadorPortal.fromJson({
      'enrolled': true,
      'channels': ['instagram'],
      'report': {'valid_orders': 4, 'window_days': 14},
    });
    expect(p.report['valid_orders'], 4);
    expect(p.links, isEmpty);
  });

  test(
    'login stores a separate token; calls use it, not the customer one',
    () async {
      final env = TestEnv(extra: api());
      final c = await env.container();
      await env.secrets.write('auth_token:egy', 'customer-token');
      await c
          .read(tenantControllerProvider.notifier)
          .select(Tenant.fromJson(tenantJson('egy')));
      await Future<void>.delayed(const Duration(milliseconds: 20));
      final ctrl = c.read(affiliateControllerProvider.notifier);
      await ctrl.login('aff@x.test', 'pw');
      expect(env.secrets.data['affiliate_token:egy'], 'aff-token');
      expect(env.secrets.data['auth_token:egy'], 'customer-token');
      // login itself carried no customer bearer
      final login = env.requestsTo('/affiliate/login').single;
      expect(login.headers['Authorization'], isNull);

      final dash = await c.read(affiliateDashboardProvider.future);
      expect(dash.stats.clicks, 5);
      final req = env.requestsTo('/affiliate/dashboard').single;
      expect(req.headers['Authorization'], 'Bearer aff-token');
    },
  );

  test('saved affiliate token is restored; logout clears it', () async {
    final env = TestEnv(extra: api());
    final c = await env.container();
    await env.secrets.write('affiliate_token:egy', 'aff-token');
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    c.listen(affiliateControllerProvider, (_, _) {});
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(c.read(affiliateControllerProvider).signedIn, isTrue);
    await c.read(affiliateControllerProvider.notifier).logout();
    expect(env.secrets.data.containsKey('affiliate_token:egy'), isFalse);
    expect(c.read(affiliateControllerProvider).signedIn, isFalse);
  });

  test('payout 422 and disabled ambassador surface typed errors', () async {
    final env = TestEnv(extra: api(ambassadorOff: true));
    final c = await env.container();
    await env.secrets.write('affiliate_token:egy', 'aff-token');
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await expectLater(
      c.read(affiliateRepositoryProvider).requestPayout(),
      throwsA(
        isA<ApiException>().having(
          (e) => e.kind,
          'kind',
          ApiErrorKind.validation,
        ),
      ),
    );
    await expectLater(
      c.read(affiliateRepositoryProvider).ambassador(),
      throwsA(isA<ApiException>()),
    );
  });

  testWidgets('affiliate hub: login form -> dashboard -> products + QR', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1000, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    final c = ProviderScope.containerOf(
      tester.element(find.byType(Scaffold).first),
    );
    c.read(routerProvider).go('/affiliate');
    await settle(tester);
    expect(find.byType(TextFormField), findsNWidgets(2));
    await tester.enterText(find.byType(TextFormField).at(0), 'aff@x.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'secret123');
    await tester.tap(find.widgetWithText(FilledButton, 'تسجيل الدخول'));
    await settle(tester);
    expect(find.textContaining('500.00'), findsWidgets);

    c.read(routerProvider).go('/affiliate/products');
    await settle(tester);
    expect(find.text('P mid'), findsOneWidget);
    await tester.tap(find.text('رمز QR'));
    await tester.pumpAndSettle();
    expect(find.byType(QrImageView), findsOneWidget);
  });
}
