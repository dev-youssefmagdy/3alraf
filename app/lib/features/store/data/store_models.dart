import 'dart:convert';

/// Typed JSON accessors so no `dynamic` leaks out of the data layer.
Map<String, Object?> _map(Object? v) =>
    v is Map ? v.map((k, v) => MapEntry('$k', v)) : const {};
List<Object?> _list(Object? v) => v is List ? v : const [];
String _str(Object? v, [String fallback = '']) => v is String ? v : fallback;
String? _strOrNull(Object? v) => v is String && v.isNotEmpty ? v : null;
bool _bool(Object? v) => v is bool && v;

/// `GET /app-config`: force update / maintenance switches + [store] basics.
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
    this.store,
  });

  factory AppConfigInfo.fromJson(Map<String, Object?> j) {
    final urls = _map(j['store_urls']);
    final store = _map(j['store']);
    return AppConfigInfo(
      minVersion: _str(j['min_version'], '0.0.0'),
      latestVersion: _str(j['latest_version'], '0.0.0'),
      iosUrl: _strOrNull(urls['ios']),
      androidUrl: _strOrNull(urls['android']),
      maintenance: _bool(j['maintenance']),
      maintenanceMessage: _strOrNull(j['maintenance_message']),
      updateRequired: _bool(j['update_required']),
      updateAvailable: _bool(j['update_available']),
      store: store.isEmpty ? null : StoreInfo.fromJson(store),
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

  /// Shop basics; `null` when an older server did not send them.
  final StoreInfo? store;
}

class StoreLocale {
  const StoreLocale({required this.code, required this.rtl});
  final String code;
  final bool rtl;
}

List<StoreLocale> _locales(Object? v) => _list(v)
    .map(_map)
    .where((m) => m['code'] is String)
    .map((m) => StoreLocale(code: '${m['code']}', rtl: _bool(m['rtl'])))
    .toList();

List<Map<String, Object?>> _localesJson(List<StoreLocale> l) => [
  for (final x in l) {'code': x.code, 'rtl': x.rtl},
];

/// Shop basics from `GET /app-config` `store`: everything needed before the
/// first catalog call (name, country, currency, locales + RTL, timezone,
/// public web address). Cached for offline starts.
class StoreInfo {
  const StoreInfo({
    required this.name,
    required this.currencyCode,
    required this.currencySymbol,
    required this.defaultLocale,
    required this.locales,
    this.country,
    this.timezone,
    this.baseUrl,
  });

  factory StoreInfo.fromJson(Map<String, Object?> j) {
    final currency = _map(j['currency']);
    return StoreInfo(
      name: _str(j['name']),
      country: _strOrNull(j['country']),
      currencyCode: _str(currency['code']),
      currencySymbol: _strOrNull(currency['symbol']) ?? _str(currency['code']),
      defaultLocale: _strOrNull(j['default_locale']) ?? 'ar',
      locales: _locales(j['locales']),
      timezone: _strOrNull(j['timezone']),
      baseUrl: _strOrNull(j['base_url']),
    );
  }

  /// `null` when [raw] is not a valid cached payload.
  static StoreInfo? tryDecode(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      final m = _map(jsonDecode(raw));
      return m.isEmpty ? null : StoreInfo.fromJson(m);
    } on FormatException {
      return null;
    }
  }

  final String name;
  final String? country;
  final String currencyCode;
  final String currencySymbol;
  final String defaultLocale;
  final List<StoreLocale> locales;
  final String? timezone;

  /// Public web address of the store (share links).
  final String? baseUrl;

  List<String> get localeCodes => locales.map((l) => l.code).toList();

  /// RTL flag of [code] from the store, `null` when the store does not list it.
  bool? isRtl(String code) {
    for (final l in locales) {
      if (l.code == code) return l.rtl;
    }
    return null;
  }

  /// Web URL of a product page (`{base_url}/{locale}/product/{slug}`).
  String? productUrl(String slug, String locale) {
    final base = baseUrl;
    if (base == null) return null;
    final root = base.endsWith('/') ? base.substring(0, base.length - 1) : base;
    return '$root/$locale/product/$slug';
  }

  Map<String, Object?> toJson() => {
    'name': name,
    'country': country,
    'currency': {'code': currencyCode, 'symbol': currencySymbol},
    'default_locale': defaultLocale,
    'locales': _localesJson(locales),
    'timezone': timezone,
    'base_url': baseUrl,
  };

  String encode() => jsonEncode(toJson());
}

/// Full store profile (`GET /store`): branding, contact, social, features,
/// app links.
class StoreProfile {
  const StoreProfile({
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

  factory StoreProfile.fromJson(Map<String, Object?> j) {
    final currency = _map(j['currency']);
    final contact = _map(j['contact']);
    return StoreProfile(
      name: _str(j['name']),
      country: _str(j['country']),
      currencyCode: _str(currency['code']),
      currencySymbol: _str(currency['symbol'], _str(currency['code'])),
      defaultLocale: _str(j['default_locale'], 'ar'),
      locales: _locales(j['locales']),
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

  /// `null` when [raw] is not a valid cached payload.
  static StoreProfile? tryDecode(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      final m = _map(jsonDecode(raw));
      return m.isEmpty ? null : StoreProfile.fromJson(m);
    } on FormatException {
      return null;
    }
  }

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
    'name': name,
    'country': country,
    'currency': {'code': currencyCode, 'symbol': currencySymbol},
    'default_locale': defaultLocale,
    'locales': _localesJson(locales),
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
