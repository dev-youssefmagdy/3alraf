/// Fake data + shots for the redesign round 2b screens (account, auth,
/// startup, content, affiliate). Used by `shop_screens_test.dart`.
library;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/harness.dart';
import 'shop_screens_test.dart';

class AccountData {
  AccountData(this.ar);
  final bool ar;

  String t(String a, String e) => ar ? a : e;

  Map<String, Object?> get storeProfile => {
    ...storeJson(locale: ar ? 'ar' : 'en'),
    'contact': {
      'whatsapp': '201001234567',
      'phone': '0100 123 4567',
      'email': 'hello@3alraf.com',
    },
    'social': {
      'facebook': 'https://facebook.com/3alraf',
      'instagram': 'https://instagram.com/3alraf',
      'tiktok': 'https://tiktok.com/@3alraf',
    },
    'features': {'gift_finder': true, 'ambassadors': true},
  };

  Map<String, Object?> get customer => {
    'id': 1,
    'name': t('سارة أحمد', 'Sara Ahmed'),
    'email': 'sara@example.com',
    'phone': '10 1234 5678',
    'phone_country_code': '+20',
    'email_verified': false,
  };

  List<Map<String, Object?>> get pages => [
    {'slug': 'privacy', 'title': t('سياسة الخصوصية', 'Privacy policy')},
    {'slug': 'terms', 'title': t('الشروط والأحكام', 'Terms & conditions')},
    {
      'slug': 'returns',
      'title': t('سياسة الاستبدال والاسترجاع', 'Returns & exchanges'),
    },
  ];

  Map<String, Object?> get returnsPage => {
    'slug': 'returns',
    'title': t('سياسة الاستبدال والاسترجاع', 'Returns & exchanges'),
    'content': t(
      '<p>يمكنك طلب الإرجاع خلال 14 يومًا من الاستلام للمنتجات غير المفتوحة وفي حالتها الأصلية.</p>'
          '<h2>كيف أطلب الإرجاع؟</h2>'
          '<p>من صفحة الطلب اضغط "طلب إرجاع"، اختر المنتجات وسبب الإرجاع، وسنتواصل معك لتحديد موعد الاستلام.</p>',
      '<p>You can request a return within 14 days of delivery for unopened products in their original condition.</p>'
          '<h2>How do I request a return?</h2>'
          '<p>On the order page tap "Request a return", choose the items and the reason, and we will contact you to arrange the pickup.</p>',
    ),
  };

  List<Map<String, Object?>> get posts => [
    {
      'slug': 'mothers-day',
      'title': t(
        'أفكار هدايا عيد الأم بميزانية 1,000 ج.م',
        "Mother's Day gift ideas under EGP 1,000",
      ),
      'excerpt': t(
        'نقدّم لك اختيارات مجرّبة تناسب كل الأذواق مع نصائح التغليف والإهداء.',
        'Tried-and-tested picks for every taste, with wrapping tips.',
      ),
      'published_at': '2026-11-10T10:00:00+02:00',
      'url': 'https://3alraf.com/blog/mothers-day',
      'content': t(
        '<p>عيد الأم فرصة نقول فيها شكرًا بطريقة مميزة. جمعنا لك أفكارًا مجرّبة تناسب كل الأذواق والميزانيات.</p>'
            '<h2>1. عطر يدوم طويلًا</h2>'
            '<p>اختاري عطرًا زهريًا خفيفًا للاستخدام اليومي، وأضيفي تغليف الهدايا الفاخر.</p>'
            '<blockquote>"أجمل هدية هي اللي بتفكّر فيها قبل ما تشتريها"</blockquote>'
            '<h2>2. باقة عناية متكاملة</h2>'
            '<p>المرطب وكريم اليدين والعطر معًا في باقة واحدة بسعر أقل.</p>',
        "<p>Mother's Day is a chance to say thank you in a special way. Here are tried-and-tested ideas for every taste and budget.</p>"
            '<h2>1. A long-lasting perfume</h2>'
            '<p>Pick a light floral scent for every day and add the luxury gift box.</p>'
            '<blockquote>"The best gift is the one you think about before you buy it"</blockquote>'
            '<h2>2. A complete care bundle</h2>'
            '<p>Moisturiser, hand cream and perfume together in one bundle for less.</p>',
      ),
    },
    {
      'slug': 'winter-skin',
      'title': t(
        'روتين العناية بالبشرة في الشتاء',
        'Your winter skin-care routine',
      ),
      'excerpt': t(
        'خطوات بسيطة تحافظ على ترطيب بشرتك طوال الموسم.',
        'Simple steps that keep your skin hydrated all season.',
      ),
      'published_at': '2026-11-02T10:00:00+02:00',
    },
    {
      'slug': 'perfume-guide',
      'title': t('كيف تختارين عطرك المناسب؟', 'How to choose your perfume'),
      'excerpt': t(
        'دليل سريع لعائلات العطور وأوقات استخدامها.',
        'A quick guide to fragrance families and when to wear them.',
      ),
      'published_at': '2026-10-20T10:00:00+02:00',
    },
  ];

