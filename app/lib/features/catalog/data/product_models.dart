import '../../../core/network/json.dart';
import 'catalog_models.dart';

class ProductImage {
  const ProductImage({required this.url, this.thumb});
  final String url;
  final String? thumb;
}

class OptionValue {
  const OptionValue({required this.id, required this.label, this.color});
  final int id;
  final String label;
  final String? color;
}

/// An attribute group (Size, Color...) of a variable product.
class ProductOption {
  const ProductOption({
    required this.id,
    required this.name,
    required this.values,
  });
  final int id;
  final String name;
  final List<OptionValue> values;

  OptionValue? valueById(int id) {
    for (final v in values) {
      if (v.id == id) return v;
    }
    return null;
  }
}

/// A purchasable combination. Simple products have exactly one variant with
/// `id == 0` and no `values`.
class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.valueIds,
    required this.price,
    required this.inStock,
    this.oldPrice,
    this.flashEnds,
    this.stock,
    this.alert = 5,
    this.label,
    this.sku,
    this.image,
  });

  factory ProductVariant.fromJson(Map<String, Object?> j) => ProductVariant(
    id: intOf(j['id']),
    valueIds: asList(j['values']).map((e) => intOf(e)).toSet(),
    price: str(j['price']),
    oldPrice: strOrNull(j['old']),
    flashEnds: dateOrNull(j['flash_ends']),
    stock: intOrNull(j['stock']),
    inStock: boolOf(j['in_stock']),
    alert: intOf(j['alert'], 5),
    label: strOrNull(j['label']),
    sku: strOrNull(j['sku']),
    image: strOrNull(j['image']),
  );

  final int id;
  final Set<int> valueIds;
  final String price;
  final String? oldPrice;
  final DateTime? flashEnds;

  /// `null` = unlimited (backorder allowed).
  final int? stock;
  final bool inStock;
  final int alert;
  final String? label;
  final String? sku;
  final String? image;

  bool get isLowStock => inStock && stock != null && stock! <= alert;
}

class Review {
  const Review({
    required this.id,
    required this.rating,
    this.body,
    this.author,
    this.createdAt,
  });

  factory Review.fromJson(Map<String, Object?> j) => Review(
    id: intOf(j['id']),
    rating: intOf(j['rating']),
    body: strOrNull(j['body']),
    author: strOrNull(j['author']),
    createdAt: dateOrNull(j['created_at']),
  );

  final int id;
  final int rating;
  final String? body;
  final String? author;
  final DateTime? createdAt;
}

class ProductDetail {
  const ProductDetail({
    required this.id,
    required this.slug,
    required this.name,
    required this.price,
    required this.options,
    required this.variants,
    this.description,
    this.sku,
    this.brand,
    this.categories = const [],
    this.images = const [],
    this.regularPrice,
    this.ratingAverage = 0,
    this.ratingCount = 0,
    this.reviews = const [],
    this.related = const [],
    this.inWishlist = false,
    this.canReview = false,
  });

  factory ProductDetail.fromJson(Map<String, Object?> j) {
    final rating = asMap(j['rating']);
    final brand = asMap(j['brand']);
    return ProductDetail(
      id: intOf(j['id']),
      slug: str(j['slug']),
      name: str(j['name']),
      description: strOrNull(j['description']),
      sku: strOrNull(j['sku']),
      brand: brand.isEmpty ? null : Brand.fromJson(brand),
      categories: asMapList(
        j['categories'],
      ).map((c) => Crumb(name: str(c['name']), slug: str(c['slug']))).toList(),
      images: asMapList(j['images'])
          .where((i) => strOrNull(i['url']) != null)
          .map(
            (i) =>
                ProductImage(url: str(i['url']), thumb: strOrNull(i['thumb'])),
          )
          .toList(),
      price: str(j['price']),
      regularPrice: strOrNull(j['regular_price']),
      options: asMapList(j['options'])
          .map(
            (o) => ProductOption(
              id: intOf(o['id']),
              name: str(o['name']),
              values: asMapList(o['values'])
                  .map(
                    (v) => OptionValue(
                      id: intOf(v['id']),
                      label: str(v['label']),
                      color: strOrNull(v['color']),
                    ),
                  )
                  .toList(),
            ),
          )
          .toList(),
      variants: asMapList(j['variants']).map(ProductVariant.fromJson).toList(),
      ratingAverage: doubleOrNull(rating['average']) ?? 0,
      ratingCount: intOf(rating['count']),
      reviews: asMapList(j['reviews']).map(Review.fromJson).toList(),
      related: asMapList(j['related']).map(ProductSummary.fromJson).toList(),
      inWishlist: boolOf(j['in_wishlist']),
      canReview: boolOf(j['can_review']),
    );
  }

