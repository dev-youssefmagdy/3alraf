import '../../../core/network/json.dart';

/// Pagination block of `meta.pagination`.
class PageInfo {
  const PageInfo({this.currentPage = 1, this.lastPage = 1, this.total = 0});

  factory PageInfo.fromMeta(Map<String, Object?> meta) {
    final p = asMap(meta['pagination']);
    return PageInfo(
      currentPage: intOf(p['current_page'], 1),
      lastPage: intOf(p['last_page'], 1),
      total: intOf(p['total']),
    );
  }

  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}

class PagedResult<T> {
  const PagedResult(this.items, this.page);
  final List<T> items;
  final PageInfo page;
}

/// Product summary card (home rails, listings, wishlist, related).
class ProductSummary {
  const ProductSummary({
    required this.id,
    required this.slug,
    required this.name,
    required this.price,
    this.image,
    this.oldPrice,
    this.discountPercent,
    this.flash = false,
    this.flashEndsAt,
    this.rating,
    this.reviewsCount = 0,
  });

  factory ProductSummary.fromJson(Map<String, Object?> j) => ProductSummary(
    id: intOf(j['id']),
    slug: str(j['slug']),
    name: str(j['name']),
    price: str(j['price']),
    image: strOrNull(j['image']),
    oldPrice: strOrNull(j['old_price']),
    discountPercent: intOrNull(j['discount_percent']),
    flash: boolOf(j['flash']),
    flashEndsAt: dateOrNull(j['flash_ends_at']),
    rating: doubleOrNull(j['rating']),
    reviewsCount: intOf(j['reviews_count']),
  );

  final int id;
  final String slug;
  final String name;
  final String price;
  final String? image;
  final String? oldPrice;
  final int? discountPercent;
  final bool flash;
  final DateTime? flashEndsAt;
  final double? rating;
  final int reviewsCount;
}

class PromoBanner {
  const PromoBanner({
    required this.id,
    required this.title,
    this.image,
    this.link,
  });

  factory PromoBanner.fromJson(Map<String, Object?> j) => PromoBanner(
    id: intOf(j['id']),
    title: str(j['title']),
    image: strOrNull(j['image']),
    link: strOrNull(j['link']),
  );

  final int id;
  final String title;
  final String? image;
  final String? link;
}

class CategoryNode {
  const CategoryNode({
    required this.id,
    required this.name,
    required this.slug,
    this.image,
    this.icon,
    this.children = const [],
  });

  factory CategoryNode.fromJson(Map<String, Object?> j) => CategoryNode(
    id: intOf(j['id']),
    name: str(j['name']),
    slug: str(j['slug']),
    image: strOrNull(j['image']),
    icon: strOrNull(j['icon']),
    children: asMapList(j['children']).map(CategoryNode.fromJson).toList(),
  );

  final int id;
  final String name;
  final String slug;
  final String? image;
  final String? icon;
  final List<CategoryNode> children;
}

class Brand {
  const Brand({
    required this.id,
    required this.name,
    required this.slug,
    this.logo,
    this.productsCount,
  });

  factory Brand.fromJson(Map<String, Object?> j) => Brand(
    id: intOf(j['id']),
    name: str(j['name']),
    slug: str(j['slug']),
    logo: strOrNull(j['logo']),
    productsCount: intOrNull(j['products_count']),
  );

  final int id;
  final String name;
  final String slug;
  final String? logo;
  final int? productsCount;
}

class FlashSale {
  const FlashSale({
    required this.id,
    required this.name,
    required this.endsAt,
    required this.products,
  });

  final int id;
  final String name;
  final DateTime endsAt;
  final List<ProductSummary> products;
}

class HomeData {
  const HomeData({
    this.hero = const [],
    this.strips = const [],
    this.tiles = const [],
    this.popup,
    this.categories = const [],
    this.flashSale,
    this.recommended = const [],
    this.mostSold = const [],
    this.mostReviewed = const [],
    this.brands = const [],
  });

  factory HomeData.fromJson(Map<String, Object?> j) {
    final banners = asMap(j['banners']);
    List<PromoBanner> bl(Object? v) =>
        asMapList(v).map(PromoBanner.fromJson).toList();
    List<ProductSummary> pl(Object? v) =>
        asMapList(v).map(ProductSummary.fromJson).toList();
    final rails = asMap(j['rails']);
    final flash = asMap(j['flash_sale']);
    final endsAt = dateOrNull(flash['ends_at']);
    final popup = asMap(banners['popup']);
    return HomeData(
      hero: bl(banners['hero']),
      strips: bl(banners['strips']),
      tiles: bl(banners['tiles']),
      popup: popup.isEmpty ? null : PromoBanner.fromJson(popup),
      categories: asMapList(
        j['categories'],
      ).map(CategoryNode.fromJson).toList(),
      flashSale: endsAt == null
          ? null
          : FlashSale(
              id: intOf(flash['id']),
              name: str(flash['name']),
              endsAt: endsAt,
              products: pl(flash['products']),
            ),
      recommended: pl(rails['recommended']),
      mostSold: pl(rails['most_sold']),
      mostReviewed: pl(rails['most_reviewed']),
      brands: asMapList(j['brands']).map(Brand.fromJson).toList(),
    );
  }

  final List<PromoBanner> hero;
  final List<PromoBanner> strips;
  final List<PromoBanner> tiles;
  final PromoBanner? popup;
  final List<CategoryNode> categories;
  final FlashSale? flashSale;
  final List<ProductSummary> recommended;
  final List<ProductSummary> mostSold;
  final List<ProductSummary> mostReviewed;
  final List<Brand> brands;
}

