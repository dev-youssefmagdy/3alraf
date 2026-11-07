import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/network/network_providers.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/storage/storage_providers.dart';
import '../data/tenant_models.dart';
import '../domain/tenant_decision.dart';
import 'tenant_controller.dart';

/// Installed app version (`x.y.z`), sent to `GET /app-config`.
final appVersionProvider = FutureProvider<String>((ref) async {
  try {
    final info = await PackageInfo.fromPlatform();
    return info.version;
  } on Object {
    return '1.0.0';
  }
});

enum StartupStatus { idle, loading, ready }

class StartupResult {
  const StartupResult({
    required this.decision,
    this.appConfig,
    this.tenants = const [],
    this.offline = false,
  });

  final TenantDecision decision;
  final AppConfigInfo? appConfig;
  final List<Tenant> tenants;

  /// A saved tenant is open but `/tenants` could not be reached.
  final bool offline;

  bool get maintenance => appConfig?.maintenance ?? false;
  bool get updateRequired => appConfig?.updateRequired ?? false;

  /// Where to go after the splash (gates first, onboarding, then tenant).
  String route({required bool onboardingDone}) {
    if (maintenance) return AppRoutes.maintenance;
    if (updateRequired) return AppRoutes.updateRequired;
    if (!onboardingDone) return AppRoutes.onboarding;
    return tenantRoute;
  }

  /// Home when a tenant is (or becomes) active, else the selection screen.
  String get tenantRoute => switch (decision) {
    ShowSelection() || UnreachableNoTenant() => AppRoutes.selectCountry,
    _ => AppRoutes.home,
  };
}

class StartupState {
  const StartupState({this.status = StartupStatus.idle, this.result});

  final StartupStatus status;
  final StartupResult? result;

  bool get isReady => status == StartupStatus.ready;

  /// Startup finished without any usable decision (needs a retry button).
  bool get failed =>
      isReady &&
      result?.decision is UnreachableNoTenant &&
      !result!.maintenance;
}

final startupControllerProvider =
    NotifierProvider<StartupController, StartupState>(StartupController.new);

/// Splash orchestration: saved tenant + `/tenants` + `/app-config`, then the
/// pure [decideTenant] table, then applying the outcome.
class StartupController extends Notifier<StartupState> {
  @override
  StartupState build() => const StartupState();

  Future<StartupResult> run() async {
    state = const StartupState(status: StartupStatus.loading);
    final prefs = ref.read(preferencesProvider);
    final repo = ref.read(tenantRepositoryProvider);
    final saved = prefs.tenantSlug;
    if (saved != null) {
      // Early binding so cached state is coherent even if everything fails.
      ref.read(apiContextProvider).tenantSlug = saved;
    }
    final version = await ref.read(appVersionProvider.future);

    final results = await Future.wait<Object?>([
      _try(repo.fetchTenants),
      _try(() => repo.fetchAppConfig(version)),
    ]);
    final tenants = results[0] as List<Tenant>?;
    final config = results[1] as AppConfigInfo?;

    final decision = decideTenant(savedSlug: saved, tenants: tenants);
    await _apply(decision);
    final result = StartupResult(
      decision: decision,
      appConfig: config,
      tenants: tenants ?? const [],
      offline: decision is OfflineWithSaved,
    );
    state = StartupState(status: StartupStatus.ready, result: result);
    return result;
  }

  Future<void> _apply(TenantDecision decision) async {
    final tenantCtl = ref.read(tenantControllerProvider.notifier);
    switch (decision) {
      case UseSavedTenant(:final tenant):
        await tenantCtl.activate(tenant.slug, tenant: tenant);
        // Refresh store info in the background.
        tenantCtl.refreshStore();
      case AutoSelectTenant(:final tenant):
        await tenantCtl.select(tenant);
        tenantCtl.refreshStore();
      case OfflineWithSaved(:final slug):
        await tenantCtl.activate(slug);
      case ShowSelection(:final reason):
        if (reason == SelectionReason.savedInactive) await tenantCtl.clear();
      case UnreachableNoTenant():
        break;
    }
  }

  /// Offline banner "retry". Returns the new decision.
  Future<TenantDecision> recheck() async {
    final prefs = ref.read(preferencesProvider);
    final repo = ref.read(tenantRepositoryProvider);
    final tenants = await _try(repo.fetchTenants);
    final decision = decideTenant(
      savedSlug: prefs.tenantSlug,
      tenants: tenants,
    );
    if (decision is OfflineWithSaved) return decision;
    await _apply(decision);
    final prev = state.result;
    state = StartupState(
      status: StartupStatus.ready,
      result: StartupResult(
        decision: decision,
        appConfig: prev?.appConfig,
        tenants: tenants ?? const [],
      ),
    );
    return decision;
  }

  /// Fresh tenant list for the selection screen / switcher.
  Future<List<Tenant>> loadTenants() =>
      ref.read(tenantRepositoryProvider).fetchTenants();

  Future<T?> _try<T>(Future<T> Function() f) async {
    try {
      return await f();
    } on Object {
      return null;
    }
  }
}
