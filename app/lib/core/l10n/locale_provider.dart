import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/network_providers.dart';

/// Current UI locale. Arabic is the default; startup sets it from the saved
/// choice or the store's `default_locale` / `locales` (`GET /app-config`).
final localeProvider = NotifierProvider<LocaleNotifier, Locale>(
  LocaleNotifier.new,
);

class LocaleNotifier extends Notifier<Locale> {
  static const supported = ['ar', 'en'];

  @override
  Locale build() => const Locale('ar');

  /// Applies [code] without persisting (startup, store locales changed).
  void setLocale(String code) {
    final c = supported.contains(code) ? code : 'ar';
    ref.read(apiContextProvider).locale = c;
    state = Locale(c);
  }
}