/// Listing sort keys accepted by `GET /catalog`.
const catalogSorts = [
  'newest',
  'price_asc',
  'price_desc',
  'best_selling',
  'top_rated',
];

/// What a listing shows: scope (category / brand / tag / search) + filters.
/// Immutable with value equality so it can key a Riverpod family.
class CatalogQuery {
  const CatalogQuery({
    this.category,
    this.brand,
    this.tag,
    this.q,
    this.brandIds = const {},
    this.valueIds = const {},
    this.priceMin,
    this.priceMax,
    this.sort = 'newest',
  });

  final String? category;
  final String? brand;
  final String? tag;
  final String? q;
  final Set<int> brandIds;
  final Set<int> valueIds;
  final String? priceMin;
  final String? priceMax;
  final String sort;

  /// Number of active user filters (the sheet badge); scope and sort excluded.
  int get activeFilters =>
      brandIds.length +
      valueIds.length +
      (priceMin != null ? 1 : 0) +
      (priceMax != null ? 1 : 0);

  CatalogQuery copyWith({
    Set<int>? brandIds,
    Set<int>? valueIds,
    String? priceMin,
    String? priceMax,
    bool clearPrice = false,
    String? sort,
  }) => CatalogQuery(
    category: category,
    brand: brand,
    tag: tag,
    q: q,
    brandIds: brandIds ?? this.brandIds,
    valueIds: valueIds ?? this.valueIds,
    priceMin: clearPrice ? null : (priceMin ?? this.priceMin),
    priceMax: clearPrice ? null : (priceMax ?? this.priceMax),
    sort: sort ?? this.sort,
  );

  /// Query parameters for `catalog`, `catalog/facets` (no paging/sort for
  /// facets) and `custom-pages/{slug}` (price + sort only).
  Map<String, Object?> toParams({bool forFacets = false}) => {
    'category': ?category,
    'brand': ?brand,
    'tag': ?tag,
    'q': ?q,
    if (brandIds.isNotEmpty) 'brands[]': (brandIds.toList()..sort()),
    if (valueIds.isNotEmpty) 'values[]': (valueIds.toList()..sort()),
    'price_min': ?priceMin,
    'price_max': ?priceMax,
    if (!forFacets) 'sort': sort,
  };

  @override
  bool operator ==(Object other) =>
      other is CatalogQuery &&
      other.category == category &&
      other.brand == brand &&
      other.tag == tag &&
      other.q == q &&
      other.priceMin == priceMin &&
      other.priceMax == priceMax &&
      other.sort == sort &&
      other.brandIds.length == brandIds.length &&
      other.brandIds.containsAll(brandIds) &&
      other.valueIds.length == valueIds.length &&
      other.valueIds.containsAll(valueIds);

  @override
  int get hashCode => Object.hash(
    category,
    brand,
    tag,
    q,
    priceMin,
    priceMax,
    sort,
    Object.hashAll(brandIds.toList()..sort()),
    Object.hashAll(valueIds.toList()..sort()),
  );
}

class FacetValue {
  const FacetValue({required this.id, required this.label, this.color});
  final int id;
  final String label;
  final String? color;
}

class FacetAttribute {
  const FacetAttribute({
    required this.id,
    required this.name,
    required this.values,
  });
  final int id;
  final String name;
  final List<FacetValue> values;
}

class Facets {
  const Facets({
    this.brands = const [],
    this.attributes = const [],
    this.priceMin,
    this.priceMax,
  });

  factory Facets.fromJson(Map<String, Object?> j) {
    final price = asMap(j['price']);
    return Facets(
      brands: asMapList(j['brands']).map(Brand.fromJson).toList(),
      attributes: asMapList(j['attributes'])
          .map(
            (a) => FacetAttribute(
              id: intOf(a['id']),
              name: str(a['name']),
              values: asMapList(a['values'])
                  .map(
                    (v) => FacetValue(
                      id: intOf(v['id']),
                      label: str(v['label']),
                      color: strOrNull(v['color']),
                    ),
                  )
                  .toList(),
            ),
          )
          .toList(),
      priceMin: strOrNull(price['min']),
      priceMax: strOrNull(price['max']),
    );
  }

  final List<Brand> brands;
  final List<FacetAttribute> attributes;
  final String? priceMin;
  final String? priceMax;
}

class Crumb {
  const Crumb({required this.name, required this.slug});
  final String name;
  final String slug;
}

/// Header of a category / brand / tag / custom page listing.
class ScopeInfo {
  const ScopeInfo({
    required this.title,
    this.image,
    this.description,
    this.breadcrumbs = const [],
    this.children = const [],
    this.productsCount,
  });

  final String title;
  final String? image;
  final String? description;
  final List<Crumb> breadcrumbs;
  final List<CategoryNode> children;
  final int? productsCount;
}

class SuggestResult {
  const SuggestResult({this.products = const [], this.categories = const []});

  factory SuggestResult.fromJson(Map<String, Object?> j) => SuggestResult(
    products: asMapList(j['products']).map(SuggestProduct.fromJson).toList(),
    categories: asMapList(
      j['categories'],
    ).map((c) => Crumb(name: str(c['name']), slug: str(c['slug']))).toList(),
  );

  final List<SuggestProduct> products;
  final List<Crumb> categories;

  bool get isEmpty => products.isEmpty && categories.isEmpty;
}

class SuggestProduct {
  const SuggestProduct({
    required this.name,
    required this.slug,
    required this.price,
    this.image,
  });

  factory SuggestProduct.fromJson(Map<String, Object?> j) => SuggestProduct(
    name: str(j['name']),
    slug: str(j['slug']),
    price: str(j['price']),
    image: strOrNull(j['image']),
  );

  final String name;
  final String slug;
  final String price;
  final String? image;
}
