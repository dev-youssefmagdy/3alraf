import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/startup_controller.dart';
import '../application/tenant_controller.dart';
import '../application/tenant_list_provider.dart';
import '../data/tenant_models.dart';
import '../domain/tenant_decision.dart';
import 'country_list.dart';

/// Full-screen country selection (first run / saved store no longer active).
class CountrySelectionPage extends ConsumerStatefulWidget {
  const CountrySelectionPage({super.key});

  @override
  ConsumerState<CountrySelectionPage> createState() =>
      _CountrySelectionPageState();
}

class _CountrySelectionPageState extends ConsumerState<CountrySelectionPage> {
  String? _selected;
  bool _busy = false;

  Future<void> _continue(List<Tenant> tenants) async {
    final tenant = tenants.firstWhere((t) => t.slug == _selected);
    final l10n = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l10n.confirmCountryTitle(tenant.name)),
        content: Text(l10n.confirmCountryMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(l10n.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: Text(l10n.confirm),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    final ctl = ref.read(tenantControllerProvider.notifier);
    await ctl.select(tenant);
    ctl.refreshStore();
    if (mounted) context.go(AppRoutes.home);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final tenants = ref.watch(tenantListProvider);
    final reason = ref.watch(
      startupControllerProvider.select((s) {
        final d = s.result?.decision;
        return d is ShowSelection ? d.reason : null;
      }),
    );
    return Scaffold(
      appBar: AppBar(title: Text(l10n.selectCountryTitle)),
      body: SafeArea(
        child: tenants.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorState(
            error: e,
            onRetry: () => ref.invalidate(tenantListProvider),
          ),
          data: (list) {
            if (list.isEmpty) {
              return EmptyState(
                title: l10n.noStoresAvailable,
                icon: Icons.storefront_outlined,
                actionLabel: l10n.retry,
                onAction: () => ref.invalidate(tenantListProvider),
              );
            }
            return Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (reason == SelectionReason.savedInactive)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: Material(
                        color: context.extra.tint,
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Text(l10n.countryInactiveNotice),
                        ),
                      ),
                    ),
                  Text(
                    l10n.selectCountrySubtitle,
                    style: context.text.bodyLarge,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Expanded(
                    child: CountryList(
                      tenants: list,
                      selectedSlug: _selected,
                      onTap: (t) => setState(() => _selected = t.slug),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppButton(
                    label: l10n.continueAction,
                    loading: _busy,
                    onPressed: _selected == null ? null : () => _continue(list),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
