import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/storage/storage_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../data/tenant_models.dart';
import '../data/tenant_repository.dart';

final tenantRepositoryProvider = Provider<TenantRepository>(
  (ref) => TenantRepository(ref.watch(dioProvider)),
);

/// The tenant (store/country) the app is currently bound to.
class TenantState {
  const TenantState({this.slug, this.store, this.tenant});

  final String? slug;
  final StoreInfo? store;

  /// Directory entry, when known (not available in offline startup).
  final Tenant? tenant;

  bool get hasTenant => slug != null;

  /// Locales the user may choose between (tenant locales that the app has
  /// translations for).
  List<String> get availableLocales {
    final codes = store?.localeCodes ?? tenant?.locales ?? const <String>[];
    return codes.where(LocaleNotifier.supported.contains).toList();
  }
}

final tenantControllerProvider =
    NotifierProvider<TenantController, TenantState>(TenantController.new);

/// Convenience selectors.
final tenantSlugProvider = Provider<String?>(
  (ref) => ref.watch(tenantControllerProvider).slug,
);
final storeInfoProvider = Provider<StoreInfo?>(
  (ref) => ref.watch(tenantControllerProvider).store,
);

class TenantController extends Notifier<TenantState> {
  @override
  TenantState build() => const TenantState();

  /// Binds the app to [slug]: API context, locale, cached store profile.
  /// Does not touch the network.
  Future<void> activate(String slug, {Tenant? tenant}) async {
    final prefs = ref.read(preferencesProvider);
    ref.read(apiContextProvider).tenantSlug = slug;
    final raw = prefs.storeCache(slug);
    StoreInfo? store;
    if (raw != null) {
      try {
        store = StoreInfo.decode(raw);
      } on Object {
        store = null;
      }
    }
    if (prefs.tenantSlug != slug) await prefs.setTenantSlug(slug);
    state = TenantState(slug: slug, store: store, tenant: tenant);
    _applyLocale(
      slug,
      state.availableLocales,
      tenant?.defaultLocale ?? store?.defaultLocale,
    );
    await ref.read(authControllerProvider.notifier).restore();
  }

  /// First selection (no previous tenant to leave).
  Future<void> select(Tenant tenant) => activate(tenant.slug, tenant: tenant);

  /// Full switch procedure (PLAN 3.1 step 3): revoke the old token, wipe the
  /// old tenant's secrets/caches, bind the new one.
  Future<void> switchTo(Tenant tenant) async {
    final old = state.slug;
    if (old == tenant.slug) return;
    if (old != null) {
      await _leave(old);
    }
    await activate(tenant.slug, tenant: tenant);
    // Fresh profile for the new store (best effort).
    await refreshStore();
  }

  /// Forgets the saved tenant (it is no longer active).
  Future<void> clear() async {
    final old = state.slug ?? ref.read(preferencesProvider).tenantSlug;
    if (old != null) await _leave(old, remote: false);
    await ref.read(preferencesProvider).setTenantSlug(null);
    ref.read(apiContextProvider).tenantSlug = null;
    state = const TenantState();
  }

  Future<void> _leave(String slug, {bool remote = true}) async {
    final storage = ref.read(sessionStorageProvider);
    if (remote && ref.read(apiContextProvider).tenantSlug == slug) {
      final token = await storage.authToken(slug);
      if (token != null) {
        await ref.read(tenantRepositoryProvider).logoutRemote();
      }
    }
    await storage.clearTenant(slug);
    await ref.read(preferencesProvider).removeStoreCache(slug);
    ref.read(authControllerProvider.notifier).reset();
  }

  /// Refreshes `GET /store`; failures are swallowed (cached data stays).
  /// Returns whether the store could be reached.
  Future<bool> refreshStore() async {
    final slug = state.slug;
    if (slug == null) return false;
    try {
      final store = await ref.read(tenantRepositoryProvider).fetchStore();
      if (ref.read(apiContextProvider).tenantSlug != slug) return false;
      await ref.read(preferencesProvider).setStoreCache(slug, store.encode());
      state = TenantState(slug: slug, store: store, tenant: state.tenant);
      final current = ref.read(localeProvider).languageCode;
      if (!state.availableLocales.contains(current)) {
        _applyLocale(slug, state.availableLocales, store.defaultLocale);
      }
      return true;
    } on Object {
      return false;
    }
  }

  /// User-chosen language, persisted per tenant.
  Future<void> chooseLocale(String code) async {
    final slug = state.slug;
    if (slug == null || !state.availableLocales.contains(code)) return;
    ref.read(localeProvider.notifier).setLocale(code);
    await ref.read(preferencesProvider).setLocale(slug, code);
  }

  void _applyLocale(String slug, List<String> allowed, String? fallback) {
    final saved = ref.read(preferencesProvider).locale(slug);
    String pick;
    if (saved != null && allowed.contains(saved)) {
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
