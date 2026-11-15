import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../../core/theme/theme_mode_provider.dart';
import '../../auth/application/auth_controller.dart';
import '../../shell/presentation/app_drawer.dart';
import '../../shell/presentation/shell_scaffold.dart';
import '../../startup/presentation/language_picker.dart';
import '../../store/application/store_controller.dart';
import 'about_page.dart';

/// Account tab: guest call-to-action or the signed-in summary, plus
/// profile, password, orders, addresses, theme, language, about,
/// logout and account deletion.
class AccountPage extends ConsumerWidget {
  const AccountPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final auth = ref.watch(authControllerProvider);
    final store = ref.watch(storeControllerProvider);
    final locale = ref.watch(localeProvider);
    final themeMode = ref.watch(themeModeProvider);
    final customer = auth.customer;
    final signedIn = auth.signedIn;
    final router = GoRouter.of(context);
    final langTile = store.availableLocales.length > 1
        ? AppListTile(
            icon: Icons.language_rounded,
            title: l10n.language,
            value: languageName(locale.languageCode),
            onTap: () => showLanguagePicker(ref),
          )
        : null;
    final themeTile = AppListTile(
      icon: Icons.auto_awesome_outlined,
      title: l10n.themeTitle,
      value: themeLabel(context, themeMode),
      onTap: () => showThemePicker(context, ref),
    );
    final affiliateTile = AppListTile(
      icon: Icons.sell_outlined,
      title: l10n.affiliateTitle,
      onTap: () => context.push(AppRoutes.affiliate),
    );
    final aboutTile = AppListTile(
      icon: Icons.info_outline_rounded,
      title: l10n.aboutTitle,
      onTap: () => context.push(AppRoutes.about),
    );
    const gap = SizedBox(height: AppSpacing.smd);
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(l10n.navAccount),
      ),
      body: ListView(
        padding: const EdgeInsets.only(
          bottom: AppSpacing.lg + kRaisedNavClearance,
        ),
        children: [
          if (!signedIn)
            const _GuestHero()
          else
            _ProfileHeader(
              name: customer?.name ?? '',
              email: customer?.email ?? '',
              unverified: customer != null && !customer.emailVerified,
            ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              AppSpacing.gutter,
              signedIn ? AppSpacing.mdl : 0,
              AppSpacing.gutter,
              0,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (signedIn) ...[
                  AppListGroup(
                    children: [
                      AppListTile(
                        icon: Icons.inventory_2_outlined,
                        title: l10n.accountOrders,
                        onTap: () => context.push(AppRoutes.orders),
                      ),
                      AppListTile(
                        key: const ValueKey('account-wishlist'),
                        icon: Icons.favorite_border_rounded,
                        title: l10n.navWishlist,
                        onTap: () => context.go(AppRoutes.wishlist),
                      ),
                      AppListTile(
                        icon: Icons.location_on_outlined,
                        title: l10n.accountAddresses,
                        onTap: () => context.push(AppRoutes.addresses),
                      ),
                    ],
                  ),
                  gap,
                  AppListGroup(
                    children: [
                      AppListTile(
                        icon: Icons.person_outline_rounded,
                        title: l10n.profileTitle,
                        onTap: () => context.push(AppRoutes.accountProfile),
                      ),
                      AppListTile(
                        icon: Icons.shield_outlined,
                        title: l10n.changePasswordTitle,
                        onTap: () => context.push(AppRoutes.accountPassword),
                      ),
                      ?langTile,
                      themeTile,
                    ],
                  ),
                  gap,
                  AppListGroup(children: [affiliateTile, aboutTile]),
                  gap,
                  AppListGroup(
                    children: [
                      AppListTile(
                        icon: Icons.logout_rounded,
                        title: l10n.logout,
                        danger: true,
                        onTap: () =>
                            ref.read(authControllerProvider.notifier).logout(),
                      ),
                      AppListTile(
                        icon: Icons.delete_outline_rounded,
                        title: l10n.deleteAccountTitle,
                        danger: true,
                        onTap: () => context.push(AppRoutes.accountDelete),
                      ),
                    ],
                  ),
                ] else
                  AppListGroup(
                    children: [
                      AppListTile(
                        icon: Icons.local_shipping_outlined,
                        title: l10n.trackOrder,
                        onTap: () => showTrackOrderDialog(router),
                      ),
                      ?langTile,
                      themeTile,
                      affiliateTile,
                      aboutTile,
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Brand-800 profile header (`60:3739`): 64 sun avatar initial, name H2,
/// e-mail, "e-mail not verified" badge (opens verification) and an edit
/// circle (opens the profile form).
class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.name,
    required this.email,
    required this.unverified,
  });

  final String name;
  final String email;
  final bool unverified;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final l10n = context.l10n;
    final initial = name.trim().isEmpty ? '?' : name.trim().characters.first;
    return Container(
      color: x.brandStrong,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gutter,
        vertical: AppSpacing.mdl,
      ),
      child: Row(
        children: [
          ExcludeSemantics(
            child: Container(
              width: 64,
              height: 64,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: x.sun, shape: BoxShape.circle),
              child: Text(
                initial,
                style: context.text.headlineMedium?.copyWith(
                  color: AppColors.slate950,
                ),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: context.text.headlineSmall?.copyWith(
                    color: Colors.white,
                  ),
                ),
                Text(
                  email,
                  textDirection: TextDirection.ltr,
                  style: context.text.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.88),
                  ),
                ),
                if (unverified) ...[
                  const SizedBox(height: 6),
                  Semantics(
                    button: true,
                    child: Material(
                      color: x.sunSoft,
                      shape: const StadiumBorder(),
                      child: InkWell(
                        customBorder: const StadiumBorder(),
                        onTap: () => context.push(AppRoutes.verifyEmail),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 3,
                          ),
                          child: Text(
                            l10n.emailNotVerified,
                            style: context.textX.labelXs.copyWith(
                              color: x.warningText,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Material(
            color: Colors.white.withValues(alpha: 0.18),
            shape: const CircleBorder(),
            child: IconButton(
              tooltip: l10n.profileTitle,
              color: Colors.white,
              icon: const Icon(Icons.edit_outlined, size: 20),
              onPressed: () => context.push(AppRoutes.accountProfile),
            ),
          ),
        ],
      ),
    );
  }
}

/// Guest hero card (`60:3934`): brand card with a decorative pop blob,
/// white user circle, welcome title + body, Sun "login" and outline
/// "create account".
class _GuestHero extends StatelessWidget {
  const _GuestHero();

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final l10n = context.l10n;
    return Container(
      margin: const EdgeInsets.all(AppSpacing.gutter),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: x.brandStrong,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -60,
            start: -50,
            child: DecorCircle(size: 160, color: x.pop.withValues(alpha: 0.6)),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              children: [
                ExcludeSemantics(
                  child: Container(
                    width: 80,
                    height: 80,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.person_outline_rounded,
                      size: 40,
                      color: context.colors.primary,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                Semantics(
                  header: true,
                  child: Text(
                    l10n.accountGuestTitle,
                    textAlign: TextAlign.center,
                    style: context.text.headlineSmall?.copyWith(
                      color: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  l10n.accountGuestBody,
                  textAlign: TextAlign.center,
                  style: context.text.bodyMedium?.copyWith(
                    color: Colors.white.withValues(alpha: 0.9),
                  ),
                ),
                const SizedBox(height: AppSpacing.mdl),
                Row(
                  children: [
                    Expanded(
                      child: AppButton(
                        label: l10n.login,
                        variant: AppButtonVariant.sun,
                        onPressed: () => context.push(AppRoutes.login),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.smd),
                    Expanded(
                      child: AppButton(
                        label: l10n.register,
                        variant: AppButtonVariant.outlined,
                        onPressed: () => context.push(AppRoutes.register),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
