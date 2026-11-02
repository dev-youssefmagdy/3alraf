import 'package:app/core/l10n/app_localizations.dart';
import 'package:app/core/theme/app_theme.dart';
import 'package:app/core/widgets/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

Widget host(Widget child, {String locale = 'ar'}) => MaterialApp(
  locale: Locale(locale),
  theme: AppTheme.light,
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: child),
);

void main() {
  testWidgets('Arabic locale lays out right-to-left', (tester) async {
    late TextDirection dir;
    await tester.pumpWidget(
      host(
        Builder(
          builder: (c) {
            dir = Directionality.of(c);
            return const SizedBox();
          },
        ),
      ),
    );
    expect(dir, TextDirection.rtl);
  });

  testWidgets('English locale lays out left-to-right', (tester) async {
    late TextDirection dir;
    await tester.pumpWidget(
      host(
        Builder(
          builder: (c) {
            dir = Directionality.of(c);
            return const SizedBox();
          },
        ),
        locale: 'en',
      ),
    );
    expect(dir, TextDirection.ltr);
  });

  testWidgets('PriceText renders amount verbatim with old price', (t) async {
    await t.pumpWidget(
      host(
        const PriceText(amount: '250.00', currency: 'EGP', oldAmount: '300.00'),
        locale: 'en',
      ),
    );
    expect(find.text('250.00 EGP'), findsOneWidget);
    expect(find.text('300.00 EGP'), findsOneWidget);
  });

  testWidgets('ProductCard shows title, discount and fires onTap', (t) async {
    var taps = 0;
    await t.pumpWidget(
      host(
        SizedBox(
          width: 200,
          child: ProductCard(
            title: 'Rose serum',
            price: '99.00',
            currency: 'EGP',
            discountPercent: '20',
            rating: 4.5,
            reviewCount: 12,
            onTap: () => taps++,
          ),
        ),
        locale: 'en',
      ),
    );
    expect(find.text('Rose serum'), findsOneWidget);
    expect(find.text('20% off'), findsOneWidget);
    await t.tap(find.text('Rose serum'));
    expect(taps, 1);
  });

  testWidgets('QuantityStepper respects bounds', (t) async {
    var v = 1;
    await t.pumpWidget(
      host(
        StatefulBuilder(
          builder: (_, set) => QuantityStepper(
            value: v,
            max: 2,
            onChanged: (n) => set(() => v = n),
          ),
        ),
        locale: 'en',
      ),
    );
    await t.tap(find.byIcon(Icons.add_rounded));
    await t.pump();
    expect(v, 2);
    expect(
      t
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.add_rounded),
          )
          .onPressed,
      isNull,
    );
  });

  testWidgets('ErrorState shows retry and calls back', (t) async {
    var retried = false;
    await t.pumpWidget(
      host(ErrorState(onRetry: () => retried = true), locale: 'en'),
    );
    await t.tap(find.text('Retry'));
    expect(retried, isTrue);
  });

  testWidgets('CountdownChip counts down and fires onFinished', (t) async {
    var now = DateTime(2026, 1, 1, 10);
    var done = false;
    await t.pumpWidget(
      host(
        CountdownChip(
          endsAt: DateTime(2026, 1, 1, 10, 0, 2),
          now: () => now,
          onFinished: () => done = true,
        ),
      ),
    );
    expect(find.text('00:00:02'), findsOneWidget);
    now = now.add(const Duration(seconds: 2));
    await t.pump(const Duration(seconds: 1));
    expect(find.text('00:00:00'), findsOneWidget);
    expect(done, isTrue);
  });

  testWidgets('EmptyState renders localised default title', (t) async {
    await t.pumpWidget(host(const EmptyState()));
    expect(find.text('لا يوجد شيء هنا بعد'), findsOneWidget);
  });
}
