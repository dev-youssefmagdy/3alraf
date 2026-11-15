import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';

/// Opens the filter bottom sheet; resolves to the new query on Apply.
Future<CatalogQuery?> showFilterSheet(
  BuildContext context, {
  required ListingScope scope,
  required CatalogQuery current,
  bool priceOnly = false,
}) => showAppBottomSheet<CatalogQuery>(
  context: context,
  builder: (_) =>
      FilterSheet(scope: scope, current: current, priceOnly: priceOnly),
);

class FilterSheet extends ConsumerStatefulWidget {
  const FilterSheet({
    super.key,
    required this.scope,
    required this.current,
    this.priceOnly = false,
  });

  final ListingScope scope;
  final CatalogQuery current;
  final bool priceOnly;

  @override
  ConsumerState<FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<FilterSheet> {
  late Set<int> _brands = {...widget.current.brandIds};
  late Set<int> _values = {...widget.current.valueIds};
  late final _min = TextEditingController(text: widget.current.priceMin ?? '');
  late final _max = TextEditingController(text: widget.current.priceMax ?? '');

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _toggle(Set<int> set, int id) =>
      setState(() => set.contains(id) ? set.remove(id) : set.add(id));

  void _reset() => setState(() {
    _brands = {};
    _values = {};
    _min.clear();
    _max.clear();
  });

  void _apply() {
    String? clean(String s) => s.trim().isEmpty ? null : s.trim();
    final min = clean(_min.text);
    final max = clean(_max.text);
    Navigator.of(context).pop(
      widget.current.copyWith(
        brandIds: _brands,
        valueIds: _values,
        clearPrice: min == null && max == null,
        priceMin: min,
        priceMax: max,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final facets = widget.priceOnly
        ? const AsyncData<Facets>(Facets())
        : ref.watch(facetsProvider(widget.scope));
    return AppSheet(
      title: l10n.filters,
      actions: [
        AppButton(
          label: l10n.applyFilters,
          size: AppButtonSize.l,
          onPressed: _apply,
        ),
        AppButton(
          label: l10n.reset,
          size: AppButtonSize.l,
          variant: AppButtonVariant.outlined,
          onPressed: _reset,
        ),
      ],
      child: facets.when(
        loading: () => const Padding(
          padding: EdgeInsets.all(AppSpacing.xl),
          child: Center(child: CircularProgressIndicator()),
        ),
        error: (e, _) => SizedBox(
          height: 320,
          child: ErrorState(
            error: e,
            onRetry: () => ref.invalidate(facetsProvider(widget.scope)),
          ),
        ),
        data: (f) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _label(context, l10n.price),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: _priceField(_min, l10n.priceFrom, f.priceMin)),
                const SizedBox(width: AppSpacing.smd),
                Expanded(child: _priceField(_max, l10n.priceTo, f.priceMax)),
              ],
            ),
            if (f.brands.isNotEmpty) ...[
              _label(context, l10n.brands),
              for (final b in f.brands)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  dense: true,
                  value: _brands.contains(b.id),
                  onChanged: (_) => _toggle(_brands, b.id),
                  title: Text(b.name, style: context.text.bodyMedium),
                  secondary: b.productsCount == null
                      ? null
                      : Text(
                          '${b.productsCount}',
                          style: context.text.bodySmall?.copyWith(
                            color: context.extra.textMuted,
                          ),
                        ),
                ),
            ],
            for (final a in f.attributes) ...[
              _label(context, a.name),
              Wrap(
                spacing: AppSpacing.sm,
                runSpacing: AppSpacing.sm,
                children: [
                  for (final v in a.values)
                    FilterChip(
                      avatar: _swatch(v.color),
                      label: Text(v.label),
                      selected: _values.contains(v.id),
                      onSelected: (_) => _toggle(_values, v.id),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _label(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(top: AppSpacing.mdl, bottom: AppSpacing.sm),
    child: Semantics(
      header: true,
      child: Text(text, style: context.text.titleLarge),
    ),
  );

  Widget _priceField(TextEditingController c, String label, String? hint) =>
      AppTextField(
        label: label,
        controller: c,
        hint: hint,
        ltr: true,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
      );

  Widget? _swatch(String? hex) {
    final color = parseHexColor(hex);
    if (color == null) return null;
    return CircleAvatar(backgroundColor: color, radius: 8);
  }
}

/// `#RRGGBB` / `RRGGBB` -> Color, null when invalid.
Color? parseHexColor(String? hex) {
  if (hex == null) return null;
  final h = hex.replaceFirst('#', '');
  if (h.length != 6) return null;
  final v = int.tryParse(h, radix: 16);
  return v == null ? null : Color(0xFF000000 | v);
}
