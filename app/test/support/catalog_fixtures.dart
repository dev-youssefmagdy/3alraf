import 'package:dio/dio.dart';

import 'harness.dart';

Map<String, Object?> card(
  int id,
  String slug, {
  String price = '100.00',
  String? old,
  int? percent,
}) => {
  'id': id,
  'slug': slug,
  'name': 'Product $slug',
  'image': null,
  'price': price,
  'old_price': old,
  'discount_percent': percent,
  'flash': false,
  'flash_ends_at': null,
  'rating': null,
  'reviews_count': 0,
};

Map<String, Object?> homeJson() => {
  'banners': {
    'hero': [
      {
        'id': 1,
        'placement': 'hero',
        'title': 'Hero',
        'image': null,
        'link': '/product/serum',
      },
    ],
    'strips': <Object>[],
    'tiles': <Object>[],
    'popup': null,
  },
  'categories': [
    {
      'id': 1,
      'name': 'Skincare',
      'slug': 'skincare',
      'image': null,
      'icon': null,
      'children': <Object>[],
    },
  ],
  'flash_sale': null,
  'rails': {
    'recommended': [
      card(1, 'serum', price: '250.00', old: '300.00', percent: 17),
    ],
    'most_sold': [card(2, 'cream')],
    'most_reviewed': <Object>[],
  },
  'brands': [
    {'id': 1, 'name': 'Sony', 'slug': 'sony', 'logo': null},
  ],
};

Map<String, Object?> variantJson(
  int id,
  List<int> values, {
  String price = '100.00',
  bool inStock = true,
  int? stock = 10,
}) => {
  'id': id,
  'values': values,
  'price': price,
  'old': null,
  'flash_ends': null,
  'stock': stock,
  'in_stock': inStock,
  'label': 'v$id',
  'alert': 5,
  'sku': 'S$id',
  'image': null,
};

/// Size (S=10, M=11) x Color (red=20, blue=21); M/red is sold out.
Map<String, Object?> variableProductJson() => {
  'id': 5,
  'slug': 'shirt',
  'name': 'Shirt',
  'description': '<p>Nice shirt</p>',
  'sku': 'SH',
  'brand': {'id': 1, 'name': 'Sony', 'slug': 'sony', 'logo': null},
  'categories': <Object>[],
  'images': <Object>[],
  'price': '100.00',
  'regular_price': '100.00',
  'options': [
    {
      'id': 1,
      'name': 'Size',
      'values': [
        {'id': 10, 'label': 'S', 'color': null},
        {'id': 11, 'label': 'M', 'color': null},
      ],
    },
    {
      'id': 2,
      'name': 'Color',
      'values': [
        {'id': 20, 'label': 'Red', 'color': '#ff0000'},
        {'id': 21, 'label': 'Blue', 'color': '#0000ff'},
      ],
    },
  ],
  'variants': [
    variantJson(100, [10, 20], price: '110.00'),
    variantJson(101, [10, 21]),
    variantJson(102, [11, 20], inStock: false, stock: 0),
    variantJson(103, [11, 21], price: '130.00', stock: 3),
  ],
  'rating': {'average': 4.5, 'count': 2},
  'reviews': [
    {
      'id': 1,
      'rating': 5,
      'body': 'Great',
      'status': 'approved',
      'author': 'Ali',
      'created_at': '2026-11-01T10:00:00+02:00',
    },
  ],
  'related': [card(9, 'hat')],
  'in_wishlist': false,
  'can_review': true,
};

Map<String, Object?> simpleProductJson({bool inStock = true}) => {
  'id': 6,
  'slug': 'soap',
  'name': 'Soap',
  'description': null,
  'sku': 'SO',
  'brand': null,
  'categories': <Object>[],
  'images': <Object>[],
  'price': '20.00',
  'regular_price': '25.00',
  'options': <Object>[],
  'variants': [
    variantJson(
      0,
      [],
      price: '20.00',
      inStock: inStock,
      stock: inStock ? 2 : 0,
    ),
  ],
  'rating': {'average': 0, 'count': 0},
  'reviews': <Object>[],
  'related': <Object>[],
  'in_wishlist': false,
  'can_review': false,
};

/// Envelope with a `meta.pagination` block.
ResponseBody paged(
  List<Object?> items, {
  int page = 1,
  int last = 1,
  int? total,
}) => envelopeWithMeta(items, {
  'pagination': {
    'current_page': page,
    'last_page': last,
    'per_page': 24,
    'total': total ?? items.length,
  },
});
