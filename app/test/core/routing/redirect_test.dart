import 'package:app/core/routing/app_router.dart';
import 'package:flutter_test/flutter_test.dart';

String? r(
  String loc, {
  bool ready = true,
  bool tenant = true,
  bool signedIn = false,
}) => computeRedirect(
  uri: Uri.parse(loc),
  startupReady: ready,
  hasTenant: tenant,
  signedIn: signedIn,
);

void main() {
  test('splash always renders', () {
    expect(r('/', ready: false, tenant: false), isNull);
  });

  test('anything else bounces to splash until startup is ready', () {
    expect(r('/home', ready: false), '/');
    expect(r('/select-country', ready: false), '/');
  });

  test('no tenant -> country selection, pre-tenant routes stay', () {
    expect(r('/home', tenant: false), '/select-country');
    expect(r('/login', tenant: false), '/select-country');
    expect(r('/select-country', tenant: false), isNull);
    expect(r('/onboarding', tenant: false), isNull);
    expect(r('/maintenance', tenant: false), isNull);
  });

  test('protected routes need a session and remember where to return', () {
    expect(
      r('/orders?x=1'),
      '/login?from=${Uri.encodeQueryComponent('/orders?x=1')}',
    );
    expect(r('/orders', signedIn: true), isNull);
    expect(r('/verify-email'), startsWith('/login?from='));
    expect(r('/wishlist'), startsWith('/login?from='));
    expect(r('/wishlist', signedIn: true), isNull);
    for (final p in [
      '/checkout',
      '/orders',
      '/account/addresses',
      '/account/addresses/edit',
      '/account/profile',
      '/account/password',
      '/account/delete',
    ]) {
      expect(r(p), startsWith('/login?from='), reason: p);
      expect(r(p, signedIn: true), isNull, reason: p);
    }
  });

  test('public order tracking by token is open to guests', () {
    for (final p in [
      '/orders/2e81f703-717c-4d21-9762-f47f823bff9f',
      '/orders/abc/confirmation',
      '/orders/abc/pay',
    ]) {
      expect(r(p), isNull, reason: p);
    }
    // still needs a tenant and a finished startup
    expect(r('/orders/abc', tenant: false), '/select-country');
    expect(r('/orders/abc', ready: false), '/');
  });

  test('content and affiliate routes are open to guests', () {
    for (final p in [
      '/about',
      '/contact',
      '/blog',
      '/blog/hello',
      '/page/terms',
      '/launch/abcd1234',
      '/affiliate',
      '/affiliate/products',
    ]) {
      expect(r(p), isNull, reason: p);
    }
  });

  test('public tabs are open to guests', () {
    for (final p in ['/home', '/cart', '/account', '/login']) {
      expect(r(p), isNull, reason: p);
    }
  });
}