  final int id;
  final String slug;
  final String name;
  final String? description;
  final String? sku;
  final Brand? brand;
  final List<Crumb> categories;
  final List<ProductImage> images;
  final String price;
  final String? regularPrice;
  final List<ProductOption> options;
  final List<ProductVariant> variants;
  final double ratingAverage;
  final int ratingCount;
  final List<Review> reviews;
  final List<ProductSummary> related;
  final bool inWishlist;
  final bool canReview;

  bool get isVariable => options.isNotEmpty;

  /// The only variant of a simple product.
  ProductVariant? get simpleVariant =>
      !isVariable && variants.isNotEmpty ? variants.first : null;

  /// Whole product is out of stock when no variant can be bought.
  bool get anyInStock => variants.any((v) => v.inStock);
}

/// Pure selection logic for the variant picker (unit-tested).
abstract final class VariantPicker {
  /// The variant matching exactly the chosen value of every option, or null
  /// while the selection is incomplete or the combination does not exist.
  static ProductVariant? resolve(
    ProductDetail p,
    Map<int, int> selection, // optionId -> valueId
  ) {
    if (!p.isVariable) return p.simpleVariant;
    if (selection.length < p.options.length) return null;
    final chosen = selection.values.toSet();
    for (final v in p.variants) {
      if (v.valueIds.length == chosen.length &&
          v.valueIds.containsAll(chosen)) {
        return v;
      }
    }
    return null;
  }

  /// Whether [valueId] of [optionId] can still lead to an in-stock variant
  /// given the other options already chosen.
  static bool isAvailable(
    ProductDetail p,
    Map<int, int> selection,
    int optionId,
    int valueId,
  ) {
    final others = {
      for (final e in selection.entries)
        if (e.key != optionId) e.value,
    };
    return p.variants.any(
      (v) =>
          v.inStock &&
          v.valueIds.contains(valueId) &&
          v.valueIds.containsAll(others),
    );
  }

  /// Pre-selects the cheapest-path default: the first in-stock variant.
  static Map<int, int> defaultSelection(ProductDetail p) {
    if (!p.isVariable) return const {};
    final v = p.variants.where((v) => v.inStock).firstOrNull;
    if (v == null) return const {};
    return {
      for (final o in p.options)
        for (final val in o.values)
          if (v.valueIds.contains(val.id)) o.id: val.id,
    };
  }
}

class BundleComponent {
  const BundleComponent({
    required this.productId,
    required this.name,
    required this.slug,
    required this.quantity,
    required this.unitPrice,
    this.image,
  });

  final int productId;
  final String name;
  final String slug;
  final int quantity;
  final String unitPrice;
  final String? image;
}

class Bundle {
  const Bundle({
    required this.id,
    required this.slug,
    required this.name,
    required this.price,
    required this.regularTotal,
    required this.savings,
    required this.inStock,
    required this.available,
    this.description,
    this.image,
    this.endsAt,
    this.components = const [],
  });

