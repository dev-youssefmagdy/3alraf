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
}) => showModalBottomSheet<CatalogQuery>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  useSafeArea: true,
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
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            child: Row(
              children: [
                Expanded(
                  child: Text(l10n.filters, style: context.text.titleLarge),
                ),
                TextButton(onPressed: _reset, child: Text(l10n.reset)),
              ],
            ),
          ),
          Flexible(
            child: facets.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(AppSpacing.xl),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (e, _) => ErrorState(
                error: e,
                onRetry: () => ref.invalidate(facetsProvider(widget.scope)),
              ),
              data: (f) => ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.all(AppSpacing.md),
                children: [
                  _label(context, l10n.price),
                  Row(
                    children: [
                      Expanded(
                        child: _priceField(_min, l10n.priceFrom, f.priceMin),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: _priceField(_max, l10n.priceTo, f.priceMax),
                      ),
                    ],
                  ),
                  if (f.brands.isNotEmpty) ...[
                    _label(context, l10n.brands),
                    Wrap(
                      spacing: AppSpacing.sm,
                      children: [
                        for (final b in f.brands)
                          FilterChip(
                            label: Text(b.name),
                            selected: _brands.contains(b.id),
                            onSelected: (_) => _toggle(_brands, b.id),
                          ),
                      ],
                    ),
                  ],
                  for (final a in f.attributes) ...[
                    _label(context, a.name),
                    Wrap(
                      spacing: AppSpacing.sm,
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
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: AppButton(label: l10n.applyFilters, onPressed: _apply),
          ),
        ],
      ),
    );
  }

  Widget _label(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(top: AppSpacing.md, bottom: AppSpacing.sm),
    child: Text(
      text,
      style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    ),
  );

  Widget _priceField(TextEditingController c, String label, String? hint) =>
      TextField(
        controller: c,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
        decoration: InputDecoration(labelText: label, hintText: hint),
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
