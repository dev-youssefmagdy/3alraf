import 'package:app/core/l10n/app_localizations.dart';
import 'package:app/core/theme/app_theme.dart';
import 'package:app/core/widgets/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/catalog_fixtures.dart';
import '../../support/harness.dart';
import 'catalog_flow_test.dart' show api, bigScreen;

Widget _app(Widget child, {double textScale = 1}) => MaterialApp(
  theme: AppTheme.light,
  locale: const Locale('ar'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: AppLocalizations.supportedLocales,
  home: Builder(
    builder: (context) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(textScale)),
      child: Scaffold(body: child),
    ),
  ),
);

void main() {
  group('round 2a shop widgets', () {
    testWidgets('product card with flash + CTA fits its rail cell at text '
        'scale 1.3 (no overflow)', (tester) async {
      await tester.pumpWidget(
        _app(
          Builder(
            builder: (context) => Center(
              child: SizedBox(
                width: 156,
                height: ProductCard.extentFor(context, 156, withFlash: true),
                child: ProductCard(
                  title: 'عطر زهور الربيع للنساء أو دو بارفان 50 مل',
                  price: '1250.00',
                  oldPrice: '1690.00',
                  discountPercent: '26',
                  currency: 'ج.م',
                  rating: 4.6,
                  reviewCount: 128,
                  flashEndsAt: DateTime.now().add(const Duration(hours: 2)),
                  onAddToCart: () {},
                ),
              ),
            ),
          ),
          textScale: 1.3,
        ),
      );
      expect(tester.takeException(), isNull);
      expect(find.text('أضف إلى السلة'), findsOneWidget);
    });

    testWidgets('choice card exposes selection to accessibility and taps', (
      tester,
    ) async {
      var taps = 0;
      await tester.pumpWidget(
        _app(
          Column(
            children: [
              ChoiceCard(
                title: 'بوسطة',
                subtitle: 'التوصيل خلال 2 يوم',
                selected: true,
                onTap: () => taps++,
              ),
              const ChoiceCard(
                title: 'شحن مجاني',
                selected: false,
                onTap: null,
                enabled: false,
              ),
            ],
          ),
        ),
      );
      await tester.tap(find.text('بوسطة'));
      expect(taps, 1);
      expect(
        tester.getSemantics(find.byType(ChoiceCard).first),
        isSemantics(
          isSelected: true,
          isInMutuallyExclusiveGroup: true,
          isEnabled: true,
        ),
      );
    });

    testWidgets('flash countdown units: hours / minutes / seconds boxes '
        'with one semantics label', (tester) async {
      final now = DateTime(2026, 11, 14, 10);
      await tester.pumpWidget(
        _app(
          CountdownChip(
            endsAt: now.add(const Duration(hours: 2, minutes: 14, seconds: 35)),
            now: () => now,
            units: true,
          ),
        ),
      );
      expect(find.text('02'), findsOneWidget);
      expect(find.text('14'), findsOneWidget);
      expect(find.text('35'), findsOneWidget);
      expect(find.text('ساعة'), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp('02:14:35')), findsOneWidget);
    });
  });

  testWidgets('home card CTA on a variable product opens the variant sheet; '
      'add posts the chosen variant', (tester) async {
    bigScreen(tester);
    final env = TestEnv(prefs: {'onboarding_done': true}, extra: api());
    await env.pumpApp(tester);
    // First card of the recommended rail is "serum" (variable product).
    await tester.tap(find.widgetWithText(FilledButton, 'أضف إلى السلة').first);
    await tester.pumpAndSettle();
    expect(find.text('اختر الخيارات'), findsWidgets); // sheet title
    expect(env.requestsTo('/cart/items'), isEmpty);
    await tester.tap(
      find.descendant(
        of: find.byType(AppSheet),
        matching: find.widgetWithText(FilledButton, 'أضف إلى السلة'),
      ),
    );
    await tester.pumpAndSettle();
    final post = env.requestsTo('/cart/items').single;
    expect(post.data, {'product_id': 5, 'variant_id': 100, 'quantity': 1});
  });

  testWidgets('card CTA on a sold-out product opens notify-me', (tester) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: api(product: simpleProductJson(inStock: false)),
    );
    await env.pumpApp(tester);
    await tester.tap(find.widgetWithText(FilledButton, 'أضف إلى السلة').first);
    await tester.pumpAndSettle();
    expect(find.text('أعلمني عند التوفر'), findsWidgets);
    expect(find.widgetWithText(FilledButton, 'إرسال الطلب'), findsOneWidget);
    expect(env.requestsTo('/cart/items'), isEmpty);
  });

  testWidgets('card CTA on a simple in-stock product adds it directly', (
    tester,
  ) async {
    bigScreen(tester);
    final env = TestEnv(
      prefs: {'onboarding_done': true},
      extra: api(product: simpleProductJson()),
    );
    await env.pumpApp(tester);
    await tester.tap(find.widgetWithText(FilledButton, 'أضف إلى السلة').first);
    await tester.pumpAndSettle();
    final post = env.requestsTo('/cart/items').single;
    expect(post.data, {'product_id': 6, 'variant_id': 0, 'quantity': 1});
  });
}
