import 'package:app/core/error/api_exception.dart';
import 'package:app/features/addresses/application/address_controller.dart';
import 'package:app/features/addresses/data/address_models.dart';
import 'package:app/features/orders/application/orders_controller.dart';
import 'package:app/features/orders/application/payment_poller.dart';
import 'package:app/features/orders/data/order_models.dart';
import 'package:app/features/orders/presentation/payment_page.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/commerce_fixtures.dart';
import '../../support/harness.dart';

Future<void> tick([int ms = 80]) =>
    Future<void>.delayed(Duration(milliseconds: ms));

void main() {
  test('Order parsing: strings stay strings, flags and nested lists', () {
    final o = Order.fromJson(
      orderJson(status: 'delivered', canUploadProof: true),
    );
    expect(o.total, '340.00');
    expect(o.statusValue, 'delivered');
    expect(o.canReturn, isTrue);
    expect(o.canCancel, isFalse);
    expect(o.canUploadProof, isTrue);
    expect(o.items.single.returnable, 2); // 3 bought, 1 returned
    expect(o.timeline.single.status, 'pending');
    expect(Order.fromJson(orderJson()).canCancel, isTrue);
    expect(nextActionOf('upload_proof'), NextAction.uploadProof);
    expect(nextActionOf('anything'), NextAction.none);
  });

  test('isPaymentReturnUrl recognises the gateway hand-back only', () {
    expect(
      isPaymentReturnUrl('https://egy.3alraf.com/ar/payments/return/abc'),
      isTrue,
    );
    expect(isPaymentReturnUrl('https://pay.test/checkout/123'), isFalse);
    expect(isPaymentReturnUrl('not a url ::'), isFalse);
  });

  group('pollPayment', () {
    Future<(List<PaymentReturn>, int)> run(List<bool> answers, int max) async {
      var i = 0;
      var waits = 0;
      final env = TestEnv(
        extra: (o) {
          if (o.path == '/payments/return/tok-1') {
            final paid = answers[i < answers.length ? i : answers.length - 1];
            i++;
            return envelopeWithMeta(orderJson(), {
              'paid': paid,
              'authoritative': false,
            });
          }
          return null;
        },
      );
      final c = await env.container();
      final res = await pollPayment(
        c.read(ordersRepositoryProvider),
        'tok-1',
        attempts: max,
        wait: (_) async => waits++,
      );
      return ([res], waits);
    }

    test('stops as soon as the order is paid', () async {
      final (r, waits) = await run([false, false, true], 6);
      expect(r.single.paid, isTrue);
      expect(waits, 2);
    });

    test('gives up with paid=false after the attempts', () async {
      final (r, waits) = await run([false], 3);
      expect(r.single.paid, isFalse);
      expect(waits, 2);
    });
  });

  test('orders list pages with infinite scroll', () async {
    final env = TestEnv(
      extra: (o) {
        switch (o.path) {
          case '/auth/me':
            return envelope({'id': 1, 'name': 'M', 'email': 'm@x.test'});
          case '/orders':
            final page = o.queryParameters['page'] as int;
            return envelopeWithMeta(
              [orderJson(token: 't$page', number: 'ORD-$page')],
              {
                'pagination': {
                  'current_page': page,
                  'last_page': 2,
                  'per_page': 10,
                  'total': 2,
                },
              },
            );
        }
        return null;
      },
    );
    final c = await env.container();
    await env.secrets.write('auth_token:egy', '12|secret');
    c.listen(ordersControllerProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick(150);
    var s = c.read(ordersControllerProvider);
    expect(s.orders.length, 1);
    expect(s.hasMore, isTrue);
    await c.read(ordersControllerProvider.notifier).loadMore();
    s = c.read(ordersControllerProvider);
    expect(s.orders.map((o) => o.number), ['ORD-1', 'ORD-2']);
    expect(s.hasMore, isFalse);
  });

  test('cancel, return, proof upload and invoice request shapes', () async {
    final env = TestEnv(
      extra: (o) {
        switch ('${o.method} ${o.path}') {
          case 'POST /orders/tok-1/cancel':
            return envelope(orderJson(status: 'cancelled'));
          case 'POST /orders/tok-1/return':
            return envelope({
              'number': 'RET-1',
              'status': 'requested',
              'reason': 'broken',
              'refund_amount': '',
            }, status: 201);
          case 'POST /orders/tok-1/proof':
            return envelope(orderJson());
          case 'POST /orders/tok-1/pay':
            return envelope({'payment_url': 'https://pay.test/z'});
        }
        return null;
      },
    );
    final c = await env.container();
    final repo = c.read(ordersRepositoryProvider);

    final cancelled = await repo.cancel('tok-1', 'changed_mind');
    expect(cancelled.statusValue, 'cancelled');
    expect(env.requestsTo('/orders/tok-1/cancel').single.data, {
      'reason_code': 'changed_mind',
    });

    final ret = await repo.requestReturn(
      'tok-1',
      quantities: {5: 2, 6: 0},
      reason: ' broken ',
    );
    expect(ret.number, 'RET-1');
    expect(env.requestsTo('/orders/tok-1/return').single.data, {
      'items': [
        {'order_item_id': 5, 'quantity': 2},
      ],
      'reason': 'broken',
    });

    final file = await writeTemp('p.jpg');
    await repo.uploadProof('tok-1', file);
    final form = env.requestsTo('/orders/tok-1/proof').single.data as FormData;
    expect(form.files.single.key, 'proof');

    expect(await repo.pay('tok-1'), 'https://pay.test/z');
  });

  test('addresses: CRUD bodies and 422 surfaces as field errors', () async {
    var reject = false;
    final env = TestEnv(
      extra: (o) {
        switch ('${o.method} ${o.path}') {
          case 'GET /auth/me':
            return envelope({'id': 1, 'name': 'M', 'email': 'm@x.test'});
          case 'GET /addresses':
            return envelope([addressJson(1, def: true)]);
          case 'POST /addresses':
            if (reject) {
              return errorEnvelope(422, 'The phone is invalid.', {
                'phone': ['The phone is invalid.'],
              });
            }
            return envelope(addressJson(2), status: 201);
          case 'DELETE /addresses/1':
            return envelope(null);
        }
        return null;
      },
    );
    final c = await env.container();
    await env.secrets.write('auth_token:egy', '12|secret');
    c.listen(addressesProvider, (_, _) {});
    await c
        .read(tenantControllerProvider.notifier)
        .select(Tenant.fromJson(tenantJson('egy')));
    await tick(150);
    expect(c.read(addressesProvider).items.single.isDefault, isTrue);

    final n = c.read(addressesProvider.notifier);
    const input = AddressInput(
      name: 'Ali',
      phone: '1',
      cityId: 1,
      address: 'St',
      isDefault: true,
    );
    reject = true;
    try {
      await n.save(input);
      fail('expected 422');
    } on ApiException catch (e) {
      expect(e.firstErrorFor('phone'), 'The phone is invalid.');
    }
    reject = false;
    final saved = await n.save(input);
    expect(saved.id, 2);
    expect(
      env.requestsTo('/addresses').where((r) => r.method == 'POST').last.data,
      {
        'name': 'Ali',
        'phone': '1',
        'city_id': 1,
        'address': 'St',
        'is_default': true,
      },
    );
    expect(await n.remove(c.read(addressesProvider).items.first), isNull);
  });
}
