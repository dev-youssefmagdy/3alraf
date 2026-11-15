import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/routing/app_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
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
  final picked = await showAppBottomSheet<String>(
    context: context,
    builder: (c) => AppSheet(
      title: c.l10n.language,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final code in options)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: LanguageOptionCard(
                code: code,
                selected: code == current,
                onTap: () => Navigator.pop(c, code),
              ),
            ),
        ],
      ),
    ),
  );
  if (picked != null && picked != current) {
    await ref.read(storeControllerProvider.notifier).chooseLocale(picked);
  }
}

/// Language option card (`50:502`): a 40 code circle ("ع" / "En"), the
/// native language name, and a check when selected (brand-50 fill with a
/// 2px brand border).
class LanguageOptionCard extends StatelessWidget {
  const LanguageOptionCard({
    super.key,
    required this.code,
    required this.selected,
    required this.onTap,
  });

  final String code;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final token = switch (code) {
      'ar' => 'ع',
      _ => code.isEmpty ? '' : code[0].toUpperCase() + code.substring(1),
    };
    return Semantics(
      selected: selected,
      inMutuallyExclusiveGroup: true,
      button: true,
      child: Material(
        color: selected ? x.brandSoft : c.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: selected ? c.primary : c.outlineVariant,
            width: selected ? 2 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 64),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.md,
                vertical: AppSpacing.smd,
              ),
              child: Row(
                children: [
                  ExcludeSemantics(
                    child: Container(
                      width: 40,
                      height: 40,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: selected ? c.primary : c.surfaceContainerHighest,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        token,
                        style: context.text.labelMedium?.copyWith(
                          color: selected ? c.onPrimary : c.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.smd),
                  Expanded(
                    child: Text(
                      languageName(code),
                      style: context.text.titleMedium,
                    ),
                  ),
                  if (selected)
                    Icon(Icons.check_rounded, color: c.primary)
                  else
                    Container(
                      width: 22,
                      height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: c.outline, width: 1.5),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
