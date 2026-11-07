import 'package:app/core/storage/key_value_store.dart';
import 'package:app/core/storage/legacy_storage_migration.dart';
import 'package:app/core/storage/preferences.dart';
import 'package:app/core/storage/session_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  late MemoryKeyValueStore secrets;
  late SessionStorage session;

  Future<AppPreferences> prefsWith(Map<String, Object> values) async {
    SharedPreferences.setMockInitialValues(values);
    return AppPreferences(await SharedPreferences.getInstance());
  }

  setUp(() {
    secrets = MemoryKeyValueStore();
    session = SessionStorage(secrets);
  });

  test('older install: tokens, cart, locale, profile cache and searches are '
      'kept under the new keys; old keys are removed', () async {
    final prefs = await prefsWith({
      legacySlugKey: 'egy',
      'locale:egy': 'en',
      'store_cache:egy': '{"name":"Old"}',
      'recent_searches:egy': ['rose', 'oud'],
      'onboarding_done': true,
    });
    secrets.data.addAll({
      'auth_token:egy': 'tok',
      'affiliate_token:egy': 'aff',
      'cart_token:egy': 'cart',
    });

    expect(await migrateLegacyStorage(prefs, session), isTrue);

    expect(await session.authToken(), 'tok');
    expect(await session.affiliateToken(), 'aff');
    expect(await session.cartToken(), 'cart');
    expect(secrets.data.keys.where((k) => k.contains(':')), isEmpty);
    expect(prefs.locale, 'en');
    expect(prefs.storeProfileCache, '{"name":"Old"}');
    expect(prefs.recentSearches, ['rose', 'oud']);
    expect(prefs.onboardingDone, isTrue);
    expect(prefs.storageMigrated, isTrue);
    final raw = await SharedPreferences.getInstance();
    expect(raw.containsKey(legacySlugKey), isFalse);
    expect(raw.getKeys().where((k) => k.contains(':')), isEmpty);
  });

  test('runs once: a later legacy key is ignored', () async {
    final prefs = await prefsWith({});
    expect(await migrateLegacyStorage(prefs, session), isFalse);
    secrets.data['auth_token:egy'] = 'late';
    expect(await migrateLegacyStorage(prefs, session), isFalse);
    expect(await session.authToken(), isNull);
  });

  test('fresh install: nothing to migrate, no locale chosen', () async {
    final prefs = await prefsWith({});
    expect(await migrateLegacyStorage(prefs, session), isFalse);
    expect(prefs.locale, isNull);
    expect(prefs.storageMigrated, isTrue);
  });

  test('values already under the new keys win', () async {
    final prefs = await prefsWith({
      legacySlugKey: 'egy',
      'locale': 'ar',
      'locale:egy': 'en',
    });
    secrets.data.addAll({'auth_token': 'new', 'auth_token:egy': 'old'});
    await migrateLegacyStorage(prefs, session);
    expect(prefs.locale, 'ar');
    expect(await session.authToken(), 'new');
    expect(secrets.data.containsKey('auth_token:egy'), isFalse);
  });
}
