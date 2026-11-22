import 'package:app/core/l10n/app_localizations.dart';
import 'package:app/core/theme/app_theme.dart';
import 'package:app/core/widgets/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phone-width (390) product rail: 156 cards + 12 gap = 168 per snap step.
const _step = 168.0;

Widget _rail(String locale, {int count = 12}) => MaterialApp(
  theme: AppTheme.light,
  locale: Locale(locale),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: AppLocalizations.supportedLocales,
  home: Scaffold(
    body: Center(
      child: SizedBox(
        width: 390,
        child: HorizontalRail(
          height: 120,
          itemWidth: 156,
          itemCount: count,
          showProgress: true,
          itemBuilder: (_, i) => Text('card $i'),
        ),
      ),
    ),
  ),
);

void main() {
  for (final locale in ['ar', 'en']) {
    // RTL: the list starts at the right, so "forward" is a drag to the right.
    final forward = locale == 'ar' ? 1.0 : -1.0;

    testWidgets('$locale: every fling rests on a card edge, moves at most '
        'one screenful and never jumps to the end', (t) async {
      await t.pumpWidget(_rail(locale, count: 20));
      final list = find.byType(Scrollable).first;
      final pos = t.state<ScrollableState>(list).position;
      final visible = (390 / _step).floorToDouble(); // 2 whole cards

      for (final (dx, speed) in [
        (60.0, 400.0), // light swipe
        (120.0, 1500.0), // normal swipe
        (200.0, 5000.0), // hard flick
        (300.0, 20000.0), // extreme flick
      ]) {
        final before = pos.pixels;
        await t.fling(list, Offset(forward * dx, 0), speed);
        await t.pumpAndSettle();
        final moved = (pos.pixels - before) / _step;
        // Momentum only: cards travelled after the finger lifted.
        final momentum = moved - dx / _step;
        final atEnd = (pos.pixels - pos.maxScrollExtent).abs() < 0.5;
        expect(
          atEnd ||
              (pos.pixels % _step).abs() < 0.5 ||
              (_step - pos.pixels % _step).abs() < 0.5,
          isTrue,
          reason: 'fling $dx@$speed rested between cards at ${pos.pixels}',
        );
        expect(moved, greaterThanOrEqualTo(1), reason: 'fling $dx@$speed');
        // At most one screenful after release (+0.5: rounding to an edge).
        expect(
          momentum,
          lessThanOrEqualTo(visible + 0.5),
          reason: 'fling $dx@$speed moved $moved cards from $before',
        );
      }
      expect(pos.pixels, lessThan(pos.maxScrollExtent));
    });

    testWidgets('$locale: the last card is reachable and a fling back '
        'from the end rests on a card edge', (t) async {
      await t.pumpWidget(_rail(locale));
      final list = find.byType(Scrollable).first;
      final pos = t.state<ScrollableState>(list).position;
      for (var i = 0; i < 8; i++) {
        await t.fling(list, Offset(forward * 200, 0), 3000);
        await t.pumpAndSettle();
      }
      expect(pos.pixels, closeTo(pos.maxScrollExtent, 0.5));
      expect(find.text('card 11'), findsOneWidget);

      await t.fling(list, Offset(-forward * 120, 0), 1500);
      await t.pumpAndSettle();
      expect(pos.pixels % _step, closeTo(0, 0.5));
    });
  }
}