  Map<String, Object?> get launch => {
    'code': 'abcd1234',
    'name': t('إطلاق محدود: عطر زهور الربيع', 'Limited launch: Spring Blossom'),
    'mode': 'limited_stock',
    'is_open': true,
    'description': t(
      'كمية محدودة من الإصدار الجديد. سجّلي اهتمامك ليصلك إشعار قبل الجميع.',
      'A small batch of the new edition. Register your interest to hear first.',
    ),
    'product': {
      'slug': 'perfume',
      'name': t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume 50 ml'),
      'image': img('womens-perfume-50ml-0'),
    },
  };

  Map<String, Object?> get affiliate => {
    'id': 1,
    'name': t('سارة', 'Sara'),
    'email': 'marketer@example.com',
    'status': 'approved',
    'ref': 'sara-x7k2',
  };

  Map<String, Object?> get dashboard => {
    'affiliate': affiliate,
    'stats': {
      'clicks': 2340,
      'orders': 82,
      'conversion_percent': '3.5',
      'lifetime_sales': '48250.00',
      'tier': {
        'name': t('الذهبي', 'Gold'),
        'percent': '10',
        'min_sales': '25000.00',
      },
      'next_tier': {
        'name': t('البلاتيني', 'Platinum'),
        'percent': '12',
        'min_sales': '60000.00',
      },
      'commissions': {
        'pending': '820.00',
        'approved': '1150.00',
        'paid': '4300.00',
        'available': '1250.00',
      },
      'bonus': {
        'enabled': true,
        'threshold': '10000.00',
        'amount': '200.00',
        'toward_next': '8250.00',
        'remaining': '1750.00',
        'percent': 82.5,
        'earned_count': 4,
        'earned_total': '800.00',
      },
    },
    'payouts': <Object>[],
    'recent_commissions': [
      {
        'order': '#10482',
        'amount': '125.00',
        'percent': '10',
        'status': 'pending',
      },
      {
        'order': '#10455',
        'amount': '99.00',
        'percent': '10',
        'status': 'approved',
      },
      {
        'order': '#10391',
        'amount': '245.00',
        'percent': '10',
        'status': 'paid',
      },
    ],
  };

  List<Map<String, Object?>> get affiliateProducts => [
    {
      'id': 1,
      'slug': 'perfume',
      'name': t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume 50 ml'),
      'price': '1250.00',
      'image': img('womens-perfume-50ml-0'),
      'link': 'https://3alraf.com/l/sara-x7k2',
    },
    {
      'id': 2,
      'slug': 'moisturizer',
      'name': t('مرطب الوجه بفيتامين C', 'Vitamin C face moisturizer'),
      'price': '1490.00',
      'image': img('face-moisturizer-0'),
      'link': 'https://3alraf.com/l/sara-m2q9',
    },
  ];

  List<Map<String, Object?>> get payouts => [
    {
      'id': 3,
      'amount': '1000.00',
      'status': 'requested',
      'note': t('فودافون كاش', 'Vodafone Cash'),
      'requested_at': '2026-11-01T10:00:00+02:00',
    },
    {
      'id': 2,
      'amount': '2300.00',
      'status': 'paid',
      'note': t('فودافون كاش', 'Vodafone Cash'),
      'requested_at': '2026-10-01T10:00:00+02:00',
    },
    {
      'id': 1,
      'amount': '2000.00',
      'status': 'paid',
      'note': t('تحويل بنكي', 'Bank transfer'),
      'requested_at': '2026-09-01T10:00:00+02:00',
    },
  ];

