import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/routing/app_router.dart';
import '../../../core/utils/context_ext.dart';
import '../../store/application/store_controller.dart';

/// Native names of the supported UI languages.
String languageName(String code) => switch (code) {
  'ar' => 'العربية',
  'en' => 'English',
  _ => code,
};

/// Language chooser among the store's locales (drawer / account).
Future<void> showLanguagePicker(WidgetRef ref) async {
  final context = rootNavigatorKey.currentContext;
  if (context == null) return;
  final options = ref.read(storeControllerProvider).availableLocales;
  if (options.length < 2) return;
  final current = ref.read(localeProvider).languageCode;
  final picked = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(c.l10n.language, style: c.text.titleLarge),
          ),
          for (final code in options)
            ListTile(
              title: Text(languageName(code)),
              trailing: code == current
                  ? Icon(Icons.check_rounded, color: c.colors.primary)
                  : null,
              onTap: () => Navigator.pop(c, code),
            ),
        ],
      ),
    ),
  );
  if (picked != null && picked != current) {
    await ref.read(storeControllerProvider.notifier).chooseLocale(picked);
  }
}
