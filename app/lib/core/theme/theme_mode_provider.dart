import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../storage/storage_providers.dart';

/// Light by default; dark is opt-in from settings (Phase 6).
final themeModeProvider = NotifierProvider<ThemeModeNotifier, ThemeMode>(
  ThemeModeNotifier.new,
);

class ThemeModeNotifier extends Notifier<ThemeMode> {
  @override
  ThemeMode build() => switch (ref.read(preferencesProvider).themeMode) {
    'dark' => ThemeMode.dark,
    'system' => ThemeMode.system,
    _ => ThemeMode.light,
  };

  Future<void> set(ThemeMode mode) async {
    state = mode;
    await ref.read(preferencesProvider).setThemeMode(mode.name);
  }
}
