import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app.dart';
import 'core/storage/preferences.dart';
import 'core/storage/storage_providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Last-resort handlers: framework errors are only printed in debug builds
  // (never in release, so request data cannot reach device logs) and async
  // errors that escaped every catch must not crash the app.
  FlutterError.onError = (details) {
    if (kDebugMode) FlutterError.presentError(details);
  };
  PlatformDispatcher.instance.onError = (error, stack) {
    if (kDebugMode) debugPrint('Unhandled: $error');
    return true;
  };
  final prefs = await SharedPreferences.getInstance();
  runApp(
    ProviderScope(
      overrides: [preferencesProvider.overrideWithValue(AppPreferences(prefs))],
      child: const AlrafApp(),
    ),
  );
}
