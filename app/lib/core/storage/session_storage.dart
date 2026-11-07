import 'key_value_store.dart';

/// Session secrets of the store: customer auth token, affiliate token and
/// guest cart token.
class SessionStorage {
  const SessionStorage(this._store);

  final KeyValueStore _store;

  static const authKey = 'auth_token';
  static const affiliateKey = 'affiliate_token';
  static const cartKey = 'cart_token';

  Future<String?> authToken() => _store.read(authKey);
  Future<void> saveAuthToken(String token) => _store.write(authKey, token);
  Future<void> clearAuthToken() => _store.delete(authKey);

  Future<String?> affiliateToken() => _store.read(affiliateKey);
  Future<void> saveAffiliateToken(String token) =>
      _store.write(affiliateKey, token);
  Future<void> clearAffiliateToken() => _store.delete(affiliateKey);

  Future<String?> cartToken() => _store.read(cartKey);
  Future<void> saveCartToken(String token) => _store.write(cartKey, token);
  Future<void> clearCartToken() => _store.delete(cartKey);

  /// Removes every secret (account deletion).
  Future<void> clearAll() async {
    await clearAuthToken();
    await clearAffiliateToken();
    await clearCartToken();
  }

  /// Moves `<kind>:<namespace>` secrets of an older build to the plain keys.
  /// A value already stored under the plain key wins. See
  /// `legacy_storage_migration.dart`.
  Future<void> adoptNamespace(String namespace) async {
    for (final key in const [authKey, affiliateKey, cartKey]) {
      final old = await _store.read('$key:$namespace');
      if (old == null) continue;
      final current = await _store.read(key);
      if ((current == null || current.isEmpty) && old.isNotEmpty) {
        await _store.write(key, old);
      }
      await _store.delete('$key:$namespace');
    }
  }
}
