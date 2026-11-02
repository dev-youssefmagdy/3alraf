import 'dart:convert';

/// Typed JSON accessors so no `dynamic` leaks out of the data layer.
Map<String, Object?> _map(Object? v) =>
    v is Map ? v.map((k, v) => MapEntry('$k', v)) : const {};
List<Object?> _list(Object? v) => v is List ? v : const [];
String _str(Object? v, [String fallback = '']) => v is String ? v : fallback;
String? _strOrNull(Object? v) => v is String && v.isNotEmpty ? v : null;
bool _bool(Object? v) => v is bool && v;

/// One active store (= country) from `GET /tenants`.
class Tenant {
  const Tenant({
    required this.slug,
    required this.name,
    required this.country,
    required this.currency,
    required this.defaultLocale,
    required this.locales,
    this.flag = '',
    this.languages = const [],
    this.logoUrl,
    this.baseUrl,
  });

  factory Tenant.fromJson(Map<String, Object?> j) => Tenant(
    slug: _str(j['slug']),
    name: _str(j['name']),
    country: _str(j['country']),
    flag: _str(j['flag']),
    currency: _str(j['currency']),
    defaultLocale: _str(j['default_locale'], 'ar'),
    locales: _list(j['locales']).whereType<String>().toList(),
    languages: _list(j['languages']).whereType<String>().toList(),
    logoUrl: _strOrNull(j['logo_url']),
    baseUrl: _strOrNull(j['base_url']),
  );

  final String slug;
  final String name;
  final String country;
  final String flag;
  final String currency;
  final String defaultLocale;
  final List<String> locales;
  final List<String> languages;
  final String? logoUrl;

  /// Public web address of the store (used for share links).
  final String? baseUrl;

  Map<String, Object?> toJson() => {
    'slug': slug,
    'name': name,
    'country': country,
    'flag': flag,
    'currency': currency,
    'default_locale': defaultLocale,
    'locales': locales,
    'languages': languages,
    'logo_url': logoUrl,
    'base_url': baseUrl,
  };

  @override
  bool operator ==(Object other) =>
      other is Tenant && other.slug == slug && other.name == name;

  @override
  int get hashCode => Object.hash(slug, name);
}

/// `GET /app-config` (central).
class AppConfigInfo {
  const AppConfigInfo({
    this.minVersion = '0.0.0',
    this.latestVersion = '0.0.0',
    this.iosUrl,
    this.androidUrl,
    this.maintenance = false,
    this.maintenanceMessage,
    this.updateRequired = false,
    this.updateAvailable = false,
  });

  factory AppConfigInfo.fromJson(Map<String, Object?> j) {
    final urls = _map(j['store_urls']);
    return AppConfigInfo(
      minVersion: _str(j['min_version'], '0.0.0'),
      latestVersion: _str(j['latest_version'], '0.0.0'),
      iosUrl: _strOrNull(urls['ios']),
      androidUrl: _strOrNull(urls['android']),
      maintenance: _bool(j['maintenance']),
      maintenanceMessage: _strOrNull(j['maintenance_message']),
      updateRequired: _bool(j['update_required']),
      updateAvailable: _bool(j['update_available']),
    );
  }

  final String minVersion;
  final String latestVersion;
  final String? iosUrl;
  final String? androidUrl;
  final bool maintenance;
  final String? maintenanceMessage;
  final bool updateRequired;
  final bool updateAvailable;
}

class StoreLocale {
  const StoreLocale({required this.code, required this.rtl});
  final String code;
  final bool rtl;
}

/// Store profile (`GET /store`): branding, currency, locales, contact...
class StoreInfo {
  const StoreInfo({
    required this.slug,
    required this.name,
    required this.country,
    required this.currencyCode,
    required this.currencySymbol,
    required this.defaultLocale,
    required this.locales,
    this.logoUrl,
    this.faviconUrl,
    this.colors = const {},
    this.whatsapp,
    this.email,
    this.phone,
    this.social = const {},
    this.features = const {},
    this.appLinks = const {},
    this.hasBlog = true,
  });

  factory StoreInfo.fromJson(Map<String, Object?> j) {
    final currency = _map(j['currency']);
    final contact = _map(j['contact']);
    return StoreInfo(
      slug: _str(j['slug']),
      name: _str(j['name']),
      country: _str(j['country']),
      currencyCode: _str(currency['code']),
      currencySymbol: _str(currency['symbol'], _str(currency['code'])),
      defaultLocale: _str(j['default_locale'], 'ar'),
      locales: _list(j['locales'])
          .map(_map)
          .where((m) => m['code'] is String)
          .map((m) => StoreLocale(code: '${m['code']}', rtl: _bool(m['rtl'])))
          .toList(),
      logoUrl: _strOrNull(j['logo_url']),
      faviconUrl: _strOrNull(j['favicon_url']),
      colors: _map(j['colors']).map((k, v) => MapEntry(k, _str(v))),
      whatsapp: _strOrNull(contact['whatsapp']),
      email: _strOrNull(contact['email']),
      phone: _strOrNull(contact['phone']),
      social: _map(j['social']).map((k, v) => MapEntry(k, _str(v))),
      features: _map(j['features']).map((k, v) => MapEntry(k, _bool(v))),
      appLinks: {
        for (final e in _map(j['app_links']).entries)
          if (_strOrNull(e.value) != null) e.key: '${e.value}',
      },
      hasBlog: _map(j['footer']).containsKey('has_blog')
          ? _bool(_map(j['footer'])['has_blog'])
          : true,
    );
  }

  factory StoreInfo.decode(String raw) {
    final decoded = jsonDecode(raw);
    return StoreInfo.fromJson(_map(decoded));
  }

  final String slug;
  final String name;
  final String country;
  final String currencyCode;
  final String currencySymbol;
  final String defaultLocale;
  final List<StoreLocale> locales;
  final String? logoUrl;
  final String? faviconUrl;
  final Map<String, String> colors;
  final String? whatsapp;
  final String? email;
  final String? phone;
  final Map<String, String> social;
  final Map<String, bool> features;

  /// `ios` / `android` store listing URLs (rate-app, share).
  final Map<String, String> appLinks;

  /// Whether the store has published blog posts (drawer entry).
  final bool hasBlog;

  List<String> get localeCodes => locales.map((l) => l.code).toList();

  Map<String, Object?> toJson() => {
    'slug': slug,
    'name': name,
    'country': country,
    'currency': {'code': currencyCode, 'symbol': currencySymbol},
    'default_locale': defaultLocale,
    'locales': [
      for (final l in locales) {'code': l.code, 'rtl': l.rtl},
    ],
    'logo_url': logoUrl,
    'favicon_url': faviconUrl,
    'colors': colors,
    'contact': {'email': email, 'phone': phone, 'whatsapp': whatsapp},
    'social': social,
    'features': features,
    'app_links': appLinks,
    'footer': {'has_blog': hasBlog},
  };

  String encode() => jsonEncode(toJson());
}
