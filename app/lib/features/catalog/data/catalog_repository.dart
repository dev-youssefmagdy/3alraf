import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/json.dart';
import '../../../core/network/interceptors.dart';
import 'catalog_models.dart';
import 'product_models.dart';

/// Read side of the store (home, menu, listings, products, bundles, gift
/// finder) plus the small writes of this phase (wishlist, review,
/// stock request, add-to-cart).
class CatalogRepository {
  const CatalogRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<HomeData> home() => _guard(() async {
    final r = await _dio.get<Object?>('/home');
    return HomeData.fromJson(asMap(r.data));
  });

  Future<List<CategoryNode>> menu() => _guard(() async {
    final r = await _dio.get<Object?>('/menu');
    return asMapList(r.data).map(CategoryNode.fromJson).toList();
  });

  Future<List<Brand>> brands() => _guard(() async {
    final r = await _dio.get<Object?>('/brands');
    return asMapList(r.data).map(Brand.fromJson).toList();
  });

  /// `GET /catalog` page.
  Future<PagedResult<ProductSummary>> catalog(
    CatalogQuery query, {
    int page = 1,
    int perPage = 24,
  }) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/catalog',
      queryParameters: {...query.toParams(), 'page': page, 'per_page': perPage},
    );
    return PagedResult(
      asMapList(r.data).map(ProductSummary.fromJson).toList(),
      PageInfo.fromMeta(r.envelopeMeta),
    );
  });

  /// `GET /custom-pages/{slug}`: title + one page of products.
  Future<({String title, PagedResult<ProductSummary> products})> customPage(
    String slug,
    CatalogQuery query, {
    int page = 1,
  }) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/custom-pages/$slug',
      queryParameters: {
        'price_min': ?query.priceMin,
        'price_max': ?query.priceMax,
        if (query.sort != 'top_rated') 'sort': query.sort,
        'page': page,
      },
    );
    final d = asMap(r.data);
    return (
      title: str(asMap(d['page'])['title']),
      products: PagedResult(
        asMapList(d['products']).map(ProductSummary.fromJson).toList(),
        PageInfo.fromMeta(r.envelopeMeta),
      ),
    );
  });

  Future<Facets> facets(CatalogQuery query) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/catalog/facets',
      queryParameters: query.toParams(forFacets: true),
    );
    return Facets.fromJson(asMap(r.data));
  });

  Future<SuggestResult> suggest(String q) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/catalog/suggest',
      queryParameters: {'q': q},
    );
    return SuggestResult.fromJson(asMap(r.data));
  });

  Future<ScopeInfo> category(String slug) => _guard(() async {
    final d = asMap((await _dio.get<Object?>('/categories/$slug')).data);
    return ScopeInfo(
      title: str(d['name']),
      image: strOrNull(d['image']),
      description: strOrNull(d['description']),
      breadcrumbs: asMapList(
        d['breadcrumbs'],
      ).map((c) => Crumb(name: str(c['name']), slug: str(c['slug']))).toList(),
      children: asMapList(d['children']).map(CategoryNode.fromJson).toList(),
    );
  });

  Future<ScopeInfo> brand(String slug) => _guard(() async {
    final d = asMap((await _dio.get<Object?>('/brands/$slug')).data);
    return ScopeInfo(
      title: str(d['name']),
      image: strOrNull(d['logo']),
      productsCount: intOrNull(d['products_count']),
    );
  });

  Future<ScopeInfo> tag(String slug) => _guard(() async {
    final d = asMap((await _dio.get<Object?>('/tags/$slug')).data);
    return ScopeInfo(title: str(d['name']));
  });

  Future<ProductDetail> product(String slug) => _guard(() async {
    final r = await _dio.get<Object?>('/products/$slug');
    return ProductDetail.fromJson(asMap(r.data));
  });

  Future<PagedResult<Review>> reviews(String slug, {int page = 1}) =>
      _guard(() async {
        final r = await _dio.get<Object?>(
          '/products/$slug/reviews',
          queryParameters: {'page': page},
        );
        return PagedResult(
          asMapList(r.data).map(Review.fromJson).toList(),
          PageInfo.fromMeta(r.envelopeMeta),
        );
      });

  /// Returns the server's moderation message.
  Future<String?> addReview(String slug, int rating, String? body) =>
      _guard(() async {
        final r = await _dio.post<Object?>(
          '/products/$slug/reviews',
          data: {
            'rating': rating,
            if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
          },
        );
        return r.envelopeMessage;
      });

  Future<String?> requestStock({
    required int productId,
    required int variantId,
    required String name,
    required String phone,
    String? note,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/stock-requests',
      data: {
        'product_id': productId,
        if (variantId != 0) 'variant_id': variantId,
        'name': name,
        'phone': phone,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      },
    );
    return r.envelopeMessage;
  });

  Future<List<Bundle>> bundles() => _guard(() async {
    final r = await _dio.get<Object?>('/bundles');
    return asMapList(r.data).map(Bundle.fromJson).toList();
  });

  Future<Bundle> bundle(String slug) => _guard(() async {
    final r = await _dio.get<Object?>('/bundles/$slug');
    return Bundle.fromJson(asMap(r.data));
  });

  Future<GiftResults> giftSearch(GiftAnswers a) => _guard(() async {
    final r = await _dio.post<Object?>('/gift-finder', data: a.toJson());
    return GiftResults.fromJson(asMap(r.data));
  });

  /// Adds the chosen suggestions to the cart; returns the number added.
  Future<int> giftAddToCart(
    String token,
    List<int> productIds,
    Map<int, int> variants,
  ) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/gift-finder/$token/cart',
      data: {
        'product_ids': productIds,
        'variants': {for (final e in variants.entries) '${e.key}': e.value},
      },
    );
    return asList(asMap(r.data)['added']).length;
  });

  // -- wishlist (customer token) ------------------------------------------

  Future<List<ProductSummary>> wishlist() => _guard(() async {
    final r = await _dio.get<Object?>('/wishlist');
    return asMapList(r.data).map(ProductSummary.fromJson).toList();
  });

  /// Returns the new state (`true` = now in the wishlist).
  Future<bool> toggleWishlist(int productId) => _guard(() async {
    final r = await _dio.post<Object?>('/wishlist/$productId/toggle');
    return boolOf(asMap(r.data)['in_wishlist']);
  });
}
