import 'package:shared_preferences/shared_preferences.dart';

/// Non-secret settings (selected tenant, locale, onboarding, theme).
class AppPreferences {
  const AppPreferences(this._prefs);

  final SharedPreferences _prefs;

  static const _tenantKey = 'tenant_slug';
  static const _onboardingKey = 'onboarding_done';
  static const _themeKey = 'theme_mode';

  String? get tenantSlug => _prefs.getString(_tenantKey);
  Future<void> setTenantSlug(String? slug) => slug == null
      ? _prefs.remove(_tenantKey)
      : _prefs.setString(_tenantKey, slug);

  /// Locale is stored per tenant: `locale:<slug>`.
  String? locale(String tenant) => _prefs.getString('locale:$tenant');
  Future<void> setLocale(String tenant, String code) =>
      _prefs.setString('locale:$tenant', code);

  Future<void> removeLocale(String tenant) => _prefs.remove('locale:$tenant');

  /// Last good `GET /store` payload per tenant (offline-tolerant startup).
  String? storeCache(String tenant) => _prefs.getString('store_cache:$tenant');
  Future<void> setStoreCache(String tenant, String json) =>
      _prefs.setString('store_cache:$tenant', json);
  Future<void> removeStoreCache(String tenant) =>
      _prefs.remove('store_cache:$tenant');

  /// Recent search terms per tenant (newest first).
  List<String> recentSearches(String tenant) =>
      _prefs.getStringList('recent_searches:$tenant') ?? const [];
  Future<void> setRecentSearches(String tenant, List<String> terms) =>
      terms.isEmpty
      ? _prefs.remove('recent_searches:$tenant')
      : _prefs.setStringList('recent_searches:$tenant', terms);

  bool get onboardingDone => _prefs.getBool(_onboardingKey) ?? false;
  Future<void> setOnboardingDone() => _prefs.setBool(_onboardingKey, true);

  String get themeMode => _prefs.getString(_themeKey) ?? 'light';
  Future<void> setThemeMode(String mode) => _prefs.setString(_themeKey, mode);
}
