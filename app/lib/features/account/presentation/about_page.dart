import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/theme_mode_provider.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../content/application/content_providers.dart';
import '../../startup/application/startup_controller.dart';
import '../../store/application/store_controller.dart';

/// Opens the store listing of this app (App Store / Play) from
/// `GET /store` `app_links`; tells the user when no link is configured.
Future<void> rateApp(BuildContext context, WidgetRef ref) async {
  final links = ref.read(storeProfileProvider)?.appLinks ?? const {};
  final key = defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
  final url = links[key] ?? links.values.firstOrNull;
  if (url == null) {
    context.showSnack(context.l10n.rateAppUnavailable);
    return;
  }
  await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
}

/// Light / dark / system chooser (persisted).
Future<void> showThemePicker(BuildContext context, WidgetRef ref) async {
  final current = ref.read(themeModeProvider);
  final picked = await showAppBottomSheet<ThemeMode>(
    context: context,
    useRootNavigator: true,
    builder: (c) {
      final l10n = c.l10n;
      Widget option(ThemeMode m, String label, IconData icon) => Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
        child: ChoiceCard(
          title: label,
          leading: Icon(icon, color: c.colors.primary),
          selected: m == current,
          onTap: () => Navigator.pop(c, m),
        ),
      );
      return AppSheet(
        title: l10n.themeTitle,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            option(ThemeMode.light, l10n.themeLight, Icons.light_mode_outlined),
            option(ThemeMode.dark, l10n.themeDark, Icons.dark_mode_outlined),
            option(
              ThemeMode.system,
              l10n.themeSystem,
              Icons.brightness_auto_outlined,
            ),
          ],
        ),
      );
    },
  );
  if (picked != null) {
    await ref.read(themeModeProvider.notifier).set(picked);
  }
}

String themeLabel(BuildContext context, ThemeMode m) => switch (m) {
  ThemeMode.dark => context.l10n.themeDark,
  ThemeMode.system => context.l10n.themeSystem,
  ThemeMode.light => context.l10n.themeLight,
};

/// About: logo, store, version, rate app, legal pages, contact.
class AboutPage extends ConsumerWidget {
  const AboutPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final storeName = ref.watch(storeControllerProvider).name;
    final version = ref.watch(appVersionProvider).value;
    final pages = ref.watch(pagesProvider).asData?.value ?? const [];
    final x = context.extra;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.aboutTitle)),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.gutter),
        children: [
          Container(
            padding: const EdgeInsets.all(AppSpacing.lg),
            decoration: BoxDecoration(
              color: x.brandSoft,
              borderRadius: BorderRadius.circular(AppRadius.lg),
            ),
            child: Column(
              children: [
                LogoCircle(
                  semanticLabel: storeName.isEmpty ? l10n.appName : storeName,
                  size: 112,
                  shadow: false,
                ),
                const SizedBox(height: AppSpacing.smd),
                Text(
                  storeName.isEmpty ? l10n.appName : storeName,
                  textAlign: TextAlign.center,
                  style: context.text.headlineSmall,
                ),
                if (version != null)
                  Text(
                    l10n.appVersion(version),
                    style: context.text.bodySmall?.copyWith(
                      color: context.colors.onSurfaceVariant,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.mdl),
          Text(
            l10n.aboutBody,
            style: context.text.bodyLarge?.copyWith(
              color: context.colors.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: AppSpacing.mdl),
          AppListGroup(
            children: [
              for (final p in pages)
                AppListTile(
                  key: ValueKey('page:${p.slug}'),
                  icon: pageIcon(p.slug),
                  title: p.title,
                  onTap: () => context.push(AppRoutes.page(p.slug)),
                ),
              AppListTile(
                icon: Icons.support_agent_rounded,
                title: l10n.contactTitle,
                onTap: () => context.push(AppRoutes.contact),
              ),
              AppListTile(
                icon: Icons.star_rounded,
                title: l10n.rateApp,
                onTap: () => rateApp(context, ref),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Icon for a static page by its slug (privacy / terms / returns / other).
IconData pageIcon(String slug) {
  final s = slug.toLowerCase();
  if (s.contains('privacy')) return Icons.privacy_tip_outlined;
  if (s.contains('return') || s.contains('refund')) {
    return Icons.inventory_2_outlined;
  }
  if (s.contains('shipping') || s.contains('delivery')) {
    return Icons.local_shipping_outlined;
  }
  if (s.contains('term') || s.contains('condition')) {
    return Icons.gavel_rounded;
  }
  return Icons.description_outlined;
}
