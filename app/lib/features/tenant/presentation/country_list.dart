import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/tenant_models.dart';

/// Countries with more entries than this get a search box.
const int kCountrySearchThreshold = 8;

/// Searchable list of stores. Used by the selection page and the switcher
/// sheet. [currentSlug] is marked; [selectedSlug] is highlighted.
class CountryList extends StatefulWidget {
  const CountryList({
    super.key,
    required this.tenants,
    required this.onTap,
    this.currentSlug,
    this.selectedSlug,
    this.shrinkWrap = false,
  });

  final List<Tenant> tenants;
  final ValueChanged<Tenant> onTap;
  final String? currentSlug;
  final String? selectedSlug;
  final bool shrinkWrap;

  @override
  State<CountryList> createState() => _CountryListState();
}

class _CountryListState extends State<CountryList> {
  String _query = '';

  List<Tenant> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.tenants;
    return widget.tenants
        .where(
          (t) =>
              t.name.toLowerCase().contains(q) ||
              t.country.toLowerCase().contains(q) ||
              t.currency.toLowerCase().contains(q),
        )
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final showSearch = widget.tenants.length > kCountrySearchThreshold;
    final items = _filtered;
    return Column(
      mainAxisSize: widget.shrinkWrap ? MainAxisSize.min : MainAxisSize.max,
      children: [
        if (showSearch)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: TextField(
              onChanged: (v) => setState(() => _query = v),
              decoration: InputDecoration(
                hintText: l10n.searchCountry,
                prefixIcon: const Icon(Icons.search_rounded),
              ),
            ),
          ),
        if (items.isEmpty)
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(l10n.noStoresFound),
          )
        else
          Flexible(
            fit: widget.shrinkWrap ? FlexFit.loose : FlexFit.tight,
            child: ListView.separated(
              shrinkWrap: widget.shrinkWrap,
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
              itemBuilder: (context, i) {
                final t = items[i];
                return CountryTile(
                  tenant: t,
                  isCurrent: t.slug == widget.currentSlug,
                  isSelected: t.slug == widget.selectedSlug,
                  onTap: () => widget.onTap(t),
                );
              },
            ),
          ),
      ],
    );
  }
}

class CountryTile extends StatelessWidget {
  const CountryTile({
    super.key,
    required this.tenant,
    required this.onTap,
    this.isCurrent = false,
    this.isSelected = false,
  });

  final Tenant tenant;
  final VoidCallback onTap;
  final bool isCurrent;
  final bool isSelected;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final colors = context.colors;
    final highlighted = isSelected || isCurrent;
    final subtitle = [
      l10n.storeCurrency(tenant.currency),
      if (tenant.languages.isNotEmpty)
        l10n.storeLanguages(tenant.languages.join(' / ')),
    ].join('  ·  ');
    return Semantics(
      button: true,
      selected: highlighted,
      child: Material(
        color: highlighted ? colors.primaryContainer : colors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: highlighted ? colors.primary : colors.outlineVariant,
            width: highlighted ? 1.6 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.md),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 64),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.md,
                vertical: AppSpacing.sm,
              ),
              child: Row(
                children: [
                  Text(
                    tenant.flag.isEmpty ? '🌍' : tenant.flag,
                    style: const TextStyle(fontSize: 32),
                    // Emoji must not be scaled by the Arabic font fallback.
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(tenant.name, style: context.text.titleMedium),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          style: context.text.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (isCurrent)
                    StatusChip(label: l10n.currentStore, tone: StatusTone.brand)
                  else if (isSelected)
                    Icon(Icons.check_circle_rounded, color: colors.primary),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
