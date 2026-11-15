import 'package:app/core/l10n/app_localizations.dart';
import 'package:app/core/utils/context_ext.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final ar = lookupAppLocalizations(const Locale('ar'));
  final en = lookupAppLocalizations(const Locale('en'));

  test('Arabic: Western digits, separators, ج.م after the amount', () {
    expect(ar.money('1250.00', 'EGP'), '1,250 ج.م');
    expect(ar.money('1250.00', 'ج.م'), '1,250 ج.م');
    expect(ar.money('99.50', 'EGP'), '99.50 ج.م');
    expect(ar.money('1,000', 'EGP'), '1,000 ج.م');
  });

  test('English: EGP before the amount', () {
    expect(en.money('1250.00', 'EGP'), 'EGP 1,250');
    expect(en.money('1250.00', 'ج.م'), 'EGP 1,250');
    expect(en.money('0.00', 'EGP'), 'EGP 0');
    expect(en.money('-50.00', 'EGP'), 'EGP -50');
  });

  test(
    'other currencies keep their symbol; unparsable amounts stay as sent',
    () {
      expect(en.money('10', 'USD'), 'USD 10');
      expect(en.money('n/a', 'EGP'), en.currencyAmount('n/a', 'EGP'));
    },
  );
}