  Map<String, Object?> get ambassador => {
    'enrolled': true,
    'ambassador': {'status': 'active', 'disclosure': 'ad'},
    'channels': ['instagram', 'tiktok', 'facebook'],
    'ideas': [
      {
        'title': t('فيديو فتح العلبة', 'Unboxing video'),
        'body': t(
          'صوّري فتح علبة الهدية مع التغليف الفاخر في 15 ثانية.',
          'Film a 15-second unboxing of the luxury gift box.',
        ),
      },
    ],
    'links': [
      {
        'id': 1,
        'channel': 'instagram',
        'product_id': 1,
        'clicks': 312,
        'url': 'https://3alraf.com/l/sara-ig1',
      },
      {
        'id': 2,
        'channel': 'tiktok',
        'product_id': 2,
        'clicks': 128,
        'url': 'https://3alraf.com/l/sara-tt2',
      },
    ],
    'report': {
      'valid_orders': 14,
      'pending_orders': 3,
      'excluded_orders': 1,
      'window_days': 30,
    },
  };

  Set<String> get images => {
    img('womens-perfume-50ml-0'),
    img('face-moisturizer-0'),
  };
}

/// Fake backend for the round 2b endpoints (falls through to null).
class AccountBackend {
  AccountBackend(this.d);
  final AccountData d;

  ResponseBody? call(RequestOptions o) {
    final p = o.path;
    switch ((o.method, p)) {
      case ('GET', '/auth/me'):
        return envelope(d.customer);
      case ('GET', '/pages'):
        return envelope(d.pages);
      case ('GET', '/checkout/cities'):
        return envelope([
          for (final (i, a, e) in [
            (1, 'القاهرة', 'Cairo'),
            (2, 'الجيزة', 'Giza'),
            (3, 'الإسكندرية', 'Alexandria'),
            (4, 'المنصورة', 'Mansoura'),
            (5, 'طنطا', 'Tanta'),
            (6, 'أسيوط', 'Assiut'),
          ])
            {'id': i, 'name': d.t(a, e)},
        ]);
      case ('GET', '/blog'):
        return envelopeWithMeta(d.posts, {
          'pagination': {
            'current_page': 1,
            'last_page': 1,
            'per_page': 9,
            'total': 3,
          },
        });
      case ('GET', '/launch/abcd1234'):
        return envelope(d.launch);
      case ('GET', '/affiliate/me'):
        return envelope(d.affiliate);
      case ('GET', '/affiliate/dashboard'):
        return envelope(d.dashboard);
      case ('GET', '/affiliate/products'):
        return envelope(d.affiliateProducts);
      case ('GET', '/affiliate/payouts'):
        return envelope(d.payouts);
      case ('GET', '/affiliate/ambassador'):
        return envelope(d.ambassador);
    }
    if (p.startsWith('/blog/')) return envelope(d.posts.first);
    if (p.startsWith('/pages/')) return envelope(d.returnsPage);
    return null;
  }
}

Future<void> _tapIcon(WidgetTester t, IconData icon) async {
  await t.tap(find.byIcon(icon).first);
  await settleShort(t);
}

Future<void> _scroll(WidgetTester t, double dy) async {
  await t.drag(find.byType(Scrollable).last, Offset(0, -dy));
  await settleShort(t);
}

