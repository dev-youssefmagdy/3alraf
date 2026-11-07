import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_router.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/storage/storage_providers.dart';
import '../../../core/theme/app_colors.dart';
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
    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: _failed
            ? Column(
                children: [
                  const SizedBox(height: 48),
                  Image.asset('assets/branding/splash_logo.png', width: 140),
                  Expanded(
                    child: ErrorState(
                      message: context.l10n.errorNetwork,
                      onRetry: _boot,
                    ),
                  ),
                ],
              )
            : Center(
                child: Image.asset(
                  'assets/branding/splash_logo.png',
                  width: 220,
                  semanticLabel: '3alraf',
                ),
              ),
      ),
    );
  }
}
