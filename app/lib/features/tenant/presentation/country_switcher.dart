import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_router.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/tenant_controller.dart';
import '../application/tenant_list_provider.dart';
import '../data/tenant_models.dart';
import 'country_list.dart';

/// "Switch country": bottom sheet listing the stores (current one marked),
/// confirm dialog, then the switch procedure and a reset to Home.
///
/// Uses the root navigator so it can be launched from the drawer.
Future<void> showCountrySwitcher(WidgetRef ref) async {
  final context = rootNavigatorKey.currentContext;
  if (context == null) return;
  final picked = await showModalBottomSheet<Tenant>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => const _SwitcherSheet(),
  );
  if (picked == null) return;
  final current = ref.read(tenantSlugProvider);
  if (picked.slug == current) return;
  final ctx = rootNavigatorKey.currentContext;
  if (ctx == null || !ctx.mounted) return;
  final l10n = ctx.l10n;
  final ok = await showDialog<bool>(
    context: ctx,
    builder: (c) => AlertDialog(
      title: Text(l10n.switchCountryWarnTitle),
      content: Text(l10n.switchCountryWarnBody(picked.name)),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(c, false),
          child: Text(l10n.cancel),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(c, true),
          child: Text(l10n.switchConfirm),
        ),
      ],
    ),
  );
  if (ok != true) return;
  await ref.read(tenantControllerProvider.notifier).switchTo(picked);
  final after = rootNavigatorKey.currentContext;
  if (after == null || !after.mounted) return;
  GoRouter.of(after).go(AppRoutes.home);
  after.showSnack(after.l10n.switchedTo(picked.name));
}

class _SwitcherSheet extends ConsumerWidget {
  const _SwitcherSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final tenants = ref.watch(tenantListProvider);
    final current = ref.watch(tenantSlugProvider);
    return ConstrainedBox(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.8,
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          0,
          AppSpacing.md,
          AppSpacing.md,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.switchCountry, style: context.text.titleLarge),
            const SizedBox(height: AppSpacing.md),
            Flexible(
              child: tenants.when(
                loading: () => const Padding(
                  padding: EdgeInsets.all(AppSpacing.xl),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (e, _) => SizedBox(
                  height: 220,
                  child: ErrorState(
                    error: e,
                    onRetry: () => ref.invalidate(tenantListProvider),
                  ),
                ),
                data: (list) => CountryList(
                  tenants: list,
                  currentSlug: current,
                  shrinkWrap: true,
                  onTap: (t) => Navigator.pop(context, t),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
