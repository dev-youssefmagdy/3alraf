import 'package:app/features/cart/application/cart_controller.dart';
import 'package:app/features/checkout/application/checkout_controller.dart';
import 'package:app/features/checkout/data/checkout_models.dart';
import 'package:app/features/orders/data/order_models.dart';
import 'package:app/features/tenant/application/tenant_controller.dart';
import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/commerce_fixtures.dart';
import '../../support/harness.dart';

Map<String, Object?> method(
  int id,
  String type, {
  bool proof = false,
  String? account,
}) => {
  'id': id,
  'type': type,
  'provider': type,
  'name': 'Method $id',
  'instructions': null,
  'account_number': account,
  'requires_proof': proof,
  'fee_percent': '0.000',
  'fee_fixed': '0.00',
};

class CheckoutBackend {
  CheckoutBackend({this.upfront = false});

  final bool upfront;
  String nextAction = 'redirect_to_payment';
  Object? placeErrors;

  ResponseBody? call(RequestOptions o) {
    switch ('${o.method} ${o.path}') {
      case 'GET /cart':
        return envelope(cartJson());
      case 'GET /auth/me':
        return envelope({'id': 1, 'name': 'Mona', 'email': 'm@x.test'});
      case 'GET /addresses':
        return envelope([addressJson(1), addressJson(2, cityId: 2, def: true)]);
      case 'GET /checkout/packaging':
        return envelope(<Object>[]);
      case 'GET /checkout/payment-methods':
        return envelopeWithMeta(
          [method(1, 'cod'), method(2, 'wallet', proof: true, account: '0100')],
          {'cod_collect_shipping_upfront': upfront},
        );
      case 'GET /checkout/shipping':
        return envelope([
          {
            'shipping_company_id': 7,
            'name': 'Fast',
            'eta_days': '1-2',
            'supports_cod': true,
            'fee': '40.00',
            'free': false,
            'order_total': '240.00',
          },
        ]);
      case 'POST /cart/quote':
        return envelope(
          cartJson(
            totals: totalsJson(shipping: '40.00', total: '240.00'),
          ),
        );
      case 'POST /checkout/validate':
        if (placeErrors != null) {
          return errorEnvelope(422, 'Bad.', placeErrors);
        }
        return envelope(
          cartJson(
            totals: totalsJson(shipping: '40.00', total: '240.00'),
          ),
        );
      case 'POST /checkout':
        if (placeErrors != null) {
          return errorEnvelope(422, 'Bad.', placeErrors);
        }
        return envelopeWithMeta(orderJson(), {
          'next_action': nextAction,
          'payment_url': nextAction == 'none' ? null : 'https://pay.test/x',
        });
    }
    return null;
  }
}

Future<void> tick([int ms = 80]) =>
    Future<void>.delayed(Duration(milliseconds: ms));

Future<(TestEnv, dynamic)> boot(CheckoutBackend b) async {
  final env = TestEnv(extra: b.call);
  final c = await env.container();
  await env.secrets.write('auth_token:egy', '12|secret');
  c.listen(cartControllerProvider, (_, _) {});
  c.listen(checkoutControllerProvider, (_, _) {});
  await c
      .read(tenantControllerProvider.notifier)
      .select(Tenant.fromJson(tenantJson('egy')));
  await tick(150);
  return (env, c);
}

