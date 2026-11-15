import 'package:app/core/l10n/app_localizations.dart';
import 'package:app/core/theme/app_theme.dart';
import 'package:app/core/widgets/widgets.dart';
import 'package:app/features/shell/presentation/app_bottom_nav.dart';
import 'package:app/features/shell/presentation/home_app_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

Widget host(Widget child, {String locale = 'ar', bool reduceMotion = false}) =>
    MaterialApp(
      locale: Locale(locale),
      theme: AppTheme.light,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, w) => MediaQuery(
        data: MediaQuery.of(context).copyWith(disableAnimations: reduceMotion),
        child: w!,
      ),
      home: Scaffold(body: child),
    );

void main() {
  group('AppBottomNav', () {
    testWidgets('five slots with the raised gift in the centre, no wishlist', (
      t,
    ) async {
      final taps = <AppNavItem>[];
      await t.pumpWidget(
        host(
          Align(
            alignment: Alignment.bottomCenter,
            child: AppBottomNav(
              selected: AppNavItem.home,
              cartCount: 3,
              onSelect: taps.add,
            ),
          ),
        ),
      );
      for (final item in AppNavItem.values) {
        expect(find.byKey(ValueKey('nav-${item.name}')), findsOneWidget);
      }
      expect(find.text('الرئيسية'), findsOneWidget);
      expect(find.text('الأقسام'), findsOneWidget);
      expect(find.text('الهدايا'), findsOneWidget);
      expect(find.text('السلة'), findsOneWidget);
      expect(find.text('حسابي'), findsOneWidget);
      expect(find.text('المفضلة'), findsNothing);

      // Visual order in RTL: home is right-most, account left-most, gift
      // centred.
      final home = t.getCenter(find.byKey(const ValueKey('nav-home')));
      final gift = t.getCenter(find.byKey(const ValueKey('nav-gifts')));
      final account = t.getCenter(find.byKey(const ValueKey('nav-account')));
      expect(home.dx, greaterThan(gift.dx));
      expect(gift.dx, greaterThan(account.dx));
      expect(gift.dx, closeTo(400, 1)); // 800-wide test surface

      // Cart count badge + spoken label.
      expect(find.text('3'), findsOneWidget);
      expect(find.bySemanticsLabel('السلة، 3 منتجات'), findsOneWidget);

      await t.tap(find.text('الهدايا'));
      await t.tap(find.text('السلة'));
      expect(taps, [AppNavItem.gifts, AppNavItem.cart]);
    });

    testWidgets('gift finder disabled: four slots, no gift', (t) async {
      await t.pumpWidget(
        host(
          AppBottomNav(
            selected: AppNavItem.account,
            showGift: false,
            onSelect: (_) {},
          ),
        ),
      );
      expect(find.byKey(const ValueKey('nav-gifts')), findsNothing);
      expect(find.text('الهدايا'), findsNothing);
      expect(find.byKey(const ValueKey('nav-account')), findsOneWidget);
    });

    testWidgets('shell: gift circle is raised over the bar and opens the '
        'gift finder; no wishlist slot', (tester) async {
      tester.view.physicalSize = const Size(1000, 2400);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      final env = TestEnv(prefs: {'onboarding_done': true});
      await env.pumpApp(tester);
      expect(find.byType(AppBottomNav), findsOneWidget);
      expect(
        find.descendant(
          of: find.byType(AppBottomNav),
          matching: find.text('المفضلة'),
        ),
        findsNothing,
      );
      final circle = find.byKey(const ValueKey('nav-gift-circle'));
      expect(circle, findsOneWidget);
      final barTop = tester.getTopLeft(find.byType(AppBottomNav)).dy;
      expect(tester.getTopLeft(circle).dy, closeTo(barTop - 20, 1));
      await tester.tap(circle);
      await tester.pumpAndSettle();
      expect(find.byType(AppBottomNav), findsNothing); // gift finder route
    });
  });

  testWidgets('HomeAppBar shows the logo only (no store-name text) with the '
      'store name as its semantics label', (tester) async {
    tester.view.physicalSize = const Size(1000, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final env = TestEnv(prefs: {'onboarding_done': true});
    await env.pumpApp(tester);
    final bar = find.byType(HomeAppBar);
    expect(bar, findsOneWidget);
    expect(
      find.descendant(of: bar, matching: find.byType(AppLogo)),
      findsOneWidget,
    );
    expect(
      find.descendant(of: bar, matching: find.byType(Image)),
      findsOneWidget,
    );
    expect(find.descendant(of: bar, matching: find.byType(Text)), findsNothing);
    expect(find.text('Store egy'), findsNothing);
    expect(find.bySemanticsLabel('Store egy'), findsWidgets);
  });

  group('carousels', () {
    Widget slide(BuildContext _, int i) => ColoredBox(
      color: Colors.pink,
      child: Center(child: Text('slide $i')),
    );

    testWidgets('hero autoplays every 5s, RTL first slide, dots', (t) async {
      await t.pumpWidget(host(HeroCarousel(itemCount: 3, itemBuilder: slide)));
      expect(find.text('slide 0'), findsOneWidget);
      expect(find.byType(CarouselDots), findsOneWidget);
      expect(find.bySemanticsLabel('الشريحة 1 من 3'), findsOneWidget);
      await t.pump(const Duration(seconds: 5));
      await t.pumpAndSettle();
      expect(find.bySemanticsLabel('الشريحة 2 من 3'), findsOneWidget);
      // The next slide is now the one in view.
      expect(t.getCenter(find.text('slide 1')).dx, closeTo(400, 1));
    });

    testWidgets('hero does not autoplay under reduced motion', (t) async {
      await t.pumpWidget(
        host(
          HeroCarousel(itemCount: 3, itemBuilder: slide),
          reduceMotion: true,
        ),
      );
      await t.pump(const Duration(seconds: 12));
      await t.pumpAndSettle();
      expect(find.bySemanticsLabel('الشريحة 1 من 3'), findsOneWidget);
    });

    testWidgets('rail snaps to an item start edge and peeks the next card', (
      t,
    ) async {
      await t.pumpWidget(
        host(
          SizedBox(
            width: 390,
            child: HorizontalRail(
              height: 100,
              itemWidth: 156,
              itemCount: 8,
              showProgress: true,
              itemBuilder: (_, i) => Text('card $i'),
            ),
          ),
          locale: 'en',
        ),
      );
      final list = find.byType(Scrollable).first;
      // About 2.3 cards visible on a 390 screen.
      expect(find.text('card 2'), findsOneWidget);
      expect(find.byType(RailProgress), findsOneWidget);
      await t.drag(list, const Offset(-100, 0));
      await t.pumpAndSettle();
      final pos = t.state<ScrollableState>(list).position.pixels;
      expect(pos % 168, closeTo(0, 0.5)); // 156 card + 12 gap
    });
  });

  testWidgets('trust band shows the four promises', (t) async {
    await t.pumpWidget(host(const TrustStrip.band()));
    for (final s in [
      'شحن سريع',
      'الدفع عند الاستلام',
      'منتجات أصلية 100%',
      'تغليف هدايا مميز',
    ]) {
      expect(find.text(s), findsOneWidget);
    }
  });

  testWidgets('dark theme builds the shared widgets', (t) async {
    await t.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark,
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: const Scaffold(
          body: Column(
            children: [
              StatusChip(label: 'New', tone: StatusTone.violet),
              PriceText(amount: '10.00', currency: 'EGP', oldAmount: '12.00'),
              TrustStrip.row(),
            ],
          ),
        ),
      ),
    );
    expect(find.text('EGP 10'), findsOneWidget);
  });
}
