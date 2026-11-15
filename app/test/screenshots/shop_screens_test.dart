/// QA screenshots of the redesigned screens: shopping (round 2a, 01-35)
/// and account / auth / startup / content / affiliate (round 2b, 36+, in
/// `account_shots.dart`).
///
/// Skipped in a normal `flutter test` run. Generate the PNGs with:
///
///     flutter test test/screenshots --update-goldens
///
/// Every screen is rendered through the real app (router, providers,
/// theme) against a fake backend at 390x844 (2x) in Arabic and English with
/// the bundled Cairo / Baloo Bhaijaan 2 fonts and the seed stock photos.
/// Output: `build/screenshots/<locale>/<nn>-<screen>.png`.
@Tags(['screenshots'])
library;

import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:app/app.dart';
import 'package:app/core/widgets/widgets.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../support/catalog_fixtures.dart';
import '../support/commerce_fixtures.dart';
import '../support/harness.dart';
import 'account_shots.dart';

const _stock = '../backend/database/seeders/assets/stock';
const _out = 'build/screenshots';
const _size = Size(390, 844);
const _dpr = 2.0;

String img(String name) => 'stock://products/$name.webp';
String catImg(String name) => 'stock://categories/$name.webp';

Future<void> _loadFonts() async {
  Future<void> load(String family, List<String> paths) async {
    final loader = FontLoader(family);
    for (final p in paths) {
      final bytes = File(p).readAsBytesSync();
      loader.addFont(Future.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
  }

  final flutterRoot =
      Platform.environment['FLUTTER_ROOT'] ??
      File(Platform.resolvedExecutable).parent.parent.parent.parent.path;
  await load('Cairo', ['assets/fonts/Cairo.ttf']);
  await load('Baloo Bhaijaan 2', ['assets/fonts/BalooBhaijaan2.ttf']);
  await load('MaterialIcons', [
    '$flutterRoot/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
  ]);
}

// -- fake data -----------------------------------------------------------

Map<String, Object?> product(
  int id,
  String slug,
  String name, {
  required String image,
  String price = '1250.00',
  String? old,
  int? percent,
  bool flash = false,
  double? rating = 4.6,
}) => {
  ...card(id, slug, price: price, old: old, percent: percent),
  'name': name,
  'image': img(image),
  'flash': flash,
  'flash_ends_at': flash
      ? DateTime.now()
            .add(const Duration(hours: 2, minutes: 14))
            .toIso8601String()
      : null,
  'rating': rating,
  'reviews_count': rating == null ? 0 : 128,
};

class ShopData {
  ShopData(this.ar);
  final bool ar;

  String t(String a, String e) => ar ? a : e;

  List<Map<String, Object?>> get products => [
    product(
      1,
      'perfume',
      t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume 50 ml'),
      image: 'womens-perfume-50ml-0',
      price: '1250.00',
      old: '1690.00',
      percent: 26,
      flash: true,
    ),
    product(
      2,
      'hand-cream',
      t('كريم اليدين بزبدة الشيا 75 مل', 'Shea butter hand cream 75 ml'),
      image: 'hand-cream-0',
      price: '390.00',
    ),
    product(
      3,
      'dress',
      t('فستان سهرة ستان طويل', 'Long satin evening dress'),
      image: 'evening-dress-0',
      price: '2450.00',
    ),
    product(
      4,
      'summer-dress',
      t('فستان صيفي قطن بأكمام قصيرة', 'Cotton summer dress'),
      image: 'summer-dress-0',
      price: '1490.00',
    ),
    product(
      5,
      'speaker',
      t('سماعة بلوتوث محمولة', 'Portable Bluetooth speaker'),
      image: 'portable-bluetooth-speaker-0',
      price: '990.00',
      old: '1290.00',
      percent: 23,
    ),
    product(
      6,
      'moisturizer',
      t('مرطب الوجه بفيتامين C', 'Vitamin C face moisturizer'),
      image: 'face-moisturizer-0',
      price: '1490.00',
    ),
    product(
      7,
      'blanket',
      t('بطانية فليس ناعمة', 'Soft fleece blanket'),
      image: 'soft-fleece-blanket-0',
      price: '650.00',
    ),
    product(
      8,
      'kettle',
      t('غلاية ستانلس ستيل 1.7 لتر', 'Stainless kettle 1.7 L'),
      image: 'stainless-kettle-0',
      price: '780.00',
    ),
  ];

  Map<String, Object?> cat(
    int id,
    String slug,
    String a,
    String e,
    String image, [
    List<Map<String, Object?>> children = const [],
  ]) => {
    'id': id,
    'name': t(a, e),
    'slug': slug,
    'image': catImg(image),
    'icon': null,
    'children': children,
  };

  List<Map<String, Object?>> get categories => [
    cat(1, 'beauty', 'مستحضرات التجميل', 'Beauty', 'beauty', [
      cat(11, 'skin-care', 'العناية بالبشرة', 'Skin care', 'skin-care'),
      cat(12, 'perfumes', 'العطور', 'Perfumes', 'perfumes'),
      cat(13, 'moisturizers', 'المرطبات', 'Moisturizers', 'moisturizers'),
    ]),
    cat(2, 'perfumes', 'العطور', 'Perfumes', 'fragrance'),
    cat(3, 'fashion', 'الأزياء', 'Fashion', 'fashion', [
      cat(31, 'dresses', 'فساتين', 'Dresses', 'dresses'),
    ]),
    cat(4, 'accessories', 'الإكسسوارات', 'Accessories', 'accessories'),
    cat(5, 'bedding', 'المفروشات', 'Bedding', 'bedding'),
    cat(6, 'mobiles', 'الموبايلات', 'Mobiles', 'mobiles'),
  ];

  List<Map<String, Object?>> get brands => [
    for (final (i, a, e, n) in [
      (1, 'لوريال باريس', "L'Oréal Paris", 48),
      (2, 'نيفيا', 'Nivea', 52),
      (3, 'لاروش بوزيه', 'La Roche-Posay', 36),
      (4, 'ديور', 'Dior', 18),
      (5, 'شانيل', 'Chanel', 12),
      (6, 'ميبيلين', 'Maybelline', 40),
    ])
      {
        'id': i,
        'name': t(a, e),
        'slug': 'brand-$i',
        'logo': null,
        'products_count': n,
      },
  ];

  Map<String, Object?> bundle(int id, {bool soldOut = false}) => {
    'id': id,
    'slug': 'bundle-$id',
    'name': id.isOdd
        ? t(
            'باقة العناية اليومية: عطر + مرطب + كريم يدين',
            'Daily care bundle: perfume + moisturiser + hand cream',
          )
        : t(
            'باقة سهرة: فستان + حذاء + عطر',
            'Evening bundle: dress + shoes + perfume',
          ),
    'description': t(
      'مجموعة يومية متكاملة بسعر أقل من شراء كل منتج على حدة، مغلفة كهدية عند الطلب.',
      'A complete daily set for less than buying each item, gift-wrapped on request.',
    ),
    'image': null,
    'price': '2290.00',
    'regular_total': '2970.00',
    'savings': '680.00',
    'in_stock': !soldOut,
    'available': true,
    'ends_at': null,
    'components': [
      for (final (pid, name, image, q)
          in id.isOdd
              ? [
                  (
                    1,
                    t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume'),
                    'womens-perfume-50ml-0',
                    1,
                  ),
                  (
                    6,
                    t('مرطب الوجه بفيتامين C', 'Vitamin C moisturiser'),
                    'face-moisturizer-0',
                    1,
                  ),
                  (
                    2,
                    t('كريم اليدين بزبدة الشيا 75 مل', 'Shea hand cream'),
                    'hand-cream-0',
                    2,
                  ),
                ]
              : [
                  (
                    3,
                    t('فستان سهرة ستان طويل', 'Satin evening dress'),
                    'evening-dress-0',
                    1,
                  ),
                  (
                    9,
                    t('حذاء كاجوال خفيف', 'Light casual shoes'),
                    'casual-shoes-0',
                    1,
                  ),
                  (
                    1,
                    t('عطر زهور الربيع', 'Spring Blossom perfume'),
                    'womens-perfume-50ml-1',
                    1,
                  ),
                ])
        {
          'product_id': pid,
          'name': name,
          'slug': 'p$pid',
          'quantity': q,
          'unit_price': '990.00',
          'image': img(image),
        },
    ],
  };

  Map<String, Object?> get home => {
    'banners': {
      'hero': [
        {
          'id': 1,
          'placement': 'hero',
          'title': 'Hero',
          'image': 'stock://banners/0.webp',
          'link': null,
        },
        {
          'id': 2,
          'placement': 'hero',
          'title': 'Hero 2',
          'image': 'stock://banners/1.webp',
          'link': null,
        },
      ],
      'strips': <Object>[],
      'tiles': [
        {
          'id': 3,
          'placement': 'tile',
          'title': 'Tile',
          'image': 'stock://banners/2.webp',
          'link': null,
        },
        {
          'id': 4,
          'placement': 'tile',
          'title': 'Tile',
          'image': 'stock://banners/3.webp',
          'link': null,
        },
      ],
      'popup': null,
    },
    'categories': categories,
    'flash_sale': {
      'id': 1,
      'name': t('عروض خاطفة', 'Flash sale'),
      'ends_at': DateTime.now()
          .add(const Duration(hours: 2, minutes: 14))
          .toIso8601String(),
      'products': products.take(4).toList(),
    },
    'rails': {
      'recommended': products.reversed.toList(),
      'most_sold': products,
      'most_reviewed': products.skip(2).toList(),
    },
    'brands': brands,
  };

  Map<String, Object?> productDetail({bool soldOut = false}) => {
    'id': 1,
    'slug': soldOut ? 'speaker' : 'perfume',
    'name': soldOut
        ? t('سماعة بلوتوث محمولة', 'Portable Bluetooth speaker')
        : t(
            'عطر زهور الربيع للنساء أو دو بارفان 50 مل',
            'Spring Blossom eau de parfum 50 ml',
          ),
    'description': t(
      '<p>عطر زهري منعش بنفحات الياسمين والفانيليا مع قاعدة من المسك الأبيض. يدوم طويلاً ومناسب للاستخدام اليومي والهدايا.</p>',
      '<p>A fresh floral scent with jasmine and vanilla over white musk. Long-lasting, for every day and for gifting.</p>',
    ),
    'sku': 'LRP-5012',
    'brand': {
      'id': 1,
      'name': t('لوريال باريس', "L'Oréal Paris"),
      'slug': 'brand-1',
      'logo': null,
    },
    'categories': <Object>[],
    'images': [
      {
        'url': img(
          soldOut ? 'portable-bluetooth-speaker-0' : 'womens-perfume-50ml-0',
        ),
        'thumb': null,
      },
    ],
    'media': soldOut
        ? <Object>[]
        : [
            {
              'id': 1,
              'type': 'image',
              'url': img('womens-perfume-50ml-0'),
              'thumb': null,
              'poster': null,
              'mime': 'image/webp',
              'sort_order': 0,
            },
            {
              'id': 2,
              'type': 'video',
              'url': 'https://example.test/v.mp4',
              'thumb': null,
              'poster': img('womens-perfume-50ml-1'),
              'mime': 'video/mp4',
              'sort_order': 1,
            },
            {
              'id': 3,
              'type': 'image',
              'url': img('hand-cream-0'),
              'thumb': null,
              'poster': null,
              'mime': 'image/webp',
              'sort_order': 2,
            },
            {
              'id': 4,
              'type': 'image',
              'url': img('face-moisturizer-0'),
              'thumb': null,
              'poster': null,
              'mime': 'image/webp',
              'sort_order': 3,
            },
            {
              'id': 5,
              'type': 'image',
              'url': img('bath-towel-set-4pc-0'),
              'thumb': null,
              'poster': null,
              'mime': 'image/webp',
              'sort_order': 4,
            },
          ],
    'price': soldOut ? '990.00' : '1250.00',
    'regular_price': soldOut ? '990.00' : '1690.00',
    'options': soldOut
        ? <Object>[]
        : [
            {
              'id': 1,
              'name': t('الحجم', 'Size'),
              'values': [
                {'id': 10, 'label': t('30 مل', '30 ml'), 'color': null},
                {'id': 11, 'label': t('50 مل', '50 ml'), 'color': null},
                {'id': 12, 'label': t('100 مل', '100 ml'), 'color': null},
              ],
            },
            {
              'id': 2,
              'name': t('اللون', 'Colour'),
              'values': [
                {'id': 20, 'label': t('وردي', 'Pink'), 'color': '#F7B6CB'},
                {'id': 21, 'label': t('ذهبي', 'Gold'), 'color': '#D7B987'},
              ],
            },
          ],
    'variants': soldOut
        ? [variantJson(0, [], price: '990.00', inStock: false, stock: 0)]
        : [
            {
              ...variantJson(100, [11, 20], price: '1250.00'),
              'old': '1690.00',
              'flash_ends': DateTime.now()
                  .add(const Duration(hours: 2, minutes: 14))
                  .toIso8601String(),
            },
            variantJson(101, [10, 20], price: '890.00'),
            variantJson(102, [12, 20], price: '1890.00'),
            variantJson(103, [11, 21], price: '1250.00', stock: 3),
          ],
    'rating': {'average': 4.6, 'count': 128},
    'reviews': [for (var i = 1; i <= 2; i++) review(i)],
    'related': products.skip(4).toList(),
    'in_wishlist': false,
    'can_review': true,
  };

  Map<String, Object?> review(int i) => {
    'id': i,
    'rating': i.isOdd ? 5 : 4,
    'body': t(
      'الريحة تجنن وبتفضل طول اليوم، والتغليف كان شيك جداً. هشتريه تاني أكيد.',
      'Lovely scent that lasts all day, and the packaging was beautiful. Will buy again.',
    ),
    'author': [
      t('منى خالد', 'Mona K.'),
      t('سارة أحمد', 'Sara A.'),
      t('ياسمين علي', 'Yasmin A.'),
      t('ريم محمود', 'Reem M.'),
    ][(i - 1) % 4],
    'created_at': '2026-11-01T10:00:00+02:00',
  };

  Map<String, Object?> line(
    int id,
    String name,
    String image,
    String price, {
    int qty = 1,
    bool available = true,
    bool bundle = false,
  }) => {
    ...lineJson(
      id,
      quantity: qty,
      name: name,
      price: price,
      available: available,
    ),
    'image': img(image),
    'is_bundle': bundle,
    'components': bundle
        ? [
            {
              'name': t('عطر زهور الربيع', 'Spring Blossom perfume'),
              'quantity': 1,
            },
            {'name': t('كريم اليدين', 'Hand cream'), 'quantity': 2},
          ]
        : <Object>[],
  };

  Map<String, Object?> cart({bool empty = false}) => {
    'items': empty
        ? <Object>[]
        : [
            line(
              1,
              t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume 50 ml'),
              'womens-perfume-50ml-0',
              '1250.00',
            ),
            line(
              2,
              t('مرطب الوجه بفيتامين C', 'Vitamin C moisturiser'),
              'face-moisturizer-0',
              '1490.00',
            ),
            line(
              3,
              t('باقة العناية اليومية', 'Daily care bundle'),
              'hand-cream-0',
              '2290.00',
              bundle: true,
            ),
          ],
    'packaging': null,
    'has_flash': !empty,
    'totals': {
      ...totalsJson(
        subtotal: '5030.00',
        discount: '201.00',
        shipping: '60.00',
        total: '4949.00',
      ),
      'cod_fee': '15.00',
      'packaging_fee': '45.00',
      'cod_amount': '4889.00',
      'prepaid_shipping': '60.00',
      'cod_fee_label': t('رسوم الدفع عند الاستلام', 'Cash on delivery fee'),
    },
  };

  Map<String, Object?> order(
    String token,
    String number,
    String status,
    String label, {
    bool proof = false,
  }) => {
    ...orderJson(
      token: token,
      number: number,
      status: status,
      total: '1929.00',
      canPayOnline: proof,
      canUploadProof: proof,
    ),
    'status': {'value': status, 'label': label},
    'payment_method': {
      'id': 2,
      'type': 'wallet',
      'name': t('فودافون كاش', 'Vodafone Cash'),
    },
    'customer': {'name': t('سارة أحمد', 'Sara Ahmed')},
    'city': t('القاهرة', 'Cairo'),
    'address': t('12 شارع النصر، مدينة نصر', '12 El-Nasr St, Nasr City'),
    'shipping_company': t('بوسطة', 'Bosta'),
    'proof_status': proof ? 'pending' : null,
    'pay_now': '60.00',
    'subtotal': '2010.00',
    'discount': '201.00',
    'shipping': '60.00',
    'cod_fee': '15.00',
    'packaging_fee': '45.00',
    'cod_amount': '1869.00',
    'prepaid_shipping': '60.00',
    'items': [
      {
        'id': 1,
        'name': t('عطر زهور الربيع للنساء 50 مل', 'Spring Blossom perfume'),
        'sku': 'A',
        'unit_price': '1250.00',
        'quantity': 1,
        'line_subtotal': '1250.00',
        'line_discount': '0.00',
        'returned_qty': 0,
      },
      {
        'id': 2,
        'name': t('كريم اليدين بزبدة الشيا 75 مل', 'Shea hand cream'),
        'sku': 'B',
        'unit_price': '390.00',
        'quantity': 2,
        'line_subtotal': '780.00',
        'line_discount': '0.00',
        'returned_qty': 0,
      },
    ],
    'timeline': [
      {
        'status': t('قيد الانتظار', 'Pending'),
        'note': null,
        'at': '2026-11-14T10:00:00+02:00',
      },
      {
        'status': t('مؤكد', 'Confirmed'),
        'note': null,
        'at': '2026-11-14T12:00:00+02:00',
      },
      {
        'status': t('قيد التجهيز', 'Processing'),
        'note': t('جارٍ تجهيز طلبك', 'Packing your order'),
        'at': '2026-11-15T09:00:00+02:00',
      },
    ],
  };

  Map<String, Object?> payMethod(
    int id,
    String type,
    String a,
    String e, {
    bool proof = false,
    String? account,
    String pct = '0.000',
    String? instr,
  }) => {
    'id': id,
    'type': type,
    'provider': type,
    'name': t(a, e),
    'instructions': instr,
    'account_number': account,
    'requires_proof': proof,
    'fee_percent': pct,
    'fee_fixed': '0.00',
  };
}

/// Fake backend for every shopping endpoint.
class ShopBackend {
  ShopBackend(this.d);
  final ShopData d;
  bool emptyCart = false;
  bool emptyWishlist = false;

  ResponseBody? call(RequestOptions o) {
    final p = o.path;
    final m = o.method;
    if (p == '/home') return envelope(d.home);
    if (p == '/menu') return envelope(d.categories);
    if (p == '/brands') return envelope(d.brands);
    if (p == '/bundles') {
      return envelope([
        d.bundle(1),
        d.bundle(2),
        d.bundle(3),
        d.bundle(4, soldOut: true),
      ]);
    }
    if (p.startsWith('/bundles/')) return envelope(d.bundle(1));
    if (p == '/auth/me') {
      return envelope({
        'id': 1,
        'name': d.t('سارة أحمد', 'Sara Ahmed'),
        'email': 'sara@example.test',
      });
    }
    if (p == '/wishlist') {
      return envelope(emptyWishlist ? <Object>[] : d.products.take(4).toList());
    }
    if (p == '/categories/perfumes') {
      return envelope({
        'name': d.t('العطور', 'Perfumes'),
        'slug': 'perfumes',
        'image': null,
        'description': null,
        'breadcrumbs': <Object>[],
        'children': [
          d.cat(21, 'women', 'عطور نسائية', "Women's", 'women'),
          d.cat(22, 'men', 'عطور رجالية', "Men's", 'men'),
        ],
      });
    }
    if (p == '/catalog') {
      final q = o.queryParameters['q'];
      if (q != null) return paged(<Object>[], total: 0);
      return paged(d.products, total: 128, last: 6);
    }
    if (p == '/catalog/facets') {
      return envelope({
        'brands': d.brands.take(3).toList(),
        'attributes': <Object>[],
        'price': {'min': '50', 'max': '5000'},
      });
    }
    if (p == '/catalog/suggest') {
      return envelope({
        'products': [
          for (final x in d.products.take(3))
            {
              'name': x['name'],
              'slug': x['slug'],
              'price': x['price'],
              'image': x['image'],
            },
        ],
        'categories': <Object>[],
      });
    }
    if (p == '/products/perfume') return envelope(d.productDetail());
    if (p == '/products/speaker') {
      return envelope(d.productDetail(soldOut: true));
    }
    if (p.endsWith('/reviews')) {
      return paged([for (var i = 1; i <= 6; i++) d.review(i)], total: 6);
    }
    if (p == '/gift-finder' && m == 'POST') {
      Map<String, Object?> gift(
        Map<String, Object?> x,
        String reason, {
        bool simple = true,
      }) => {
        'product_id': x['id'],
        'name': x['name'],
        'slug': x['slug'],
        'price': x['price'],
        'simple': simple,
        'image': x['image'],
        'explanation': reason,
        'picker': {'options': <Object>[], 'variants': <Object>[]},
      };
      final ps = d.products;
      return envelope({
        'token': 'gift-1',
        'results': [
          gift(ps[0], d.t('مناسب لعيد الأم', "Great for Mother's Day")),
          gift(ps[1], d.t('ضمن الميزانية', 'Within budget')),
          gift(ps[3], d.t('الأكثر طلباً', 'Popular pick')),
        ],
        'bundles': [d.bundle(1), d.bundle(2)],
        'near_budget': [gift(ps[2], ''), gift(ps[7], '')],
      });
    }
    switch ('$m $p') {
      case 'GET /cart':
      case 'POST /cart/quote':
      case 'POST /checkout/validate':
        return envelope(d.cart(empty: emptyCart));
      case 'GET /addresses':
        return envelope([
          {
            ...addressJson(1, def: true),
            'label': d.t('البيت', 'Home'),
            'name': d.t('سارة أحمد', 'Sara Ahmed'),
            'city': d.t('القاهرة', 'Cairo'),
            'address': d.t(
              '12 شارع النصر، مدينة نصر',
              '12 El-Nasr St, Nasr City',
            ),
          },
          {
            ...addressJson(2, cityId: 2),
            'label': d.t('الشغل', 'Work'),
            'name': d.t('سارة أحمد', 'Sara Ahmed'),
            'city': d.t('القاهرة', 'Cairo'),
            'address': d.t('5 شارع التسعين، التجمع', '5 90th St, New Cairo'),
          },
        ]);
      case 'GET /checkout/packaging':
        return envelope([
          {
            'id': 1,
            'name': d.t('صندوق هدايا فاخر', 'Luxury gift box'),
            'description': null,
            'image': img('dinner-set-36pc-0'),
            'price': '45.00',
          },
          {
            'id': 2,
            'name': d.t('كيس هدايا', 'Gift bag'),
            'description': null,
            'image': null,
            'price': '20.00',
          },
        ]);
      case 'GET /checkout/payment-methods':
        return envelopeWithMeta(
          [
            d.payMethod(1, 'cod', 'الدفع عند الاستلام', 'Cash on delivery'),
            d.payMethod(
              2,
              'wallet',
              'فودافون كاش',
              'Vodafone Cash',
              proof: true,
              account: '010 1234 5678',
              instr: d.t(
                'حوّل المبلغ ثم ارفع صورة الإيصال',
                'Transfer, then upload the receipt',
              ),
            ),
            d.payMethod(3, 'online', 'بطاقة بنكية', 'Bank card', pct: '2.500'),
          ],
          {'cod_collect_shipping_upfront': false},
        );
      case 'GET /checkout/shipping':
        return envelope([
          {
            'shipping_company_id': 7,
            'name': d.t('بوسطة', 'Bosta'),
            'eta_days': '2',
            'supports_cod': true,
            'fee': '60.00',
            'free': false,
            'order_total': '1929.00',
          },
          {
            'shipping_company_id': 8,
            'name': d.t('أرامكس', 'Aramex'),
            'eta_days': '3',
            'supports_cod': true,
            'fee': '45.00',
            'free': false,
            'order_total': '1914.00',
          },
        ]);
      case 'GET /orders':
        return envelopeWithMeta(
          [
            d.order('t1', '#10482', 'pending', d.t('قيد الانتظار', 'Pending')),
            d.order('t2', '#10455', 'shipped', d.t('تم الشحن', 'Shipped')),
            d.order(
              't3',
              '#10391',
              'delivered',
              d.t('تم التوصيل', 'Delivered'),
            ),
            d.order('t4', '#10302', 'cancelled', d.t('ملغي', 'Cancelled')),
          ],
          {
            'pagination': {
              'current_page': 1,
              'last_page': 1,
              'per_page': 10,
              'total': 4,
            },
          },
        );
    }
    if (p.startsWith('/orders/')) {
      return envelope(
        d.order(
          't1',
          '#10482',
          'pending',
          d.t('قيد الانتظار', 'Pending'),
          proof: true,
        ),
      );
    }
    return null;
  }
}

// -- driver ----------------------------------------------------------------

final _boundary = GlobalKey();

class Shot {
  const Shot(
    this.name,
    this.run, {
    this.signedIn = false,
    this.setup,
    this.textScale = 1,
    this.affiliate = false,
    this.appConfig = const {},
    this.splashMinDuration = Duration.zero,
    this.after,
  });
  final double textScale;

  /// Signs the affiliate in (saved affiliate token).
  final bool affiliate;
  final Map<String, Object?> appConfig;
  final Duration splashMinDuration;

  /// Runs after the capture (e.g. drains a long splash timer).
  final Future<void> Function(WidgetTester t)? after;
  final String name;
  final Future<void> Function(WidgetTester t, GoRouter r) run;
  final bool signedIn;
  final void Function(ShopBackend b)? setup;
}

Future<void> settleShort(WidgetTester t, [int frames = 12]) async {
  for (var i = 0; i < frames; i++) {
    await t.pump(const Duration(milliseconds: 100));
  }
}

ImageProvider stockImage(String url) =>
    FileImage(File('$_stock/${url.substring('stock://'.length)}'));

/// Decodes every stock photo the fake data uses in the real async zone, so
/// the widgets find them complete in the image cache (images first loaded
/// inside the fake-async test zone would never finish).
Future<void> warmImages(WidgetTester t, Set<String> urls) async {
  await t.runAsync(() async {
    for (final u in urls) {
      final done = Completer<void>();
      final provider = u.startsWith('stock://')
          ? stockImage(u)
          : AssetImage(u) as ImageProvider;
      final stream = provider.resolve(ImageConfiguration.empty);
      late final ImageStreamListener l;
      l = ImageStreamListener(
        (_, _) {
          if (!done.isCompleted) done.complete();
          stream.removeListener(l);
        },
        onError: (_, _) {
          if (!done.isCompleted) done.complete();
        },
      );
      stream.addListener(l);
      await done.future.timeout(const Duration(seconds: 10), onTimeout: () {});
    }
  });
}

/// Every `stock://` URL in a JSON-ish value.
Set<String> stockUrls(Object? v) => switch (v) {
  String s when s.startsWith('stock://') => {s},
  Map() => {for (final x in v.values) ...stockUrls(x)},
  List() => {for (final x in v) ...stockUrls(x)},
  _ => <String>{},
};

Future<void> precacheImages(WidgetTester t) => settleShort(t, 4);

Future<void> capture(WidgetTester t, String locale, String name) async {
  await precacheImages(t);
  final ro =
      _boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await t.runAsync(() async {
    final image = await ro.toImage(pixelRatio: _dpr);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final file = File('$_out/$locale/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}

GoRouter routerOf(WidgetTester t) =>
    GoRouter.of(t.element(find.byType(Scaffold).first));

Future<void> go(WidgetTester t, String loc, {bool push = false}) async {
  final r = routerOf(t);
  push ? r.push(loc) : r.go(loc);
  await settleShort(t);
}

/// Checkout: address (default preselected) -> shipping -> packaging ->
/// payment step.
Future<void> toPayment(WidgetTester t) async {
  await go(t, '/checkout', push: true);
  Future<void> next() async {
    await t.tap(find.byType(FilledButton).last);
    await settleShort(t, 6);
  }

  await next();
  await t.tap(find.byType(ChoiceCard).first);
  await settleShort(t, 6);
  await next();
  await next();
}

final shots = <Shot>[
  Shot('01-home', (t, r) async {}),
  Shot('02-home-flash', (t, r) async {
    await t.drag(find.byType(ListView).first, const Offset(0, -560));
    await settleShort(t);
  }),
  Shot('03-categories', (t, r) async {
    await go(t, '/categories');
    await t.tap(find.byType(InkWell).at(1));
    await settleShort(t);
  }),
  Shot('04-listing', (t, r) async => go(t, '/category/perfumes', push: true)),
  Shot('05-filter-sheet', (t, r) async {
    await go(t, '/category/perfumes', push: true);
    await t.tap(find.byKey(const ValueKey('listing-filter')));
    await settleShort(t);
  }),
  Shot('06-search', (t, r) async {
    await go(t, '/search', push: true);
    await t.enterText(find.byType(TextField).first, 'عطر');
    await settleShort(t, 8);
  }),
  Shot(
    '07-search-no-results',
    (t, r) async => go(t, '/search/results?q=xyz', push: true),
  ),
  Shot('08-product', (t, r) async => go(t, '/product/perfume', push: true)),
  Shot('09-product-details', (t, r) async {
    await go(t, '/product/perfume', push: true);
    await t.drag(find.byType(ListView).last, const Offset(0, -700));
    await settleShort(t);
  }),
  Shot('10-notify-sheet', (t, r) async {
    await go(t, '/product/speaker', push: true);
    await t.tap(
      find.descendant(
        of: find.byKey(const ValueKey('product-buy-bar')),
        matching: find.byType(FilledButton),
      ),
    );
    await settleShort(t);
  }),
  Shot('11-variant-sheet', (t, r) async {
    await go(t, '/category/perfumes', push: true);
    await t.tap(find.byType(FilledButton).first);
    await settleShort(t, 15);
  }),
  Shot('12-reviews', (t, r) async {
    await go(t, '/product/perfume', push: true);
    await go(t, '/product/perfume/reviews', push: true);
  }),
  Shot('13-brands', (t, r) async => go(t, '/brands', push: true)),
  Shot('14-bundles', (t, r) async => go(t, '/bundles', push: true)),
  Shot('15-bundle', (t, r) async => go(t, '/bundle/bundle-1', push: true)),
  Shot('16-gift-budget', (t, r) async {
    await go(t, '/gift-finder', push: true);
    await t.tap(find.byType(ChoiceChip).at(2));
    await settleShort(t);
  }),
  Shot('17-gift-occasion', (t, r) async {
    await go(t, '/gift-finder', push: true);
    await t.tap(find.byType(ChoiceChip).at(2));
    await t.tap(find.byType(FilledButton).last);
    await settleShort(t);
    await t.tap(find.byType(ChoiceChip).at(1));
    await settleShort(t);
  }),
  Shot('18-gift-results', (t, r) async {
    await go(t, '/gift-finder', push: true);
    await t.tap(find.byType(ChoiceChip).at(2));
    for (var i = 0; i < 3; i++) {
      await t.tap(find.byType(FilledButton).last);
      await settleShort(t, 4);
    }
    await t.tap(find.byType(FilledButton).last);
    await settleShort(t);
  }),
  Shot('19-cart', (t, r) async => go(t, '/cart'), signedIn: true),
  Shot('20-cart-totals', (t, r) async {
    await go(t, '/cart');
    await t.drag(find.byType(ListView).first, const Offset(0, -700));
    await settleShort(t);
  }, signedIn: true),
  Shot(
    '21-cart-empty',
    (t, r) async => go(t, '/cart'),
    setup: (b) => b.emptyCart = true,
  ),
  Shot(
    '22-checkout-address',
    (t, r) async => go(t, '/checkout', push: true),
    signedIn: true,
  ),
  Shot('23-checkout-payment', (t, r) async {
    await toPayment(t);
    await t.tap(find.byType(ChoiceCard).at(1));
    await settleShort(t);
  }, signedIn: true),
  Shot('24-checkout-review', (t, r) async {
    await toPayment(t);
    await t.tap(find.byType(ChoiceCard).at(0));
    await settleShort(t, 6);
    await t.tap(find.byType(FilledButton).last);
    await settleShort(t);
  }, signedIn: true),
  Shot(
    '25-order-confirmation',
    (t, r) async => go(t, '/orders/t1/confirmation', push: true),
    signedIn: true,
  ),
  Shot(
    '26-orders',
    (t, r) async => go(t, '/orders', push: true),
    signedIn: true,
  ),
  Shot(
    '27-order-detail',
    (t, r) async => go(t, '/orders/t1', push: true),
    signedIn: true,
  ),
  Shot('28-order-detail-more', (t, r) async {
    await go(t, '/orders/t1', push: true);
    await t.drag(find.byType(ListView).last, const Offset(0, -900));
    await settleShort(t);
  }, signedIn: true),
  Shot('29-wishlist', (t, r) async => go(t, '/wishlist'), signedIn: true),
  Shot(
    '30-wishlist-empty',
    (t, r) async => go(t, '/wishlist'),
    signedIn: true,
    setup: (b) => b.emptyWishlist = true,
  ),
  // Large text (accessibility): nothing may clip at 1.3.
  Shot('31-home-text130', (t, r) async {
    await t.drag(find.byType(ListView).first, const Offset(0, -560));
    await settleShort(t);
  }, textScale: 1.3),
  Shot(
    '32-listing-text130',
    (t, r) async => go(t, '/category/perfumes', push: true),
    textScale: 1.3,
  ),
  Shot(
    '33-product-text130',
    (t, r) async => go(t, '/product/perfume', push: true),
    textScale: 1.3,
  ),
  Shot(
    '34-cart-text130',
    (t, r) async => go(t, '/cart'),
    signedIn: true,
    textScale: 1.3,
  ),
  Shot(
    '35-checkout-payment-text130',
    (t, r) async {
      await toPayment(t);
      await t.tap(find.byType(ChoiceCard).at(1));
      await settleShort(t);
    },
    signedIn: true,
    textScale: 1.3,
  ),
  ...accountShots,
];

void main() {
  final enabled = autoUpdateGoldenFiles;
  setUpAll(() async {
    if (!enabled) return;
    await _loadFonts();
    NetworkImageX.debugImageOverride = (url) {
      if (url.startsWith('stock://')) return stockImage(url);
      return const AssetImage('assets/branding/icon.png');
    };
  });

  for (final locale in ['ar', 'en']) {
    for (final s in shots) {
      testWidgets('$locale ${s.name}', (t) async {
        t.view.physicalSize = _size * _dpr;
        t.view.devicePixelRatio = _dpr;
        t.platformDispatcher.accessibilityFeaturesTestValue =
            const FakeAccessibilityFeatures(disableAnimations: true);
        t.platformDispatcher.textScaleFactorTestValue = s.textScale;
        addTearDown(t.platformDispatcher.clearTextScaleFactorTestValue);
        addTearDown(t.view.reset);
        addTearDown(t.platformDispatcher.clearAccessibilityFeaturesTestValue);

        final backend = ShopBackend(ShopData(locale == 'ar'));
        final account = AccountBackend(AccountData(locale == 'ar'));
        s.setup?.call(backend);
        final env = TestEnv(
          prefs: {'onboarding_done': true, 'locale': locale},
          appConfig: s.appConfig,
          splashMinDuration: s.splashMinDuration,
          storeProfile: account.d.storeProfile,
          extra: (o) => account.call(o) ?? backend.call(o),
        );
        await env.init();
        final d = backend.d;
        await warmImages(t, {
          ...stockUrls(d.home),
          ...stockUrls(d.productDetail()),
          ...stockUrls(d.productDetail(soldOut: true)),
          ...stockUrls(d.cart()),
          ...stockUrls(d.bundle(1)),
          ...stockUrls(d.bundle(2)),
          ...stockUrls(d.categories),
          'stock://products/casual-shoes-0.webp',
          'stock://products/dinner-set-36pc-0.webp',
          'stock://categories/women.webp',
          'stock://categories/men.webp',
          ...account.d.images,
          'assets/branding/splash_logo.png',
        });
        if (s.signedIn) await env.secrets.write('auth_token', '12|secret');
        if (s.affiliate) {
          await env.secrets.write('affiliate_token', 'aff|secret');
        }
        await t.pumpWidget(
          RepaintBoundary(
            key: _boundary,
            child: ProviderScope(
              overrides: env.overrides,
              child: const AlrafApp(),
            ),
          ),
        );
        await settleShort(t, 20);
        await precacheImages(t);
        await s.run(t, routerOf(t));
        await settleShort(t);
        await capture(t, locale, s.name);
        await s.after?.call(t);
      }, skip: !enabled);
    }
  }
}
