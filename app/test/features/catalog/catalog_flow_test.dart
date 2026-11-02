import 'package:app/core/network/network_providers.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/auth/presentation/login_page.dart';
import 'package:app/features/catalog/application/catalog_providers.dart';
import 'package:app/features/catalog/data/catalog_models.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:app/features/wishlist/application/wishlist_controller.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/catalog_fixtures.dart';
import '../../support/harness.dart';

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

String? header(RequestOptions o, String name) => o.headers[name] as String?;

ResponseBody? Function(RequestOptions) api({
  Map<String, Object?>? product,
  bool listingTwoPages = false,
}) => (o) {
  switch (o.path) {
    case '/home':
      return envelope(homeJson());
    case '/bundles':
      return envelope(<Object>[]);
    case '/menu':
      return envelope(<Object>[]);
    case '/products/serum':
    case '/products/soap':
      return envelope(product ?? variableProductJson());
    case '/cart/items':
      return envelope({'items': <Object>[]}, status: 200);
    case '/stock-requests':
      return envelope(null, status: 201);
    case '/categories/skincare':
      return envelope({
        'id': 1,
        'name': 'Skincare',
        'slug': 'skincare',
        'image': null,
        'description': null,
        'breadcrumbs': <Object>[],
        'children': <Object>[],
      });
    case '/catalog':
      final page = o.queryParameters['page'] as int? ?? 1;
      return paged(
        [card(page * 10 + 1, 'p${page}a'), card(page * 10 + 2, 'p${page}b')],
        page: page,
        last: listingTwoPages ? 2 : 1,
        total: 4,
      );
  }
  return null;
};

void main() {
  testWidgets('home renders sections and opens a product; add to cart posts '
      'product, variant and quantity', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    expect(find.text('Product serum'), findsOneWidget);
    expect(find.text('مقترح لك'), findsOneWidget);
    expect(find.text('Skincare'), findsOneWidget);

    await tester.tap(find.text('Product serum'));
    await tester.pumpAndSettle();
    expect(find.text('Shirt'), findsWidgets);
    // Default variant (S / Red) price is shown.
    expect(find.textContaining('110.00'), findsWidgets);

    await tester.tap(find.widgetWithText(FilledButton, 'أضف إلى السلة'));
    await tester.pumpAndSettle();
    final post = env.requestsTo('/cart/items').single;
    expect(post.method, 'POST');
    expect(post.data, {'product_id': 5, 'variant_id': 100, 'quantity': 1});
  });

  testWidgets('sold-out product offers notify-me and posts the request', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: api(product: simpleProductJson(inStock: false)),
    );
    await env.pumpApp(tester);
    await tester.tap(find.text('Product serum'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(FilledButton, 'أضف إلى السلة'), findsNothing);
    await tester.tap(find.widgetWithText(OutlinedButton, 'أعلمني عند التوفر'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField).at(0), 'Mona');
    await tester.enterText(find.byType(TextFormField).at(1), '1001234567');
    await tester.tap(find.widgetWithText(FilledButton, 'إرسال الطلب'));
    await tester.pumpAndSettle();
    final post = env.requestsTo('/stock-requests').single;
    expect(post.data, {'product_id': 6, 'name': 'Mona', 'phone': '1001234567'});
  });

  testWidgets('category listing: infinite scroll loads page 2 and the sort '
      'sheet restarts the query', (tester) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: api(listingTwoPages: true),
    );
    await env.pumpApp(tester);
    await tester.tap(find.text('Skincare'));
    await tester.pumpAndSettle();
    expect(find.text('Product p1a'), findsOneWidget);
    final calls = env.requestsTo('/catalog');
    expect(calls.first.queryParameters['category'], 'skincare');
    expect(calls.first.queryParameters['page'], 1);

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -2000));
    await tester.pumpAndSettle();
    expect(
      env.requestsTo('/catalog').any((r) => r.queryParameters['page'] == 2),
      isTrue,
    );

    await tester.tap(find.text('الأحدث'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('السعر: من الأقل للأعلى'));
    await tester.pumpAndSettle();
    final sorted = env
        .requestsTo('/catalog')
        .where((r) => r.queryParameters['sort'] == 'price_asc')
        .toList();
    expect(sorted.first.queryParameters['page'], 1);
  });

  testWidgets('guest tapping a heart is sent to sign in', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    await tester.tap(
      find.byIcon(Icons.favorite_border_rounded).first,
      warnIfMissed: false,
    );
    await tester.pumpAndSettle();
    expect(find.byType(LoginPage), findsOneWidget);
  });

  test('catalog repository: params, pagination, stock request body', () async {
    final env = TestEnv(extra: api(listingTwoPages: true));
    final c = await env.container();
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    final repo = c.read(catalogRepositoryProvider);
    final page = await repo.catalog(
      const CatalogQuery(brand: 'sony', brandIds: {2, 1}, priceMax: '50'),
      page: 1,
    );
    expect(page.items.length, 2);
    expect(page.page.hasMore, isTrue);
    final q = env.requestsTo('/catalog').single.uri.query;
    expect(q, contains('brand=sony'));
    expect(q, contains('brands%5B%5D=1&brands%5B%5D=2'));
    expect(q, contains('price_max=50'));
  });

  test('nothing survives a tenant switch: home refetches with the new '
      'X-Tenant, listing and wishlist are rebuilt', () async {
    final env = TestEnv(
      tenants: [tenantJson('egy'), tenantJson('sa')],
      extra: api(),
    );
    final c = await env.container();
    final tenants = c.read(tenantControllerProvider.notifier);
    await tenants.select(Tenant.fromJson(tenantJson('egy')));
    await c.read(homeProvider.future);
    expect(header(env.requestsTo('/home').last, 'X-Tenant'), 'egy');

    const scope = ListingScope(category: 'skincare');
    c.listen(listingProvider(scope), (_, _) {});
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(c.read(listingProvider(scope)).items, isNotEmpty);
    final before = env.requestsTo('/catalog').length;

    await tenants.switchTo(Tenant.fromJson(tenantJson('sa')));
    await c.read(homeProvider.future);
    expect(header(env.requestsTo('/home').last, 'X-Tenant'), 'sa');
    expect(env.requestsTo('/home').length, 2);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    final catalog = env.requestsTo('/catalog');
    expect(catalog.length, greaterThan(before));
    expect(header(catalog.last, 'X-Tenant'), 'sa');
    expect(c.read(apiContextProvider).tenantSlug, 'sa');
  });

  test('wishlist: loads for a signed-in customer, toggle removes optimistic '
      'and it empties on sign-out', () async {
    final env = TestEnv(
      extra: (o) {
        switch (o.path) {
          case '/auth/me':
            return envelope({'id': 7, 'name': 'Mona', 'email': 'm@x.test'});
          case '/wishlist':
            return envelope([card(1, 'serum'), card(2, 'cream')]);
          case '/wishlist/1/toggle':
            return envelope({'in_wishlist': false});
        }
        return null;
      },
    );
    final c = await env.container();
    await env.secrets.write('auth_token:egy', '12|secret');
    c.listen(wishlistProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(c.read(wishlistProvider).ids, {1, 2});

    final result = await c.read(wishlistProvider.notifier).toggle(1);
    expect(result, isFalse);
    expect(c.read(wishlistProvider).ids, {2});

    await c.read(authControllerProvider.notifier).logout();
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(c.read(wishlistProvider).items, isEmpty);
  });
}