/// Round 2b shots (numbering continues after the shopping screens).
final accountShots = <Shot>[
  Shot(
    '36-splash',
    (t, r) async {},
    splashMinDuration: const Duration(minutes: 5),
    after: (t) async {
      await t.pump(const Duration(minutes: 6));
      await settleShort(t, 40);
    },
  ),
  Shot('37-language', (t, r) async => go(t, '/language')),
  Shot('38-onboarding', (t, r) async => go(t, '/onboarding')),
  Shot('39-login', (t, r) async => go(t, '/login', push: true)),
  Shot('40-register', (t, r) async => go(t, '/register', push: true)),
  Shot(
    '41-forgot-password',
    (t, r) async => go(t, '/forgot-password', push: true),
  ),
  Shot('42-verify-email', (t, r) async {
    await go(t, '/verify-email', push: true);
    await t.enterText(find.byType(TextFormField).first, '4827');
    await settleShort(t, 4);
  }, signedIn: true),
  Shot(
    '43-reset-password',
    (t, r) async =>
        go(t, '/reset-password?email=sara%40example.com', push: true),
  ),
  Shot('44-account-guest', (t, r) async => go(t, '/account')),
  Shot('45-account', (t, r) async => go(t, '/account'), signedIn: true),
  Shot(
    '46-profile',
    (t, r) async => go(t, '/account/profile', push: true),
    signedIn: true,
  ),
  Shot(
    '47-change-password',
    (t, r) async => go(t, '/account/password', push: true),
    signedIn: true,
  ),
  Shot(
    '48-delete-account',
    (t, r) async => go(t, '/account/delete', push: true),
    signedIn: true,
  ),
  Shot(
    '49-addresses',
    (t, r) async => go(t, '/account/addresses', push: true),
    signedIn: true,
  ),
  Shot('50-address-edit', (t, r) async {
    await go(t, '/account/addresses/edit', push: true);
    await t.enterText(find.byType(TextFormField).at(0), 'البيت');
    await t.tap(find.byType(FilledButton).last);
    await settleShort(t);
  }, signedIn: true),
  Shot('51-city-sheet', (t, r) async {
    await go(t, '/account/addresses/edit', push: true);
    await _tapIcon(t, Icons.keyboard_arrow_down_rounded);
  }, signedIn: true),
  Shot('52-theme-sheet', (t, r) async {
    await go(t, '/account');
    await _tapIcon(t, Icons.auto_awesome_outlined);
  }),
  Shot('53-track-order', (t, r) async {
    await go(t, '/account');
    await _tapIcon(t, Icons.local_shipping_outlined);
  }),
  Shot('54-about', (t, r) async => go(t, '/about', push: true)),
  Shot('55-blog', (t, r) async => go(t, '/blog', push: true)),
  Shot('56-blog-post', (t, r) async => go(t, '/blog/mothers-day', push: true)),
  Shot('57-contact', (t, r) async => go(t, '/contact', push: true)),
  Shot('58-content-page', (t, r) async => go(t, '/page/returns', push: true)),
  Shot('59-launch', (t, r) async => go(t, '/launch/abcd1234', push: true)),
  Shot('60-maintenance', (t, r) async => go(t, '/maintenance')),
  Shot(
    '61-update-required',
    (t, r) async => go(t, '/update-required'),
    appConfig: {
      'store_urls': {
        'ios': 'https://apps.apple.com/app/id1',
        'android': 'https://play.google.com/store/apps/details?id=x',
      },
    },
  ),
  Shot('62-affiliate-login', (t, r) async => go(t, '/affiliate', push: true)),
  Shot(
    '63-affiliate-register',
    (t, r) async => go(t, '/affiliate/register', push: true),
  ),
  Shot(
    '64-affiliate-dashboard',
    (t, r) async => go(t, '/affiliate', push: true),
    affiliate: true,
  ),
  Shot('65-affiliate-dashboard-more', (t, r) async {
    await go(t, '/affiliate', push: true);
    await _scroll(t, 640);
  }, affiliate: true),
  Shot('66-affiliate-products-qr', (t, r) async {
    await go(t, '/affiliate/products', push: true);
    await _tapIcon(t, Icons.qr_code_2_rounded);
  }, affiliate: true),
  Shot('67-affiliate-payouts', (t, r) async {
    await go(t, '/affiliate', push: true);
    await go(t, '/affiliate/payouts', push: true);
  }, affiliate: true),
  Shot(
    '68-ambassador',
    (t, r) async => go(t, '/affiliate/ambassador', push: true),
    affiliate: true,
  ),
  // Large text (accessibility): nothing may clip at 1.3.
  Shot(
    '69-account-text130',
    (t, r) async => go(t, '/account'),
    signedIn: true,
    textScale: 1.3,
  ),
  Shot(
    '70-login-text130',
    (t, r) async => go(t, '/login', push: true),
    textScale: 1.3,
  ),
  Shot(
    '71-affiliate-dashboard-text130',
    (t, r) async => go(t, '/affiliate', push: true),
    affiliate: true,
    textScale: 1.3,
  ),
  Shot(
    '72-verify-email-text130',
    (t, r) async {
      await go(t, '/verify-email', push: true);
    },
    signedIn: true,
    textScale: 1.3,
  ),
  Shot(
    '73-addresses-text130',
    (t, r) async => go(t, '/account/addresses', push: true),
    signedIn: true,
    textScale: 1.3,
  ),
];
