import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';

void main() {
  testWidgets('boots RTL, splash then bottom-nav shell (single tenant)', (
    tester,
  ) async {
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: (o) => o.path == '/cart' ? envelope({'items': <Object>[]}) : null,
    );
    await env.init();
    await tester.pumpWidget(envApp(env));
    expect(
      Directionality.of(tester.element(find.byType(Scaffold).first)),
      TextDirection.rtl,
    );
    await tester.pumpAndSettle();
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.text('الرئيسية'), findsWidgets);
    await tester.tap(find.text('السلة'));
    await tester.pumpAndSettle();
    expect(find.text('سلتك فارغة'), findsOneWidget);
  });
}
