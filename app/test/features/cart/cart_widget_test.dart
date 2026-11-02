import 'package:app/features/auth/presentation/login_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';
import 'cart_test.dart' show CartBackend;

void bigScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('cart tab: badge, lines, server totals, stepper PUT, guest '
      'checkout goes to login', (tester) async {
    bigScreen(tester);
    final b = CartBackend();
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: (o) {
        if (o.path == '/home') return envelope({});
        return b.call(o);
      },
    );
    await env.pumpApp(tester);
    // Badge on the tab shows the unit count from the server cart.
    expect(
      find.descendant(of: find.byType(Badge), matching: find.text('2')),
      findsWidgets,
    );

    await tester.tap(find.text('السلة').last);
    await tester.pumpAndSettle();
    expect(find.text('Serum'), findsOneWidget);
    expect(
      find.textContaining('200.00'),
      findsWidgets,
    ); // server subtotal/total
    expect(find.text('يُحسب عند إتمام الطلب'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.add_rounded));
    await tester.pumpAndSettle();
    final put = env.adapter.requests.where((r) => r.method == 'PUT').single;
    expect(put.path, '/cart/items/1');
    expect(put.data, {'quantity': 3});
    expect(find.textContaining('300.00'), findsWidgets);

    await tester.tap(find.text('إتمام الطلب'));
    await tester.pumpAndSettle();
    expect(find.byType(LoginPage), findsOneWidget);
  });
}
