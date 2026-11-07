import 'package:shared_preferences/shared_preferences.dart';

/// Non-secret settings (locale, cached store info, onboarding, theme).
class AppPreferences {
  const AppPreferences(this._prefs);

  final SharedPreferences _prefs;

  static const localeKey = 'locale';
  static const storeInfoKey = 'store_info';
  static const storeProfileKey = 'store_profile';
  static const recentSearchesKey = 'recent_searches';
  static const onboardingKey = 'onboarding_done';
  static const themeKey = 'theme_mode';
  static const migratedKey = 'storage_single_store_v1';

  /// UI language the user picked; `null` until they chose one.
  String? get locale => _prefs.getString(localeKey);
  Future<void> setLocale(String code) => _prefs.setString(localeKey, code);

  /// Last good `GET /app-config` `store` block (offline-tolerant startup).
  String? get storeInfoCache => _prefs.getString(storeInfoKey);
  Future<void> setStoreInfoCache(String json) =>
      _prefs.setString(storeInfoKey, json);

  /// Last good `GET /store` profile.
  String? get storeProfileCache => _prefs.getString(storeProfileKey);
  Future<void> setStoreProfileCache(String json) =>
      _prefs.setString(storeProfileKey, json);

  /// Recent search terms (newest first).
  List<String> get recentSearches =>
      _prefs.getStringList(recentSearchesKey) ?? const [];
  Future<void> setRecentSearches(List<String> terms) => terms.isEmpty
      ? _prefs.remove(recentSearchesKey)
      : _prefs.setStringList(recentSearchesKey, terms);

  bool get onboardingDone => _prefs.getBool(onboardingKey) ?? false;
  Future<void> setOnboardingDone() => _prefs.setBool(onboardingKey, true);

  String get themeMode => _prefs.getString(themeKey) ?? 'light';
  Future<void> setThemeMode(String mode) => _prefs.setString(themeKey, mode);

  // --- one-time storage migration (see legacy_storage_migration.dart) ---

  bool get storageMigrated => _prefs.getBool(migratedKey) ?? false;
  Future<void> setStorageMigrated() => _prefs.setBool(migratedKey, true);

  /// Reads and removes a string key.
  Future<String?> takeString(String key) async {
    final v = _prefs.getString(key);
    if (v != null) await _prefs.remove(key);
    return v;
  }

  /// Moves `locale:<ns>`, `store_cache:<ns>` and `recent_searches:<ns>` of an
  /// older build to the plain keys (values already stored win).
  Future<void> adoptNamespace(String namespace) async {
    final locale = await takeString('locale:$namespace');
    if (locale != null && this.locale == null) await setLocale(locale);
    final profile = await takeString('store_cache:$namespace');
    if (profile != null && storeProfileCache == null) {
      await setStoreProfileCache(profile);
    }
    final searchesKey = 'recent_searches:$namespace';
    final searches = _prefs.getStringList(searchesKey);
    if (searches != null) {
      if (recentSearches.isEmpty) await setRecentSearches(searches);
      await _prefs.remove(searchesKey);
    }
  }
}
