import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/locale_provider.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_router.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/theme_mode_provider.dart';
import '../../../core/utils/context_ext.dart';
import '../../account/presentation/about_page.dart';
import '../../content/application/content_providers.dart';
import '../../tenant/application/startup_controller.dart';
import '../../tenant/application/tenant_controller.dart';
import '../../tenant/presentation/country_switcher.dart';
import '../../tenant/presentation/language_picker.dart';

/// Side menu: catalog shortcuts, custom/static pages, blog, affiliate,
/// track order, about / contact / rate app, country, language, theme.
class AppDrawer extends ConsumerWidget {
  const AppDrawer({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final tenant = ref.watch(tenantControllerProvider);
    final locale = ref.watch(localeProvider);
    final version = ref.watch(appVersionProvider).value;
    final name = tenant.store?.name ?? tenant.tenant?.name ?? '';
    final whatsapp = tenant.store?.whatsapp;
    final customPages =
        ref.watch(customPagesProvider).asData?.value ?? const [];
    final pages = ref.watch(pagesProvider).asData?.value ?? const [];
    final themeMode = ref.watch(themeModeProvider);
    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                children: [
                  Image.asset('assets/branding/splash_logo.png', width: 120),
                  if (name.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.sm),
                    Text(name, style: context.text.titleMedium),
                  ],
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                children: [
                  _nav(
                    context,
                    Icons.sell_outlined,
                    l10n.brands,
                    AppRoutes.brands,
                  ),
                  _nav(
                    context,
                    Icons.inventory_2_outlined,
                    l10n.bundles,
                    AppRoutes.bundles,
                  ),
                  if (tenant.store?.features['gift_finder'] ?? false)
                    _nav(
                      context,
                      Icons.card_giftcard_rounded,
                      l10n.giftFinder,
                      AppRoutes.giftFinder,
                    ),
                  if (customPages.isNotEmpty)
                    ExpansionTile(
                      leading: const Icon(Icons.local_offer_outlined),
                      title: Text(l10n.drawerCollections),
                      shape: const Border(),
                      collapsedShape: const Border(),
                      children: [
                        for (final p in customPages)
                          ListTile(
                            key: ValueKey('custom:${p.slug}'),
                            contentPadding: const EdgeInsetsDirectional.only(
                              start: 72,
                              end: 16,
                            ),
                            title: Text(p.title),
                            onTap: () {
                              Navigator.of(context).pop();
                              context.push(AppRoutes.customPage(p.slug));
                            },
                          ),
                      ],
                    ),
                  if (tenant.store?.hasBlog ?? true)
                    _nav(
                      context,
                      Icons.article_outlined,
                      l10n.blogTitle,
                      AppRoutes.blog,
                    ),
                  const Divider(),
                  _nav(
                    context,
                    Icons.handshake_outlined,
                    l10n.affiliateTitle,
                    AppRoutes.affiliate,
                  ),
                  ListTile(
                    leading: const Icon(Icons.local_shipping_outlined),
                    title: Text(l10n.trackOrder),
                    onTap: () {
                      final router = GoRouter.of(context);
                      Navigator.of(context).pop();
                      showTrackOrderDialog(router);
                    },
                  ),
                  if (pages.isNotEmpty)
                    ExpansionTile(
                      leading: const Icon(Icons.description_outlined),
                      title: Text(l10n.drawerInfoPages),
                      shape: const Border(),
                      collapsedShape: const Border(),
                      children: [
                        for (final p in pages)
                          ListTile(
                            key: ValueKey('page:${p.slug}'),
                            contentPadding: const EdgeInsetsDirectional.only(
                              start: 72,
                              end: 16,
                            ),
                            title: Text(p.title),
                            onTap: () {
                              Navigator.of(context).pop();
                              context.push(AppRoutes.page(p.slug));
                            },
                          ),
                      ],
                    ),
                  _nav(
                    context,
                    Icons.support_agent_rounded,
                    l10n.contactTitle,
                    AppRoutes.contact,
                  ),
                  _nav(
                    context,
                    Icons.info_outline_rounded,
                    l10n.aboutTitle,
                    AppRoutes.about,
                  ),
                  ListTile(
                    leading: const Icon(Icons.star_outline_rounded),
                    title: Text(l10n.rateApp),
                    onTap: () {
                      Navigator.of(context).pop();
                      rateApp(context, ref);
                    },
                  ),
                  const Divider(),
                  ListTile(
                    leading: const Icon(Icons.brightness_6_outlined),
                    title: Text(l10n.themeTitle),
                    subtitle: Text(themeLabel(context, themeMode)),
                    onTap: () {
                      Navigator.of(context).pop();
                      showThemePicker(context, ref);
                    },
                  ),
                  ListTile(
                    leading: const Icon(Icons.public_rounded),
                    title: Text(l10n.switchCountry),
                    subtitle: name.isEmpty
                        ? null
                        : Text(l10n.currentStoreLabel(name)),
                    onTap: () {
                      Navigator.of(context).pop();
                      showCountrySwitcher(ref);
                    },
                  ),
                  if (tenant.availableLocales.length > 1)
                    ListTile(
                      leading: const Icon(Icons.translate_rounded),
                      title: Text(l10n.language),
                      subtitle: Text(languageName(locale.languageCode)),
                      onTap: () {
                        Navigator.of(context).pop();
                        showLanguagePicker(ref);
                      },
                    ),
                  if (whatsapp != null && whatsapp.isNotEmpty)
                    ListTile(
                      leading: const Icon(Icons.chat_outlined),
                      title: Text(l10n.contactWhatsapp),
                      onTap: () {
                        Navigator.of(context).pop();
                        launchUrl(
                          Uri.parse('https://wa.me/$whatsapp'),
                          mode: LaunchMode.externalApplication,
                        );
                      },
                    ),
                ],
              ),
            ),
            if (version != null)
              Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Text(
                  l10n.appVersion(version),
                  style: context.text.bodySmall,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

Widget _nav(BuildContext context, IconData icon, String title, String route) =>
    ListTile(
      leading: Icon(icon),
      title: Text(title),
      onTap: () {
        Navigator.of(context).pop();
        context.push(route);
      },
    );

final _uuid = RegExp(r'[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}');

/// Extracts an order token from pasted text or a tracking link.
String? parseOrderToken(String input) => _uuid.firstMatch(input)?.group(0);

/// Public order tracking: paste the token (or the link from the e-mail).
Future<void> showTrackOrderDialog(GoRouter router) async {
  final context = rootNavigatorKey.currentContext;
  if (context == null) return;
  final controller = TextEditingController();
  String? error;
  final token = await showDialog<String>(
    context: context,
    builder: (c) => StatefulBuilder(
      builder: (c, setState) => AlertDialog(
        title: Text(c.l10n.trackOrder),
        content: TextField(
          controller: controller,
          autofocus: true,
          textDirection: TextDirection.ltr,
          decoration: InputDecoration(
            labelText: c.l10n.trackOrderHint,
            errorText: error,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: Text(c.l10n.cancel),
          ),
          TextButton(
            onPressed: () {
              final t = parseOrderToken(controller.text);
              if (t == null) {
                setState(() => error = c.l10n.trackOrderInvalid);
              } else {
                Navigator.pop(c, t);
              }
            },
            child: Text(c.l10n.continueAction),
          ),
        ],
      ),
    ),
  );
  controller.dispose();
  if (token != null) router.push(AppRoutes.order(token));
}
