import 'package:app/core/l10n/locale_provider.dart';
import 'package:app/core/network/network_providers.dart';
import 'package:app/core/storage/storage_providers.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/tenant/application/startup_controller.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:app/features/tenant/domain/tenant_decision.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

void main() {
  test('select binds context, persists slug, applies default locale', () async {
    final env = TestEnv(
      tenants: [
        tenantJson('egy', locale: 'ar'),
        tenantJson('uk', locale: 'en'),
      ],
    );
    final c = await env.container();
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('uk', locale: 'en')));
    expect(c.read(apiContextProvider).tenantSlug, 'uk');
    expect(c.read(preferencesProvider).tenantSlug, 'uk');
    expect(c.read(localeProvider).languageCode, 'en');
    expect(c.read(apiContextProvider).locale, 'en');
  });

  test('switchTo revokes old token, wipes old secrets, binds new', () async {
    final env = TestEnv(
      tenants: [
        tenantJson('egy'),
        tenantJson('sa', locale: 'en'),
      ],
      extra: (o) => o.path == '/auth/me'
          ? envelope({
              'id': 1,
              'name': 'Mona',
              'email': 'm@x.test',
              'email_verified': true,
            })
          : null,
    );
    final c = await env.container();
    final tenants = c.read(tenantControllerProvider.notifier);
    final storage = c.read(sessionStorageProvider);
    await storage.saveAuthToken('egy', 'tok-egy');
    await storage.saveCartToken('egy', 'cart-egy');
    await storage.saveAuthToken('sa', 'tok-sa-old');

    await tenants.select(Tenant.fromJson(tenantJson('egy')));
    expect(c.read(authControllerProvider).signedIn, isTrue);
    await c.read(preferencesProvider).setStoreCache('egy', 'x');

    await tenants.switchTo(Tenant.fromJson(tenantJson('sa', locale: 'en')));

    final logout = env.requestsTo('/auth/logout');
    expect(logout, hasLength(1));
    expect(logout.single.headers['X-Tenant'], 'egy');
    expect(logout.single.headers['Authorization'], 'Bearer tok-egy');

    expect(await storage.authToken('egy'), isNull);
    expect(await storage.cartToken('egy'), isNull);
    expect(c.read(preferencesProvider).storeCache('egy'), isNull);
    expect(c.read(apiContextProvider).tenantSlug, 'sa');
    expect(c.read(preferencesProvider).tenantSlug, 'sa');
    expect(c.read(localeProvider).languageCode, 'en');
    // The new store's own (older) session is its own namespace.
    expect(c.read(tenantControllerProvider).slug, 'sa');
    expect(c.read(tenantControllerProvider).store?.slug, 'sa');
    expect(env.requestsTo('/store').last.headers['X-Tenant'], 'sa');
  });

  test('chooseLocale is per tenant and limited to tenant locales', () async {
    final env = TestEnv();
    final c = await env.container();
    final ctl = c.read(tenantControllerProvider.notifier);
    await ctl.select(Tenant.fromJson(tenantJson('egy')));
    await ctl.chooseLocale('en');
    expect(c.read(localeProvider).languageCode, 'en');
    expect(c.read(preferencesProvider).locale('egy'), 'en');
    await ctl.chooseLocale('fr');
    expect(c.read(localeProvider).languageCode, 'en');
  });

  group('startup', () {
    test('saved tenant gone -> cleared, cart token wiped, selection', () async {
      final env = TestEnv(
        tenants: [tenantJson('egy'), tenantJson('sa')],
        prefs: {'tenant_slug': 'old'},
      );
      final c = await env.container();
      await c.read(sessionStorageProvider).saveCartToken('old', 'cart');
      final result = await c.read(startupControllerProvider.notifier).run();
      expect(result.decision, isA<ShowSelection>());
      expect(c.read(preferencesProvider).tenantSlug, isNull);
      expect(await c.read(sessionStorageProvider).cartToken('old'), isNull);
      expect(c.read(tenantControllerProvider).slug, isNull);
    });

    test('single tenant is auto-selected and persisted', () async {
      final env = TestEnv();
      final c = await env.container();
      final result = await c.read(startupControllerProvider.notifier).run();
      expect(result.decision, isA<AutoSelectTenant>());
      expect(c.read(preferencesProvider).tenantSlug, 'egy');
      expect(c.read(apiContextProvider).tenantSlug, 'egy');
    });

    test(
      'unreachable with saved tenant -> offline, cached store used',
      () async {
        final env = TestEnv(tenantsDown: true, prefs: {'tenant_slug': 'egy'});
        final c = await env.container();
        await c
            .read(preferencesProvider)
            .setStoreCache(
              'egy',
              StoreInfo.fromJson(storeJson('egy')).encode(),
            );
        final result = await c.read(startupControllerProvider.notifier).run();
        expect(result.offline, isTrue);
        expect(c.read(tenantControllerProvider).slug, 'egy');
        expect(c.read(tenantControllerProvider).store?.name, 'Store egy');
        expect(c.read(startupControllerProvider).failed, isFalse);

        env.tenantsDown = false;
        final decision = await c
            .read(startupControllerProvider.notifier)
            .recheck();
        expect(decision, isA<UseSavedTenant>());
        expect(c.read(startupControllerProvider).result!.offline, isFalse);
      },
    );

    test('unreachable and nothing saved -> failed state', () async {
      final env = TestEnv(tenantsDown: true);
      final c = await env.container();
      await c.read(startupControllerProvider.notifier).run();
      expect(c.read(startupControllerProvider).failed, isTrue);
    });

    test('app-config gates are exposed', () async {
      final env = TestEnv(
        appConfig: {'maintenance': true, 'maintenance_message': 'soon'},
      );
      final c = await env.container();
      final r = await c.read(startupControllerProvider.notifier).run();
      expect(r.route(onboardingDone: true), '/maintenance');
      final env2 = TestEnv(appConfig: {'update_required': true});
      final c2 = await env2.container();
      final r2 = await c2.read(startupControllerProvider.notifier).run();
      expect(r2.route(onboardingDone: false), '/update-required');
    });
  });
}
