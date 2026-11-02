import 'package:app/core/network/network_providers.dart';
import 'package:app/features/auth/application/auth_controller.dart';
import 'package:app/features/cart/application/cart_controller.dart';
import 'package:app/features/cart/data/cart_models.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/commerce_fixtures.dart';
import '../../support/harness.dart';

/// Scripted cart backend: one mutable cart.
class CartBackend {
  CartBackend() : cart = cartJson();

  Map<String, Object?> cart;
  bool failQuantity = false;
  bool rejectCode = false;

  ResponseBody? call(RequestOptions o) {
    switch ('${o.method} ${o.path}') {
      case 'GET /cart':
        return envelope(
          cart,
          headers: {
            'x-cart-token': ['guest-1'],
          },
        );
      case 'POST /cart/items':
        cart = cartJson(
          items: [lineJson(1, quantity: 3)],
          totals: totalsJson(subtotal: '300.00', total: '300.00'),
        );
        return envelope(cart);
      case 'PUT /cart/items/1':
        if (failQuantity) return errorEnvelope(422, 'Not enough stock.');
        final q = (o.data as Map)['quantity'] as int;
        cart = cartJson(
          items: [lineJson(1, quantity: q)],
          totals: totalsJson(subtotal: '${q * 100}.00', total: '${q * 100}.00'),
        );
        return envelope(cart);
      case 'DELETE /cart/items/1':
        cart = cartJson(
          items: [],
          totals: totalsJson(subtotal: '0.00', total: '0.00'),
        );
        return envelope(cart);
      case 'POST /cart/quote':
        if (rejectCode) {
          return errorEnvelope(422, 'Invalid code.', {
            'discount_code': ['This code is not valid.'],
          });
        }
        return envelope(
          cartJson(
            totals: totalsJson(discount: '20.00', total: '180.00'),
          ),
        );
      case 'POST /cart/merge':
        return envelope(cart);
    }
    return null;
  }
}

Future<void> tick() => Future<void>.delayed(const Duration(milliseconds: 60));

void main() {
  test('loads, add replaces the cart with the server one (count from '
      'server), guest token is echoed on later calls', () async {
    final b = CartBackend();
    final env = TestEnv(extra: b.call);
    final c = await env.container();
    c.listen(cartControllerProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick();
    expect(c.read(cartCountProvider), 2);
    expect(c.read(cartControllerProvider).cart!.totals.total, '200.00');

    final msg = await c
        .read(cartControllerProvider.notifier)
        .add(productId: 11, variantId: 0, quantity: 1);
    expect(msg, isNull);
    expect(c.read(cartCountProvider), 3);
    final post = env.requestsTo('/cart/items').single;
    expect(post.data, {'product_id': 11, 'variant_id': 0, 'quantity': 1});
    expect(post.headers['X-Cart-Token'], 'guest-1');
  });

  test('stepper: optimistic quantity, server totals win; failure rolls '
      'back', () async {
    final b = CartBackend();
    final env = TestEnv(extra: b.call);
    final c = await env.container();
    c.listen(cartControllerProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick();
    final line = c.read(cartControllerProvider).cart!.items.first;
    final notifier = c.read(cartControllerProvider.notifier);

    final pending = notifier.setQuantity(line, 5);
    expect(c.read(cartCountProvider), 5); // optimistic count
    expect(await pending, isNull);
    expect(c.read(cartControllerProvider).cart!.totals.total, '500.00');

    b.failQuantity = true;
    final err = await notifier.setQuantity(
      c.read(cartControllerProvider).cart!.items.first,
      9,
    );
    expect(err?.message, 'Not enough stock.');
    expect(c.read(cartCountProvider), 5); // rolled back
    expect(c.read(cartControllerProvider).cart!.totals.total, '500.00');
  });

  test('remove empties the cart', () async {
    final env = TestEnv(extra: CartBackend().call);
    final c = await env.container();
    c.listen(cartControllerProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick();
    final line = c.read(cartControllerProvider).cart!.items.first;
    expect(await c.read(cartControllerProvider.notifier).remove(line), isNull);
    expect(c.read(cartControllerProvider).cart!.isEmpty, isTrue);
  });

  test('discount code: accepted shows server totals, rejected keeps the '
      'inline 422 text and the old cart', () async {
    final b = CartBackend();
    final env = TestEnv(extra: b.call);
    final c = await env.container();
    c.listen(cartControllerProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick();
    final n = c.read(cartControllerProvider.notifier);

    b.rejectCode = true;
    expect(await n.applyCode('BAD'), isFalse);
    expect(c.read(cartControllerProvider).codeError, 'This code is not valid.');
    expect(c.read(cartControllerProvider).code, isNull);
    expect(c.read(cartControllerProvider).cart!.totals.total, '200.00');

    b.rejectCode = false;
    expect(await n.applyCode(' SAVE '), isTrue);
    final s = c.read(cartControllerProvider);
    expect(s.code, 'SAVE');
    expect(s.cart!.totals.discount, '20.00');
    expect(s.cart!.totals.total, '180.00');
    expect(env.requestsTo('/cart/quote').last.data, {'discount_code': 'SAVE'});
  });

  test('tenant switch empties the cart and refetches with the new '
      'X-Tenant', () async {
    final env = TestEnv(
      tenants: [tenantJson('egy'), tenantJson('sa')],
      extra: CartBackend().call,
    );
    final c = await env.container();
    c.listen(cartControllerProvider, (_, _) {});
    final tenants = c.read(tenantControllerProvider.notifier);
    await tenants.select(Tenant.fromJson(tenantJson('egy')));
    await tick();
    expect(c.read(cartCountProvider), 2);
    await tenants.switchTo(Tenant.fromJson(tenantJson('sa')));
    await tick();
    final gets = env.requestsTo('/cart');
    expect(gets.last.headers['X-Tenant'], 'sa');
    expect(c.read(apiContextProvider).tenantSlug, 'sa');
  });

  test(
    'sign-in merges the guest cart BEFORE the customer cart loads',
    () async {
      final env = TestEnv(
        extra: (o) {
          if (o.path == '/auth/login') {
            return envelope({
              'token': '9|tok',
              'customer': {'id': 1, 'name': 'Mona', 'email': 'm@x.test'},
            });
          }
          return CartBackend().call(o);
        },
      );
      final c = await env.container();
      c.listen(cartControllerProvider, (_, _) {});
      await c
          .read(tenantControllerProvider.notifier)
          .select(Tenant.fromJson(tenantJson('egy')));
      await tick(); // guest cart loaded, token 'guest-1' persisted
      final before = env.adapter.requests.length;
      await c
          .read(authControllerProvider.notifier)
          .login('m@x.test', 'secret123');
      await tick();
      final after = env.adapter.requests
          .skip(before)
          .map((r) => '${r.method} ${r.path}')
          .toList();
      final merge = after.indexOf('POST /cart/merge');
      final reload = after.indexOf('GET /cart');
      expect(merge, isNonNegative);
      expect(reload, greaterThan(merge));
    },
  );

  test('isZeroMoney only gates row visibility', () {
    expect(isZeroMoney('0.00'), isTrue);
    expect(isZeroMoney(''), isTrue);
    expect(isZeroMoney(null), isTrue);
    expect(isZeroMoney('12.50'), isFalse);
  });

  test('cart parsing is total: missing fields fall back to safe defaults', () {
    final cart = Cart.fromJson({
      'items': [
        {'id': 3, 'quantity': 4},
      ],
    });
    expect(cart.count, 4);
    expect(cart.totals.total, '0.00');
    expect(cart.items.first.available, isTrue);
  });
}
