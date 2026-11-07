import 'package:app/features/checkout/presentation/checkout_page.dart';
import 'package:app/features/orders/presentation/order_confirmation_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';
import 'checkout_test.dart' show CheckoutBackend;

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('signed in: cart -> checkout steps -> place -> confirmation', (
    tester,
  ) async {
    bigScreen(tester);
    final b = CheckoutBackend()..nextAction = 'none';
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: (o) => o.path == '/home' ? envelope({}) : b.call(o),
    );
    await env.init();
    await env.secrets.write('auth_token', '12|secret');
    await env.pumpApp(tester);

    await tester.tap(find.text('السلة').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('إتمام الطلب'));
    await tester.pumpAndSettle();
    expect(find.byType(CheckoutPage), findsOneWidget);

    // Address step: default address preselected -> continue.
    await tester.tap(find.text('متابعة'));
    await tester.pumpAndSettle();
    // Shipping (single option preselected, price from server).
    expect(find.text('Fast'), findsOneWidget);
    expect(find.textContaining('40.00'), findsWidgets);
    await tester.tap(find.text('متابعة'));
    await tester.pumpAndSettle();
    // Payment: choose COD.
    await tester.tap(find.text('Method 1'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('متابعة'));
    await tester.pumpAndSettle();
    // Review shows the validated server total.
    expect(env.requestsTo('/checkout/validate'), isNotEmpty);
    expect(find.textContaining('240.00'), findsWidgets);

    await tester.tap(find.text('تأكيد الطلب'));
    await tester.pumpAndSettle();
    expect(env.requestsTo('/checkout').single.method, 'POST');
    expect(find.byType(OrderConfirmationPage), findsOneWidget);
    expect(find.textContaining('ORD-000001'), findsWidgets);
  });
}
