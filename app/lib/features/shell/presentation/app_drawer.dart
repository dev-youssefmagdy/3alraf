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
import '../../../core/widgets/widgets.dart';
import '../../account/presentation/about_page.dart';
import '../../content/application/content_providers.dart';
import '../../startup/application/startup_controller.dart';
import '../../startup/presentation/language_picker.dart';
import '../../store/application/store_controller.dart';

/// Side menu (Drawer `62:4164`): 320 wide from the start side with 24
/// radius on the inner corners · logo + close · violet gift CTA · the
/// "collections & offers" group (brands, bundles, wishlist, custom pages,
/// blog, affiliate, track order) · the "info" group (static pages, contact,
/// about, rate app, theme, language) · mint WhatsApp pill · version.
class AppDrawer extends ConsumerWidget {
  const AppDrawer({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final x = context.extra;
    final store = ref.watch(storeControllerProvider);
    final locale = ref.watch(localeProvider);
    final version = ref.watch(appVersionProvider).value;
    final name = store.name;
    final whatsapp = store.profile?.whatsapp;
    final giftOn = store.profile?.features['gift_finder'] ?? false;
    final customPages =
        ref.watch(customPagesProvider).asData?.value ?? const [];
    final pages = ref.watch(pagesProvider).asData?.value ?? const [];
    final themeMode = ref.watch(themeModeProvider);
    void close() => Navigator.of(context).pop();
    return Drawer(
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadiusDirectional.horizontal(
          end: Radius.circular(AppRadius.lg),
        ),
      ),
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(16, 8, 4, 8),
              child: Row(
                children: [
                  AppLogo(
                    semanticLabel: name.isEmpty ? l10n.appName : name,
                    width: 72,
                    height: 56,
                  ),
                  const Spacer(),
                  IconButton(
                    tooltip: l10n.close,
                    icon: const Icon(Icons.close_rounded),
                    onPressed: close,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.md),
                children: [
                  if (giftOn) ...[
                    _GiftCta(
                      onTap: () {
                        close();
                        context.push(AppRoutes.giftFinder);
                      },
                    ),
                    const SizedBox(height: AppSpacing.mdl),
                  ],
                  AppListGroup(
                    title: l10n.drawerCollections,
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
                      AppListTile(
                        key: const ValueKey('drawer-wishlist'),
                        icon: Icons.favorite_border_rounded,
                        title: l10n.navWishlist,
                        onTap: () {
                          close();
                          context.go(AppRoutes.wishlist);
                        },
                      ),
                      for (final p in customPages)
                        AppListTile(
                          key: ValueKey('custom:${p.slug}'),
                          icon: Icons.local_offer_outlined,
                          title: p.title,
                          onTap: () {
                            close();
                            context.push(AppRoutes.customPage(p.slug));
                          },
                        ),
                      if (store.profile?.hasBlog ?? true)
                        _nav(
                          context,
                          Icons.article_outlined,
                          l10n.blogTitle,
                          AppRoutes.blog,
                        ),
                      _nav(
                        context,
                        Icons.handshake_outlined,
                        l10n.affiliateTitle,
                        AppRoutes.affiliate,
                      ),
                      AppListTile(
                        icon: Icons.local_shipping_outlined,
                        title: l10n.trackOrder,
                        onTap: () {
                          final router = GoRouter.of(context);
                          close();
                          showTrackOrderDialog(router);
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.mdl),
                  AppListGroup(
                    title: l10n.drawerInfoPages,
                    children: [
                      for (final p in pages)
                        AppListTile(
                          key: ValueKey('page:${p.slug}'),
                          icon: Icons.description_outlined,
                          title: p.title,
                          onTap: () {
                            close();
                            context.push(AppRoutes.page(p.slug));
                          },
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
                      AppListTile(
                        icon: Icons.star_outline_rounded,
                        title: l10n.rateApp,
                        onTap: () {
                          close();
                          rateApp(context, ref);
                        },
                      ),
                      AppListTile(
                        icon: Icons.brightness_6_outlined,
                        title: l10n.themeTitle,
                        value: themeLabel(context, themeMode),
                        onTap: () {
                          close();
                          showThemePicker(context, ref);
                        },
                      ),
                      if (store.availableLocales.length > 1)
                        AppListTile(
                          icon: Icons.translate_rounded,
                          title: l10n.language,
                          value: languageName(locale.languageCode),
                          onTap: () {
                            close();
                            showLanguagePicker(ref);
                          },
                        ),
                    ],
                  ),
                  if (whatsapp != null && whatsapp.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.mdl),
                    Material(
                      color: x.mint,
                      shape: const StadiumBorder(),
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: () {
                          close();
                          launchUrl(
                            Uri.parse('https://wa.me/$whatsapp'),
                            mode: LaunchMode.externalApplication,
                          );
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.md,
                            vertical: AppSpacing.smd,
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(
                                Icons.chat_outlined,
                                size: 20,
                                color: AppColors.slate950,
                              ),
                              const SizedBox(width: AppSpacing.sm),
                              Text(
                                l10n.contactWhatsapp,
                                style: context.text.labelMedium?.copyWith(
                                  color: AppColors.slate950,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
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
    AppListTile(
      icon: icon,
      title: title,
      onTap: () {
        Navigator.of(context).pop();
        context.push(route);
      },
    );

/// Violet gift finder CTA at the top of the drawer.
class _GiftCta extends StatelessWidget {
  const _GiftCta({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    return Material(
      color: x.violet,
      borderRadius: BorderRadius.circular(AppRadius.md),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: x.sun, shape: BoxShape.circle),
                child: const Icon(
                  Icons.card_giftcard_rounded,
                  color: AppColors.slate950,
                ),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.giftFinder,
                      style: context.text.titleMedium?.copyWith(
                        color: Colors.white,
                      ),
                    ),
                    Text(
                      l10n.giftCtaBody,
                      style: context.text.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.9),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Colors.white),
            ],
          ),
        ),
      ),
    );
  }
}

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
      builder: (c, setState) {
        void submit() {
          final t = parseOrderToken(controller.text);
          if (t == null) {
            setState(() => error = c.l10n.trackOrderInvalid);
          } else {
            Navigator.pop(c, t);
          }
        }

        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          icon: const ToneBlob(
            icon: Icons.local_shipping_outlined,
            tone: StateTone.sun,
            size: 64,
            iconSize: 30,
          ),
          title: Text(c.l10n.trackOrder, style: c.text.headlineSmall),
          content: TextField(
            controller: controller,
            autofocus: true,
            textDirection: TextDirection.ltr,
            onSubmitted: (_) => submit(),
            decoration: InputDecoration(
              hintText: c.l10n.trackOrderHint,
              errorText: error,
              prefixIcon: const Icon(Icons.link_rounded),
            ),
          ),
          actionsPadding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            0,
            AppSpacing.lg,
            AppSpacing.lg,
          ),
          actions: [
            Row(
              children: [
                Expanded(
                  child: AppButton(
                    label: c.l10n.cancel,
                    variant: AppButtonVariant.outlined,
                    onPressed: () => Navigator.pop(c),
                  ),
                ),
                const SizedBox(width: AppSpacing.smd),
                Expanded(
                  child: AppButton(
                    label: c.l10n.continueAction,
                    onPressed: submit,
                  ),
                ),
              ],
            ),
          ],
        );
      },
    ),
  );
  controller.dispose();
  if (token != null) router.push(AppRoutes.order(token));
}
