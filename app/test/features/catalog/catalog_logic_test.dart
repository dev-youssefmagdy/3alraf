import 'package:app/core/routing/link_router.dart';
import 'package:app/features/catalog/data/catalog_models.dart';
import 'package:app/features/catalog/data/product_models.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/catalog_fixtures.dart';

ProductDetail variable() => ProductDetail.fromJson(variableProductJson());

void main() {
  group('VariantPicker', () {
    test('resolves only a complete, existing combination', () {
      final p = variable();
      expect(VariantPicker.resolve(p, {1: 10}), isNull);
      expect(VariantPicker.resolve(p, {1: 10, 2: 20})?.id, 100);
      expect(VariantPicker.resolve(p, {1: 11, 2: 21})?.price, '130.00');
    });

    test('simple product resolves to its single variant', () {
      final p = ProductDetail.fromJson(simpleProductJson());
      expect(p.isVariable, isFalse);
      expect(VariantPicker.resolve(p, const {})?.id, 0);
    });

    test('a value is unavailable when no in-stock variant matches', () {
      final p = variable();
      // With Size=M chosen, Red leads only to the sold-out variant.
      expect(VariantPicker.isAvailable(p, {1: 11}, 2, 20), isFalse);
      expect(VariantPicker.isAvailable(p, {1: 11}, 2, 21), isTrue);
      // Size values are judged against the current colour.
      expect(VariantPicker.isAvailable(p, {2: 20}, 1, 11), isFalse);
      expect(VariantPicker.isAvailable(p, {2: 20}, 1, 10), isTrue);
    });

    test('default selection is the first in-stock variant', () {
      expect(VariantPicker.defaultSelection(variable()), {1: 10, 2: 20});
    });

    test('low stock flag follows the alert threshold', () {
      final v = ProductVariant.fromJson(variantJson(1, [], stock: 3));
      expect(v.isLowStock, isTrue);
      expect(
        ProductVariant.fromJson(variantJson(1, [], stock: 50)).isLowStock,
        isFalse,
      );
      expect(
        ProductVariant.fromJson(variantJson(1, [], stock: null)).isLowStock,
        isFalse,
      );
    });
  });

  group('CatalogQuery', () {
    test('value equality ignores set order and keys families', () {
      const a = CatalogQuery(category: 'x', brandIds: {1, 2});
      const b = CatalogQuery(category: 'x', brandIds: {2, 1});
      expect(a, b);
      expect(a.hashCode, b.hashCode);
      expect(a == a.copyWith(sort: 'price_asc'), isFalse);
    });

    test('params: scope, array filters, price and sort', () {
      const q = CatalogQuery(
        category: 'skin',
        q: 'rose',
        brandIds: {3, 1},
        valueIds: {9},
        priceMin: '10',
        sort: 'price_desc',
      );
      expect(q.toParams(), {
        'category': 'skin',
        'q': 'rose',
        'brands[]': [1, 3],
        'values[]': [9],
        'price_min': '10',
        'sort': 'price_desc',
      });
      expect(q.toParams(forFacets: true).containsKey('sort'), isFalse);
      expect(q.activeFilters, 4);
    });
  });

  group('resolveLink', () {
    test('maps store URLs and paths onto app routes', () {
      expect(
        resolveLink('https://egy.3alraf.com/en/product/serum')?.route,
        '/product/serum',
      );
      expect(resolveLink('/category/skin')?.route, '/category/skin');
      expect(resolveLink('/search?q=rose')?.route, '/search/results?q=rose');
      expect(resolveLink('/bundles')?.route, '/bundles');
    });

    test('other http links open externally, junk is ignored', () {
      expect(resolveLink('https://example.com/a/b/c')?.external, isNotNull);
      expect(resolveLink('#'), isNull);
      expect(resolveLink(null), isNull);
      expect(resolveLink('mailto:a@b.c'), isNull);
    });
  });

  test('HomeData parses rails, banners, brands and null popup', () {
    final h = HomeData.fromJson(homeJson());
    expect(h.hero.single.link, '/product/serum');
    expect(h.recommended.single.discountPercent, 17);
    expect(h.recommended.single.oldPrice, '300.00');
    expect(h.brands.single.slug, 'sony');
    expect(h.popup, isNull);
    expect(h.flashSale, isNull);
  });
}
