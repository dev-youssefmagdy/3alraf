import 'key_value_store.dart';

/// Per-tenant secrets (auth token, affiliate token, guest cart token).
/// Every key is namespaced with the tenant slug so nothing leaks between
/// countries/stores.
class SessionStorage {
  const SessionStorage(this._store);

  final KeyValueStore _store;

  static String _key(String kind, String tenant) => '$kind:$tenant';

  Future<String?> authToken(String tenant) =>
      _store.read(_key('auth_token', tenant));
  Future<void> saveAuthToken(String tenant, String token) =>
      _store.write(_key('auth_token', tenant), token);
  Future<void> clearAuthToken(String tenant) =>
      _store.delete(_key('auth_token', tenant));

  Future<String?> affiliateToken(String tenant) =>
      _store.read(_key('affiliate_token', tenant));
  Future<void> saveAffiliateToken(String tenant, String token) =>
      _store.write(_key('affiliate_token', tenant), token);
  Future<void> clearAffiliateToken(String tenant) =>
      _store.delete(_key('affiliate_token', tenant));

  Future<String?> cartToken(String tenant) =>
      _store.read(_key('cart_token', tenant));
  Future<void> saveCartToken(String tenant, String token) =>
      _store.write(_key('cart_token', tenant), token);
  Future<void> clearCartToken(String tenant) =>
      _store.delete(_key('cart_token', tenant));

  /// Removes every secret of one tenant (logout / switch country).
  Future<void> clearTenant(String tenant) async {
    await clearAuthToken(tenant);
    await clearAffiliateToken(tenant);
    await clearCartToken(tenant);
  }
}
