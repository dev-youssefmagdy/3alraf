import 'preferences.dart';
import 'session_storage.dart';

/// Legacy key migration (runs once, on the first launch of the single-store
/// build). Older builds served several country stores ("tenants"); they saved
/// the chosen store slug under [legacySlugKey] and namespaced the auth /
/// affiliate / cart tokens, locale, store cache and recent searches as
/// `<key>:<slug>`. This moves those values to the plain keys so the customer
/// stays signed in and keeps the cart and language, then forgets the slug.
const String legacySlugKey = 'tenant_slug';

/// Returns `true` when an older install was migrated.
Future<bool> migrateLegacyStorage(
  AppPreferences prefs,
  SessionStorage session,
) async {
  if (prefs.storageMigrated) return false;
  final slug = await prefs.takeString(legacySlugKey);
  final found = slug != null && slug.isNotEmpty;
  if (found) {
    await prefs.adoptNamespace(slug);
    await session.adoptNamespace(slug);
  }
  await prefs.setStorageMigrated();
  return found;
}
