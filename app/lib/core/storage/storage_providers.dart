import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'key_value_store.dart';
import 'preferences.dart';
import 'session_storage.dart';

/// Overridden in `main()` once `SharedPreferences` has loaded.
final preferencesProvider = Provider<AppPreferences>(
  (ref) => throw UnimplementedError('preferencesProvider must be overridden'),
);

final keyValueStoreProvider = Provider<KeyValueStore>(
  (ref) => const SecureKeyValueStore(),
);

final sessionStorageProvider = Provider<SessionStorage>(
  (ref) => SessionStorage(ref.watch(keyValueStoreProvider)),
);
