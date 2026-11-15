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
      child: GatePage(
        icon: Icons.upload_rounded,
        tone: StateTone.violet,
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
      child: GatePage(
        icon: Icons.schedule_rounded,
        tone: StateTone.sun,
        title: l10n.maintenanceTitle,
        message: message ?? l10n.maintenanceDefault,
        actionLabel: l10n.retry,
        actionVariant: AppButtonVariant.outlined,
        onAction: () => context.go(AppRoutes.splash),
      ),
    );
  }
}

/// Gate page (Maintenance `64:5284` / Update required `64:5303`): a 140
/// blob, Display/M title, body, a Primary L CTA at the bottom and a
/// WhatsApp link when the store has one.
class GatePage extends ConsumerWidget {
  const GatePage({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.tone = StateTone.brand,
    this.actionLabel,
    this.onAction,
    this.actionVariant = AppButtonVariant.filled,
  });

  final IconData icon;
  final StateTone tone;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final AppButtonVariant actionVariant;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final whatsapp = ref.watch(storeProfileProvider)?.whatsapp;
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.gutter),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: Center(
                  child: SingleChildScrollView(
                    child: Column(
                      children: [
                        ToneBlob(
                          icon: icon,
                          tone: tone,
                          size: 140,
                          iconSize: 56,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        Semantics(
                          header: true,
                          child: Text(
                            title,
                            textAlign: TextAlign.center,
                            style: context.text.displaySmall,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.smd),
                        Text(
                          message,
                          textAlign: TextAlign.center,
                          style: context.text.bodyLarge?.copyWith(
                            color: context.colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              if (actionLabel != null && onAction != null)
                AppButton(
                  label: actionLabel!,
                  size: AppButtonSize.l,
                  variant: actionVariant,
                  onPressed: onAction,
                ),
              if (whatsapp != null && whatsapp.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.sm),
                  child: Center(
                    child: TextButton.icon(
                      onPressed: () => launchUrl(
                        Uri.parse('https://wa.me/$whatsapp'),
                        mode: LaunchMode.externalApplication,
                      ),
                      icon: const Icon(Icons.call_outlined, size: 18),
                      label: Text(context.l10n.contactWhatsapp),
                    ),
                  ),
                ),
            ],
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
    final x = context.extra;
    final tints = [x.popSoft, x.violetSoft, x.mintSoft];
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xs),
                child: TextButton(onPressed: _finish, child: Text(l10n.skip)),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: slides.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) {
                  final (icon, title, body) = slides[i];
                  return ListView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.gutter,
                    ),
                    children: [
                      _OnboardingArt(icon: icon, tint: tints[i % tints.length]),
                      const SizedBox(height: AppSpacing.lg),
                      Semantics(
                        header: true,
                        child: Text(
                          title,
                          textAlign: TextAlign.center,
                          style: context.text.displaySmall,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.smd),
                      Text(
                        body,
                        textAlign: TextAlign.center,
                        style: context.text.bodyLarge?.copyWith(
                          color: context.colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            CarouselDots(index: _index, count: slides.length),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.gutter),
              child: AppButton(
                label: last ? l10n.getStarted : l10n.next,
                size: AppButtonSize.l,
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

/// Onboarding art panel: tinted, radius 32, a sun quarter blob at the
/// inline-end, a sparkle and a big white-ringed icon circle.
class _OnboardingArt extends StatelessWidget {
  const _OnboardingArt({required this.icon, required this.tint});

  final IconData icon;
  final Color tint;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    return ExcludeSemantics(
      child: AspectRatio(
        aspectRatio: 1.2,
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: tint,
            borderRadius: BorderRadius.circular(AppRadius.xl),
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              PositionedDirectional(
                top: -60,
                end: -60,
                child: DecorCircle(
                  size: 200,
                  color: x.sun.withValues(alpha: 0.75),
                ),
              ),
              PositionedDirectional(
                top: 28,
                start: 28,
                child: Icon(Icons.auto_awesome_rounded, color: x.pop, size: 28),
              ),
              Container(
                width: 168,
                height: 168,
                decoration: BoxDecoration(
                  color: context.colors.surfaceContainerLowest,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 6),
                  boxShadow: AppShadows.raised,
                ),
                child: Icon(icon, size: 76, color: context.colors.primary),
              ),
            ],
          ),
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
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(AppSpacing.gutter),
                  children: [
                    const SizedBox(height: AppSpacing.lg),
                    Center(
                      child: AppLogo(
                        semanticLabel: store.name.isEmpty
                            ? l10n.appName
                            : store.name,
                        width: 120,
                        height: 100,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Semantics(
                      header: true,
                      child: Text(
                        l10n.chooseLanguageTitle,
                        textAlign: TextAlign.center,
                        style: context.text.displaySmall,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      l10n.chooseLanguageSubtitle,
                      textAlign: TextAlign.center,
                      style: context.text.bodyMedium?.copyWith(
                        color: context.colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    for (final code in options)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.smd),
                        child: LanguageOptionCard(
                          key: ValueKey('lang-$code'),
                          code: code,
                          selected: code == current,
                          onTap: () => _pick(code),
                        ),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.gutter),
                child: AppButton(
                  label: l10n.continueAction,
                  size: AppButtonSize.l,
                  loading: _busy,
                  onPressed: () => _pick(current),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
