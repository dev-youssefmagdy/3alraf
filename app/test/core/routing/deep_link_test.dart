import 'package:app/core/routing/link_router.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('resolveLink (Phase 6 targets)', () {
    test('order tracking, pages, blog, launch, custom page', () {
      const token = '2e81f703-717c-4d21-9762-f47f823bff9f';
      expect(
        resolveLink('https://egy.3alraf.com/en/order/$token')?.route,
        '/orders/$token',
      );
      expect(resolveLink('/orders/$token')?.route, '/orders/$token');
      expect(
        resolveLink('/orders/$token/confirmation')?.route,
        '/orders/$token/confirmation',
      );
      expect(resolveLink('/ar/page/terms')?.route, '/page/terms');
      expect(resolveLink('/en/blog')?.route, '/blog');
      expect(resolveLink('/en/blog/hello')?.route, '/blog/hello');
      expect(resolveLink('/launch/abcd1234')?.route, '/launch/abcd1234');
      expect(resolveLink('/cart')?.route, '/cart');
      // web catch-all /{slug} = custom landing page
      expect(
        resolveLink('https://egy.3alraf.com/offers')?.route,
        '/custom/offers',
      );
      expect(resolveLink('/orders')?.route, isNull);
    });
  });

  group('rewriteIncomingLocation', () {
    test('strips locale prefixes and maps web-only paths', () {
      expect(
        rewriteIncomingLocation(Uri.parse('/en/product/serum')),
        '/product/serum',
      );
      expect(
        rewriteIncomingLocation(Uri.parse('/ar/category/skin')),
        '/category/skin',
      );
      expect(rewriteIncomingLocation(Uri.parse('/order/abc')), '/orders/abc');
      expect(
        rewriteIncomingLocation(Uri.parse('/search?q=rose')),
        '/search/results?q=rose',
      );
      expect(rewriteIncomingLocation(Uri.parse('/offers')), '/custom/offers');
    });

    test('app routes are left alone (no redirect loops)', () {
      for (final p in [
        '/',
        '/home',
        '/product/serum',
        '/search',
        '/search/results?q=rose',
        '/orders/abc',
        '/custom/offers',
        '/account/addresses',
        '/affiliate/products',
        '/blog/hello',
      ]) {
        expect(rewriteIncomingLocation(Uri.parse(p)), isNull, reason: p);
      }
    });
  });
}
