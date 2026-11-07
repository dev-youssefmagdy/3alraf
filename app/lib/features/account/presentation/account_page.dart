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
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(l10n.navAccount),
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          if (!auth.signedIn) ...[
            Icon(
              Icons.person_outline_rounded,
              size: 64,
              color: context.colors.primary,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              l10n.accountGuestTitle,
              style: context.text.titleLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              l10n.accountGuestBody,
              textAlign: TextAlign.center,
              style: context.text.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: l10n.login,
              onPressed: () => context.push(AppRoutes.login),
            ),
            const SizedBox(height: AppSpacing.sm),
            AppButton(
              label: l10n.register,
              variant: AppButtonVariant.outlined,
              onPressed: () => context.push(AppRoutes.register),
            ),
          ] else
            Card(
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person_rounded)),
                title: Text(customer?.name ?? ''),
                subtitle: Text(customer?.email ?? ''),
                trailing: customer != null && !customer.emailVerified
                    ? ActionChip(
                        label: Text(l10n.emailNotVerified),
                        onPressed: () => context.push(AppRoutes.verifyEmail),
                      )
                    : null,
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          if (auth.signedIn) ...[
            ListTile(
              leading: const Icon(Icons.receipt_long_outlined),
              title: Text(l10n.accountOrders),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push(AppRoutes.orders),
            ),
            ListTile(
              leading: const Icon(Icons.location_on_outlined),
              title: Text(l10n.accountAddresses),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push(AppRoutes.addresses),
            ),
            ListTile(
              leading: const Icon(Icons.manage_accounts_outlined),
              title: Text(l10n.profileTitle),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push(AppRoutes.accountProfile),
            ),
            ListTile(
              leading: const Icon(Icons.lock_reset_rounded),
              title: Text(l10n.changePasswordTitle),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push(AppRoutes.accountPassword),
            ),
          ],
          if (store.availableLocales.length > 1)
            ListTile(
              leading: const Icon(Icons.translate_rounded),
              title: Text(l10n.language),
              subtitle: Text(languageName(locale.languageCode)),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => showLanguagePicker(ref),
            ),
          ListTile(
            leading: const Icon(Icons.brightness_6_outlined),
            title: Text(l10n.themeTitle),
            subtitle: Text(themeLabel(context, themeMode)),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => showThemePicker(context, ref),
          ),
          ListTile(
            leading: const Icon(Icons.handshake_outlined),
            title: Text(l10n.affiliateTitle),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push(AppRoutes.affiliate),
          ),
          ListTile(
            leading: const Icon(Icons.info_outline_rounded),
            title: Text(l10n.aboutTitle),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push(AppRoutes.about),
          ),
          if (auth.signedIn) ...[
            ListTile(
              leading: Icon(Icons.logout_rounded, color: context.colors.error),
              title: Text(l10n.logout),
              onTap: () => ref.read(authControllerProvider.notifier).logout(),
            ),
            ListTile(
              leading: Icon(
                Icons.delete_outline_rounded,
                color: context.colors.error,
              ),
              title: Text(
                l10n.deleteAccountTitle,
                style: TextStyle(color: context.colors.error),
              ),
              onTap: () => context.push(AppRoutes.accountDelete),
            ),
          ],
        ],
      ),
    );
  }
}
