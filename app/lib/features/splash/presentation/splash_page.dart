import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_router.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/storage/storage_providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../startup/application/startup_controller.dart';

/// Minimum time the splash stays up (overridden to zero in tests).
final splashMinDurationProvider = Provider<Duration>(
  (ref) => const Duration(milliseconds: 1200),
);

/// Splash + startup orchestration. Runs [StartupController.run] and, after
/// the minimum display time, routes by the result: maintenance / forced
/// update / language choice / onboarding / Home. If `/app-config` is
/// unreachable and nothing is cached it shows a full-screen retry.
class SplashPage extends ConsumerStatefulWidget {
  const SplashPage({super.key});

  @override
  ConsumerState<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends ConsumerState<SplashPage> {
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    // Provider state must not change during the first build.
    Future<void>.microtask(_boot);
  }

  Future<void> _boot() async {
    if (!mounted) return;
    setState(() => _failed = false);
    final minimum = Future<void>.delayed(ref.read(splashMinDurationProvider));
    final result = await ref.read(startupControllerProvider.notifier).run();
    await minimum;
    if (!mounted) return;
    if (ref.read(startupControllerProvider).failed) {
      setState(() => _failed = true);
      return;
    }
    final onboardingDone = ref.read(preferencesProvider).onboardingDone;
    final route = result.route(onboardingDone: onboardingDone);
    final pending = ref.read(pendingLinkProvider).take();
    final router = GoRouter.of(context);
    router.go(route);
    // A deep link that opened the app: land on Home, then show the target.
    if (pending != null && route == AppRoutes.home && pending != '/') {
      Future<void>.microtask(() => router.push(pending));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (_failed) {
      return Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              const SizedBox(height: AppSpacing.xxxl),
              LogoCircle(semanticLabel: l10n.appName, size: 120),
              Expanded(
                child: ErrorState(message: l10n.errorNetwork, onRetry: _boot),
              ),
            ],
          ),
        ),
      );
    }
    final x = context.extra;
    final version = ref.watch(appVersionProvider).value;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: x.brandStrong,
        body: Stack(
          fit: StackFit.expand,
          children: [
            PositionedDirectional(
              top: 70,
              start: -70,
              child: DecorCircle(
                size: 180,
                color: x.gold.withValues(alpha: 0.75),
              ),
            ),
            PositionedDirectional(
              bottom: -90,
              end: -80,
              child: DecorCircle(
                size: 260,
                color: x.pop.withValues(alpha: 0.5),
              ),
            ),
            SafeArea(
              child: Column(
                children: [
                  const Spacer(),
                  LogoCircle(semanticLabel: '3alraf', size: 160),
                  const SizedBox(height: AppSpacing.lg),
                  ExcludeSemantics(
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        for (var i = 0; i < 3; i++)
                          Container(
                            width: 8,
                            height: 8,
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: i == 2
                                  ? x.sun
                                  : Colors.white.withValues(alpha: 0.85),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  if (version != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: Text(
                        l10n.appVersion(version),
                        style: context.text.bodySmall?.copyWith(
                          color: Colors.white.withValues(alpha: 0.8),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