void main() {
  group('stepForErrors', () {
    test('maps a failing field to its earliest step', () {
      expect(
        stepForErrors({
          'city_id': ['x'],
          'proof': ['y'],
        }),
        CheckoutStep.address,
      );
      expect(
        stepForErrors({
          'shipping_company_id': ['x'],
        }),
        CheckoutStep.shipping,
      );
      expect(
        stepForErrors({
          'packaging_option_id': ['x'],
        }),
        CheckoutStep.packaging,
      );
      expect(
        stepForErrors({
          'proof': ['x'],
        }),
        CheckoutStep.payment,
      );
      expect(
        stepForErrors({
          'prepay_method_id': ['x'],
        }),
        CheckoutStep.payment,
      );
    });

    test('discount_code / unknown / empty', () {
      expect(
        stepForErrors({
          'discount_code': ['x'],
        }),
        isNull,
      ); // the cart
      expect(
        stepForErrors({
          'items': ['stock'],
        }),
        CheckoutStep.review,
      );
      expect(stepForErrors({}), CheckoutStep.review);
      expect(
        stepForErrors({
          'discount_code': ['x'],
          'payment_method_id': ['y'],
        }),
        CheckoutStep.payment,
      );
    });
  });

  test('init: default address preselected, its city drives the shipping '
      'query, single option preselected, live quote requested', () async {
    final (env, c) = await boot(CheckoutBackend());
    final s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.addressId, 2);
    expect(s.cityId, 2);
    expect(env.requestsTo('/checkout/shipping').single.queryParameters, {
      'city_id': 2,
    });
    expect(s.shippingId, 7);
    expect(s.quote?.totals.total, '240.00'); // server number, not computed
    expect(s.canLeave(CheckoutStep.address), isTrue);
    expect(s.steps, isNot(contains(CheckoutStep.packaging)));
  });

  test('proof rules: wallet needs a file; COD with upfront shipping needs a '
      'prepay method + file', () async {
    final (_, c) = await boot(CheckoutBackend(upfront: true));
    final n = c.read(checkoutControllerProvider.notifier);
    await n.selectPayment(2);
    var s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.proofRequired, isTrue);
    expect(s.canLeave(CheckoutStep.payment), isFalse);
    n.setProof('/tmp/receipt.jpg');
    s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.canLeave(CheckoutStep.payment), isTrue);

    await n.selectPayment(1); // COD + upfront
    s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.needsPrepay, isTrue);
    expect(s.canLeave(CheckoutStep.payment), isFalse);
    await n.selectPrepay(2);
    n.setProof('/tmp/receipt.jpg');
    s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.canLeave(CheckoutStep.payment), isTrue);
    expect(s.selection().toJson()['prepay_method_id'], 2);
  });

  test('place: posts the selection, returns next action + payment url and '
      'empties the local cart', () async {
    final (env, c) = await boot(CheckoutBackend());
    final n = c.read(checkoutControllerProvider.notifier);
    await n.selectPayment(1);
    final placed = await n.place();
    expect(placed, isNotNull);
    expect(placed!.nextAction, NextAction.redirectToPayment);
    expect(placed.paymentUrl, 'https://pay.test/x');
    final body = env.requestsTo('/checkout').single.data as Map;
    expect(body['address_id'], 2);
    expect(body['shipping_company_id'], 7);
    expect(body['payment_method_id'], 1);
    expect(body.containsKey('proof'), isFalse);
    expect(c.read(cartControllerProvider).cart!.isEmpty, isTrue);
  });

  test('422 on place: field errors kept inline and the step jumps to the '
      'owner', () async {
    final b = CheckoutBackend();
    final (_, c) = await boot(b);
    final n = c.read(checkoutControllerProvider.notifier);
    await n.selectPayment(1);
    n.goTo(CheckoutStep.review);
    b.placeErrors = {
      'shipping_company_id': ['Shipping is not available.'],
    };
    expect(await n.place(), isNull);
    final s = c.read(checkoutControllerProvider) as CheckoutState;
    expect(s.step, CheckoutStep.shipping);
    expect(s.errorFor('shipping_company_id'), 'Shipping is not available.');
    expect(s.working, isFalse);
    expect(s.cartProblem, isFalse);
  });

  test(
    '422 about the discount code sends the shopper back to the cart',
    () async {
      final b = CheckoutBackend();
      final (_, c) = await boot(b);
      final n = c.read(checkoutControllerProvider.notifier);
      b.placeErrors = {
        'discount_code': ['Code expired.'],
      };
      expect(await n.validate(), isFalse);
      final s = c.read(checkoutControllerProvider) as CheckoutState;
      expect(s.cartProblem, isTrue);
      expect(s.errorFor('discount_code'), 'Code expired.');
    },
  );

  test('validate with a proof sends multipart', () async {
    final (env, c) = await boot(CheckoutBackend());
    final n = c.read(checkoutControllerProvider.notifier);
    await n.selectPayment(2);
    // A real file is needed by MultipartFile.fromFile.
    final f = await writeTemp('receipt.jpg');
    n.setProof(f);
    expect(await n.validate(), isTrue);
    final data = env.requestsTo('/checkout/validate').single.data;
    expect(data, isA<FormData>());
    final form = data as FormData;
    expect(form.files.single.key, 'proof');
    expect(form.fields.map((e) => e.key), contains('payment_method_id'));
  });
}
