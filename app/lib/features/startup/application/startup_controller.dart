import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/storage/legacy_storage_migration.dart';
import '../../../core/storage/storage_providers.dart';
import '../../store/application/store_controller.dart';
import '../../store/data/store_models.dart';

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
    this.appConfig,
    this.storeLoaded = false,
    this.offline = false,
    this.needsLanguage = false,
  });

  final AppConfigInfo? appConfig;

  /// Shop basics are known (fresh from `/app-config` or cached).
  final bool storeLoaded;

  /// `/app-config` could not be reached; cached store data is used.
  final bool offline;

  /// The user never chose a language and the store offers several.
  final bool needsLanguage;

  bool get maintenance => appConfig?.maintenance ?? false;
  bool get updateRequired => appConfig?.updateRequired ?? false;

  /// Where to go after the splash: gates, language, onboarding, Home.
  String route({required bool onboardingDone}) {
    if (maintenance) return AppRoutes.maintenance;
    if (updateRequired) return AppRoutes.updateRequired;
    if (needsLanguage) return AppRoutes.chooseLanguage;
    return afterLanguage(onboardingDone: onboardingDone);
  }

  /// Next step once a language is set.
  String afterLanguage({required bool onboardingDone}) =>
      onboardingDone ? AppRoutes.home : AppRoutes.onboarding;
}

class StartupState {
  const StartupState({this.status = StartupStatus.idle, this.result});

  final StartupStatus status;
  final StartupResult? result;

  bool get isReady => status == StartupStatus.ready;

  /// Startup finished without app-config and without cached store data
  /// (needs a retry button).
  bool get failed {
    final r = result;
    return isReady &&
        r != null &&
        !r.storeLoaded &&
        !r.maintenance &&
        !r.updateRequired;
  }
}

final startupControllerProvider =
    NotifierProvider<StartupController, StartupState>(StartupController.new);

/// Splash orchestration: one-time storage migration, `GET /app-config`
/// (switches + store basics, cached for offline), locale + session restore,
/// then the next route.
class StartupController extends Notifier<StartupState> {
  @override
  StartupState build() => const StartupState();

  Future<StartupResult> run() async {
    state = const StartupState(status: StartupStatus.loading);
    await migrateLegacyStorage(
      ref.read(preferencesProvider),
      ref.read(sessionStorageProvider),
    );
    final version = await ref.read(appVersionProvider.future);
    final config = await _try(
      () => ref.read(storeRepositoryProvider).fetchAppConfig(version),
    );
    final store = ref.read(storeControllerProvider.notifier);
    final info = config?.store ?? store.cachedInfo();
    final hasProfile = ref.read(preferencesProvider).storeProfileCache != null;
    if (info != null || hasProfile || config != null) {
      await store.apply(config?.store);
      // Fresh profile (contact, features) in the background.
      store.refreshProfile();
    }
    // A reachable server without a `store` block (older backend) still
    // starts with defaults.
    final loaded = ref.read(storeControllerProvider).isLoaded || config != null;
    final result = StartupResult(
      appConfig: config,
      storeLoaded: loaded,
      offline: config == null && loaded,
      needsLanguage: loaded && store.needsLanguageChoice,
    );
    state = StartupState(status: StartupStatus.ready, result: result);
    return result;
  }

  /// Offline banner "retry": re-fetches `/app-config`. Returns whether the
  /// server could be reached.
  Future<bool> recheck() async {
    final version = await ref.read(appVersionProvider.future);
    final config = await _try(
      () => ref.read(storeRepositoryProvider).fetchAppConfig(version),
    );
    if (config == null) return false;
    final store = ref.read(storeControllerProvider.notifier);
    if (config.store != null) await store.apply(config.store);
    await store.refreshProfile();
    final prev = state.result;
    state = StartupState(
      status: StartupStatus.ready,
      result: StartupResult(
        appConfig: config,
        storeLoaded: true,
        needsLanguage: prev?.needsLanguage ?? false,
      ),
    );
    return true;
  }

  Future<T?> _try<T>(Future<T> Function() f) async {
    try {
      return await f();
    } on Object {
      return null;
    }
  }
}
