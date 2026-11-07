import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/theme_mode_provider.dart';
import '../../../core/utils/context_ext.dart';
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
  final picked = await showModalBottomSheet<ThemeMode>(
    context: context,
    showDragHandle: true,
    builder: (c) {
      final l10n = c.l10n;
      Widget option(ThemeMode m, String label, IconData icon) => ListTile(
        leading: Icon(icon),
        title: Text(label),
        trailing: m == current
            ? Icon(Icons.check_rounded, color: c.colors.primary)
            : null,
        selected: m == current,
        onTap: () => Navigator.pop(c, m),
      );
      return SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Text(l10n.themeTitle, style: c.text.titleLarge),
            ),
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
    return Scaffold(
      appBar: AppBar(title: Text(l10n.aboutTitle)),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          Center(
            child: Image.asset(
              'assets/branding/splash_logo.png',
              width: 140,
              semanticLabel: l10n.appName,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Center(
            child: Text(
              storeName.isEmpty ? l10n.appName : storeName,
              style: context.text.titleLarge,
            ),
          ),
          if (version != null)
            Center(
              child: Text(
                l10n.appVersion(version),
                style: context.text.bodySmall,
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          ListTile(
            leading: const Icon(Icons.star_outline_rounded),
            title: Text(l10n.rateApp),
            onTap: () => rateApp(context, ref),
          ),
          ListTile(
            leading: const Icon(Icons.support_agent_rounded),
            title: Text(l10n.contactTitle),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push(AppRoutes.contact),
          ),
          for (final p in pages)
            ListTile(
              key: ValueKey('page:${p.slug}'),
              leading: const Icon(Icons.description_outlined),
              title: Text(p.title),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push(AppRoutes.page(p.slug)),
            ),
        ],
      ),
    );
  }
}