  factory Bundle.fromJson(Map<String, Object?> j) => Bundle(
    id: intOf(j['id']),
    slug: str(j['slug']),
    name: str(j['name']),
    description: strOrNull(j['description']),
    image: strOrNull(j['image']),
    price: str(j['price']),
    regularTotal: str(j['regular_total']),
    savings: str(j['savings']),
    inStock: boolOf(j['in_stock']),
    available: boolOf(j['available']),
    endsAt: dateOrNull(j['ends_at']),
    components: asMapList(j['components'])
        .map(
          (c) => BundleComponent(
            productId: intOf(c['product_id']),
            name: str(c['name']),
            slug: str(c['slug']),
            quantity: intOf(c['quantity'], 1),
            unitPrice: str(c['unit_price']),
            image: strOrNull(c['image']),
          ),
        )
        .toList(),
  );

  final int id;
  final String slug;
  final String name;
  final String? description;
  final String? image;
  final String price;
  final String regularTotal;
  final String savings;
  final bool inStock;
  final bool available;
  final DateTime? endsAt;
  final List<BundleComponent> components;

  /// Savings shown only when positive (server decimal string, compared as text).
  bool get hasSavings {
    final n = double.tryParse(savings);
    return n != null && n > 0;
  }
}

/// A gift suggestion row (`POST gift-finder`).
class GiftItem {
  const GiftItem({
    required this.productId,
    required this.name,
    required this.slug,
    required this.price,
    required this.simple,
    this.image,
    this.explanation,
    this.options = const [],
    this.variants = const [],
  });

  factory GiftItem.fromJson(Map<String, Object?> j) {
    final picker = asMap(j['picker']);
    return GiftItem(
      productId: intOf(j['product_id']),
      name: str(j['name']),
      slug: str(j['slug']),
      price: str(j['price']),
      simple: boolOf(j['simple']),
      image: strOrNull(j['image']),
      explanation: strOrNull(j['explanation']),
      options: asMapList(picker['options'])
          .map(
            (o) => ProductOption(
              id: intOf(o['id']),
              name: str(o['name']),
              values: asMapList(o['values'])
                  .map(
                    (v) => OptionValue(
                      id: intOf(v['id']),
                      label: str(v['label']),
                      color: strOrNull(v['color']),
                    ),
                  )
                  .toList(),
            ),
          )
          .toList(),
      variants: asMapList(
        picker['variants'],
      ).map(ProductVariant.fromJson).toList(),
    );
  }

  final int productId;
  final String name;
  final String slug;
  final String price;
  final bool simple;
  final String? image;
  final String? explanation;
  final List<ProductOption> options;
  final List<ProductVariant> variants;
}

class GiftResults {
  const GiftResults({
    required this.token,
    required this.results,
    required this.bundles,
    required this.nearBudget,
  });

  factory GiftResults.fromJson(Map<String, Object?> j) => GiftResults(
    token: str(j['token']),
    results: asMapList(j['results']).map(GiftItem.fromJson).toList(),
    bundles: asMapList(j['bundles']).map(Bundle.fromJson).toList(),
    nearBudget: asMapList(j['near_budget']).map(GiftItem.fromJson).toList(),
  );

  final String token;
  final List<GiftItem> results;
  final List<Bundle> bundles;
  final List<GiftItem> nearBudget;
}

class GiftAnswers {
  const GiftAnswers({
    required this.budget,
    this.occasion,
    this.recipient,
    this.categoryId,
    this.restrictions,
  });
  final String budget;
  final String? occasion;
  final String? recipient;
  final int? categoryId;
  final String? restrictions;

  Map<String, Object?> toJson() => {
    'budget': budget,
    'occasion': ?occasion,
    'recipient': ?recipient,
    'category_id': ?categoryId,
    if (restrictions != null && restrictions!.trim().isNotEmpty)
      'restrictions': restrictions!.trim(),
  };
}

/// Gift-finder choices (kept in sync with `GiftFinderService`).
const giftOccasions = [
  'birthday',
  'mothers_day',
  'valentine',
  'wedding',
  'eid_fitr',
  'eid_adha',
  'ramadan',
  'graduation',
  'thanks',
  'self',
];
const giftRecipients = [
  'mother',
  'partner',
  'friend',
  'sister',
  'colleague',
  'child',
  'self',
  'other',
];
