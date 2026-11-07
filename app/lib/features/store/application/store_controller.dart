import 'package:flutter/widgets.dart' show TextDirection;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/storage/storage_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../data/store_models.dart';
import '../data/store_repository.dart';

final storeRepositoryProvider = Provider<StoreRepository>(
  (ref) => StoreRepository(ref.watch(dioProvider)),
);

/// The shop: basics from `GET /app-config` (`info`) and the full profile
/// from `GET /store` (`profile`). Both are cached for offline starts.
class StoreState {
  const StoreState({this.info, this.profile});

  final StoreInfo? info;
  final StoreProfile? profile;

  bool get isLoaded => info != null || profile != null;

  String get name {
    final n = info?.name ?? '';
    return n.isNotEmpty ? n : (profile?.name ?? '');
  }

  String? get defaultLocale => info?.defaultLocale ?? profile?.defaultLocale;

  /// Store locales the app has translations for.
  List<String> get availableLocales {
    final codes = info?.localeCodes ?? profile?.localeCodes ?? const [];
    return codes.where(LocaleNotifier.supported.contains).toList();
  }

  /// Currency symbol (falls back to the code).
  String get currencyLabel {
    final s = info?.currencySymbol ?? '';
    if (s.isNotEmpty) return s;
    return profile?.currencySymbol ?? '';
  }

  /// RTL flag of [code] as declared by the store (`null` = unknown).
  bool? isRtl(String code) {
    final fromInfo = info?.isRtl(code);
    if (fromInfo != null) return fromInfo;
    for (final l in profile?.locales ?? const <StoreLocale>[]) {
      if (l.code == code) return l.rtl;
    }
    return null;
  }
}

final storeControllerProvider = NotifierProvider<StoreController, StoreState>(
  StoreController.new,
);

/// Shop basics (`app-config.store`): name, currency, locales, base URL.
final storeInfoProvider = Provider<StoreInfo?>(
  (ref) => ref.watch(storeControllerProvider).info,
);

/// Full shop profile (`GET /store`): contact, features, app links.
final storeProfileProvider = Provider<StoreProfile?>(
  (ref) => ref.watch(storeControllerProvider).profile,
);

/// Text direction of the current locale as declared by the store; `null`
/// lets Flutter derive it from the locale.
final storeTextDirectionProvider = Provider<TextDirection?>((ref) {
  final code = ref.watch(localeProvider).languageCode;
  final rtl = ref.watch(storeControllerProvider).isRtl(code);
  if (rtl == null) return null;
  return rtl ? TextDirection.rtl : TextDirection.ltr;
});

class StoreController extends Notifier<StoreState> {
  @override
  StoreState build() => const StoreState();

  /// Last cached `app-config.store`.
  StoreInfo? cachedInfo() =>
      StoreInfo.tryDecode(ref.read(preferencesProvider).storeInfoCache);

  /// Applies the shop basics: caches a fresh [info] (or falls back to the
  /// cached one when `null`, e.g. offline), loads the cached profile, picks
  /// the locale and restores the customer session.
  Future<void> apply(StoreInfo? info) async {
    final prefs = ref.read(preferencesProvider);
    if (info != null) await prefs.setStoreInfoCache(info.encode());
    final profile =
        state.profile ?? StoreProfile.tryDecode(prefs.storeProfileCache);
    state = StoreState(
      info: info ?? cachedInfo() ?? state.info,
      profile: profile,
    );
    _applyLocale();
    await ref.read(authControllerProvider.notifier).restore();
  }

  /// Refreshes `GET /store`; failures are swallowed (cached data stays).
  /// Returns whether the store could be reached.
  Future<bool> refreshProfile() async {
    try {
      final profile = await ref.read(storeRepositoryProvider).fetchProfile();
      await ref
          .read(preferencesProvider)
          .setStoreProfileCache(profile.encode());
      state = StoreState(info: state.info, profile: profile);
      final current = ref.read(localeProvider).languageCode;
      final allowed = state.availableLocales;
      if (allowed.isNotEmpty && !allowed.contains(current)) _applyLocale();
      return true;
    } on Object {
      return false;
    }
  }

  /// Whether the user still has to pick a language (never chose one and the
  /// store offers more than one).
  bool get needsLanguageChoice =>
      ref.read(preferencesProvider).locale == null &&
      state.availableLocales.length > 1;

  /// User-chosen language, persisted.
  Future<void> chooseLocale(String code) async {
    final allowed = state.availableLocales;
    if (allowed.isNotEmpty && !allowed.contains(code)) return;
    if (!LocaleNotifier.supported.contains(code)) return;
    ref.read(localeProvider.notifier).setLocale(code);
    await ref.read(preferencesProvider).setLocale(code);
  }

  /// Saved choice if the store still offers it, else the store default,
  /// else the first store locale, else Arabic.
  void _applyLocale() {
    final allowed = state.availableLocales;
    final saved = ref.read(preferencesProvider).locale;
    final fallback = state.defaultLocale;
    String pick;
    if (saved != null &&
        LocaleNotifier.supported.contains(saved) &&
        (allowed.isEmpty || allowed.contains(saved))) {
      pick = saved;
    } else if (fallback != null &&
        (allowed.isEmpty || allowed.contains(fallback))) {
      pick = fallback;
    } else if (allowed.isNotEmpty) {
      pick = allowed.first;
    } else {
      pick = 'ar';
    }
    ref.read(localeProvider.notifier).setLocale(pick);
  }
}
