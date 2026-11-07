import 'package:app/features/store/data/store_models.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

void main() {
  group('StoreInfo (app-config.store)', () {
    test(
      'parses name, country, currency, locales + rtl, timezone, base_url',
      () {
        final s = StoreInfo.fromJson(storeInfoJson());
        expect(s.name, 'Store egy');
        expect(s.country, 'EG');
        expect(s.currencyCode, 'EGP');
        expect(s.currencySymbol, 'EGP');
        expect(s.defaultLocale, 'ar');
        expect(s.localeCodes, ['ar', 'en']);
        expect(s.isRtl('ar'), isTrue);
        expect(s.isRtl('en'), isFalse);
        expect(s.isRtl('fr'), isNull);
        expect(s.timezone, 'Africa/Cairo');
        expect(s.baseUrl, 'http://shop.test');
      },
    );

    test('defaults: symbol falls back to code, locale to ar', () {
      final s = StoreInfo.fromJson({
        'name': 'X',
        'currency': {'code': 'SAR', 'symbol': ''},
      });
      expect(s.currencySymbol, 'SAR');
      expect(s.defaultLocale, 'ar');
      expect(s.locales, isEmpty);
      expect(s.country, isNull);
      expect(s.productUrl('a', 'ar'), isNull);
    });

    test('product share URL = base_url/{locale}/product/{slug}', () {
      final s = StoreInfo.fromJson({
        ...storeInfoJson(),
        'base_url': 'https://x.com/',
      });
      expect(
        s.productUrl('rose-oil', 'en'),
        'https://x.com/en/product/rose-oil',
      );
    });

    test('encode / tryDecode round trip; garbage -> null', () {
      final s = StoreInfo.fromJson(storeInfoJson(locales: ['en']));
      final back = StoreInfo.tryDecode(s.encode())!;
      expect(back.name, s.name);
      expect(back.localeCodes, ['en']);
      expect(back.isRtl('en'), isFalse);
      expect(back.currencySymbol, 'EGP');
      expect(StoreInfo.tryDecode('not json'), isNull);
      expect(StoreInfo.tryDecode(null), isNull);
      expect(StoreInfo.tryDecode('[]'), isNull);
    });
  });

  test('AppConfigInfo reads switches and the store block', () {
    final c = AppConfigInfo.fromJson({
      'min_version': '1.2.0',
      'maintenance': true,
      'maintenance_message': 'soon',
      'update_required': true,
      'store_urls': {'ios': 'https://apps.apple.com/x', 'android': null},
      'store': storeInfoJson(name: 'Shop'),
    });
    expect(c.minVersion, '1.2.0');
    expect(c.maintenance, isTrue);
    expect(c.updateRequired, isTrue);
    expect(c.iosUrl, 'https://apps.apple.com/x');
    expect(c.androidUrl, isNull);
    expect(c.store?.name, 'Shop');
    expect(AppConfigInfo.fromJson(const {}).store, isNull);
  });

  test('StoreProfile (GET /store) keeps contact, features and round-trips', () {
    final p = StoreProfile.fromJson(storeJson());
    expect(p.whatsapp, '201001234567');
    expect(p.features['gift_finder'], isTrue);
    final back = StoreProfile.tryDecode(p.encode())!;
    expect(back.whatsapp, '201001234567');
    expect(back.localeCodes, ['ar', 'en']);
  });
}
