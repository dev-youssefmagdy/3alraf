import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/storage/storage_providers.dart';
import '../../catalog/application/catalog_providers.dart';
import '../../tenant/application/tenant_controller.dart';
import '../data/affiliate_models.dart';
import '../data/affiliate_repository.dart';

final affiliateRepositoryProvider = Provider<AffiliateRepository>((ref) {
  final slug = ref.watch(tenantSlugProvider);
  return AffiliateRepository(
    ref.watch(dioProvider),
    () async => slug == null
        ? null
        : ref.read(sessionStorageProvider).affiliateToken(slug),
  );
});

class AffiliateState {
  const AffiliateState({
    this.signedIn = false,
    this.profile,
    this.ready = true,
  });

  final bool signedIn;
  final AffiliateProfile? profile;

  /// False while the stored token is being verified at start.
  final bool ready;

  static const loading = AffiliateState(ready: false);
  static const signedOut = AffiliateState();
}

final affiliateControllerProvider =
    NotifierProvider<AffiliateController, AffiliateState>(
      AffiliateController.new,
    );

/// Affiliate session (separate token from the customer). Rebuilt on tenant
/// switch, so a token never crosses stores.
class AffiliateController extends Notifier<AffiliateState> {
  @override
  AffiliateState build() {
    final slug = ref.watch(tenantSlugProvider);
    if (slug == null) return AffiliateState.signedOut;
    Future.microtask(() => _restore(slug));
    return AffiliateState.loading;
  }

  AffiliateRepository get _repo => ref.read(affiliateRepositoryProvider);

  Future<void> _restore(String slug) async {
    final token = await ref.read(sessionStorageProvider).affiliateToken(slug);
    if (!ref.mounted) return;
    if (token == null || token.isEmpty) {
      state = AffiliateState.signedOut;
      return;
    }
    try {
      final me = await _repo.me();
      if (ref.mounted) state = AffiliateState(signedIn: true, profile: me);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      if (e.isUnauthorized) {
        await ref.read(sessionStorageProvider).clearAffiliateToken(slug);
        state = AffiliateState.signedOut;
      } else {
        // Keep the session; the dashboard shows the error and retries.
        state = const AffiliateState(signedIn: true);
      }
    }
  }

  Future<void> login(String email, String password) async {
    final slug = ref.read(tenantSlugProvider);
    if (slug == null) return;
    final r = await _repo.login(email, password);
    await ref.read(sessionStorageProvider).saveAffiliateToken(slug, r.token);
    state = AffiliateState(signedIn: true, profile: r.profile);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    await _repo.register(
      name: name,
      email: email,
      password: password,
      passwordConfirmation: passwordConfirmation,
      phone: phone,
    );
  }

  Future<void> logout() async {
    final slug = ref.read(tenantSlugProvider);
    if (slug == null) return;
    await _repo.logout();
    await ref.read(sessionStorageProvider).clearAffiliateToken(slug);
    state = AffiliateState.signedOut;
  }

  /// A 401 from any affiliate call means the token is dead.
  Future<void> expire() async {
    final slug = ref.read(tenantSlugProvider);
    if (slug != null) {
      await ref.read(sessionStorageProvider).clearAffiliateToken(slug);
    }
    if (ref.mounted) state = AffiliateState.signedOut;
  }
}

/// Runs an affiliate read; a 401 ends the affiliate session.
Future<T> _guarded<T>(Ref ref, Future<T> Function() run) async {
  try {
    return await run();
  } on ApiException catch (e) {
    if (e.isUnauthorized) {
      unawaited(ref.read(affiliateControllerProvider.notifier).expire());
    }
    rethrow;
  }
}

void _scope(Ref ref) {
  watchStoreScope(ref);
  ref.watch(affiliateControllerProvider.select((s) => s.signedIn));
}

AffiliateRepository _repoOf(Ref ref) => ref.watch(affiliateRepositoryProvider);

final affiliateDashboardProvider =
    FutureProvider.autoDispose<AffiliateDashboard>((ref) {
      _scope(ref);
      return _guarded(ref, () => _repoOf(ref).dashboard());
    });

final affiliateProductsProvider =
    FutureProvider.autoDispose<List<AffiliateProduct>>((ref) {
      _scope(ref);
      return _guarded(ref, () => _repoOf(ref).products());
    });

final affiliatePayoutsProvider = FutureProvider.autoDispose<List<Payout>>((
  ref,
) {
  _scope(ref);
  return _guarded(ref, () => _repoOf(ref).payouts());
});

final ambassadorProvider = FutureProvider.autoDispose<AmbassadorPortal>((ref) {
  _scope(ref);
  return _guarded(ref, () => _repoOf(ref).ambassador());
});
