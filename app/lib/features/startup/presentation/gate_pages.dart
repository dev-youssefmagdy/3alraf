import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/locale_provider.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/storage/storage_providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../store/application/store_controller.dart';
import '../application/startup_controller.dart';
import 'language_picker.dart';

/// Shown when the installed version is below `min_version`.
class UpdateRequiredPage extends ConsumerWidget {
  const UpdateRequiredPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final config = ref.watch(
      startupControllerProvider.select((s) => s.result?.appConfig),
    );
    final url = defaultTargetPlatform == TargetPlatform.iOS
        ? config?.iosUrl
        : config?.androidUrl;
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: EmptyState(
            icon: Icons.system_update_rounded,
            title: l10n.updateRequiredTitle,
            message: l10n.updateRequiredBody,
            actionLabel: url == null ? null : l10n.updateNow,
            onAction: url == null
                ? null
                : () => launchUrl(
                    Uri.parse(url),
                    mode: LaunchMode.externalApplication,
                  ),
          ),
        ),
      ),
    );
  }
}

/// Shown when `/app-config` reports `maintenance: true`.
class MaintenancePage extends ConsumerWidget {
  const MaintenancePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final message = ref.watch(
      startupControllerProvider.select(
        (s) => s.result?.appConfig?.maintenanceMessage,
      ),
    );
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: EmptyState(
            icon: Icons.construction_rounded,
            title: l10n.maintenanceTitle,
            message: message ?? l10n.maintenanceDefault,
            actionLabel: l10n.retry,
            onAction: () => context.go(AppRoutes.splash),
          ),
        ),
      ),
    );
  }
}

/// First-launch slides (skippable).
class OnboardingPage extends ConsumerStatefulWidget {
  const OnboardingPage({super.key});

  @override
  ConsumerState<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends ConsumerState<OnboardingPage> {
  final _controller = PageController();
  int _index = 0;

  Future<void> _finish() async {
    await ref.read(preferencesProvider).setOnboardingDone();
    if (!mounted) return;
    context.go(AppRoutes.home);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final slides = [
      (Icons.spa_outlined, l10n.onboarding1Title, l10n.onboarding1Body),
      (
        Icons.card_giftcard_rounded,
        l10n.onboarding2Title,
        l10n.onboarding2Body,
      ),
      (
        Icons.local_shipping_outlined,
        l10n.onboarding3Title,
        l10n.onboarding3Body,
      ),
    ];
    final last = _index == slides.length - 1;
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TextButton(onPressed: _finish, child: Text(l10n.skip)),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: slides.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) {
                  final (icon, title, body) = slides[i];
                  return Padding(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 140,
                          height: 140,
                          decoration: BoxDecoration(
                            color: context.extra.tint,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            icon,
                            size: 64,
                            color: context.colors.primary,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        Text(
                          title,
                          textAlign: TextAlign.center,
                          style: context.text.headlineSmall,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          body,
                          textAlign: TextAlign.center,
                          style: context.text.bodyLarge,
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < slides.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.all(4),
                    width: i == _index ? 24 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: i == _index
                          ? context.colors.primary
                          : context.colors.outlineVariant,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: AppButton(
                label: last ? l10n.getStarted : l10n.next,
                onPressed: last
                    ? _finish
                    : () => _controller.nextPage(
                        duration: const Duration(milliseconds: 250),
                        curve: Curves.easeOut,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// First launch: pick the UI language among the store's locales (shown only
/// when the user never chose one and the store offers several).
class LanguageSelectionPage extends ConsumerStatefulWidget {
  const LanguageSelectionPage({super.key});

  @override
  ConsumerState<LanguageSelectionPage> createState() =>
      _LanguageSelectionPageState();
}

class _LanguageSelectionPageState extends ConsumerState<LanguageSelectionPage> {
  bool _busy = false;

  Future<void> _pick(String code) async {
    if (_busy) return;
    setState(() => _busy = true);
    await ref.read(storeControllerProvider.notifier).chooseLocale(code);
    if (!mounted) return;
    final result = ref.read(startupControllerProvider).result;
    final done = ref.read(preferencesProvider).onboardingDone;
    context.go(
      result?.afterLanguage(onboardingDone: done) ??
          (done ? AppRoutes.home : AppRoutes.onboarding),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final store = ref.watch(storeControllerProvider);
    final options = store.availableLocales;
    final current = ref.watch(localeProvider).languageCode;
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: AppSpacing.xl),
                Image.asset('assets/branding/splash_logo.png', height: 120),
                const SizedBox(height: AppSpacing.xl),
                Text(
                  l10n.chooseLanguageTitle,
                  textAlign: TextAlign.center,
                  style: context.text.headlineSmall,
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  l10n.chooseLanguageSubtitle,
                  textAlign: TextAlign.center,
                  style: context.text.bodyLarge,
                ),
                const SizedBox(height: AppSpacing.lg),
                for (final code in options)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Card(
                      child: ListTile(
                        key: ValueKey('lang-$code'),
                        title: Text(languageName(code)),
                        trailing: code == current
                            ? Icon(
                                Icons.check_rounded,
                                color: context.colors.primary,
                              )
                            : null,
                        onTap: () => _pick(code),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
