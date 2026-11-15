import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../data/product_models.dart';
import 'filter_sheet.dart' show parseHexColor;

/// One row of choices per option (Size, Color...). Values that cannot lead
/// to an in-stock variant are shown disabled (struck through).
class VariantPickerView extends StatelessWidget {
  const VariantPickerView({
    super.key,
    required this.product,
    required this.selection,
    required this.onSelect,
  });

  final ProductDetail product;
  final Map<int, int> selection;
  final void Function(int optionId, int valueId) onSelect;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final o in product.options) ...[
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.md),
            child: Text(
              _groupLabel(context, o, selection),
              style: context.text.titleSmall,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final v in o.values)
                _Choice(
                  value: v,
                  selected: selection[o.id] == v.id,
                  available: VariantPicker.isAvailable(
                    product,
                    selection,
                    o.id,
                    v.id,
                  ),
                  onTap: () => onSelect(o.id, v.id),
                ),
            ],
          ),
        ],
      ],
    );
  }
}

/// "الحجم: 50 مل" once a value is chosen, else the option name.
String _groupLabel(
  BuildContext context,
  ProductOption o,
  Map<int, int> selection,
) {
  final chosen = o.values.where((v) => v.id == selection[o.id]).firstOrNull;
  return chosen == null
      ? o.name
      : context.l10n.variantSelected(o.name, chosen.label);
}

class _Choice extends StatelessWidget {
  const _Choice({
    required this.value,
    required this.selected,
    required this.available,
    required this.onTap,
  });

  final OptionValue value;
  final bool selected;
  final bool available;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final swatch = parseHexColor(value.color);
    final scheme = context.colors;
    final fg = selected
        ? scheme.onPrimary
        : available
        ? scheme.onSurface
        : scheme.onSurface.withValues(alpha: 0.4);
    return Semantics(
      button: true,
      selected: selected,
      enabled: available,
      label: value.label,
      excludeSemantics: true,
      child: Material(
        color: selected ? scheme.primary : scheme.surfaceContainerLowest,
        shape: StadiumBorder(
          side: BorderSide(color: selected ? scheme.primary : scheme.outline),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: available ? onTap : null,
          child: Container(
            constraints: const BoxConstraints(minHeight: 44, minWidth: 56),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (selected) ...[
                  Icon(Icons.check_rounded, size: 16, color: fg),
                  const SizedBox(width: 6),
                ],
                if (swatch != null) ...[
                  Container(
                    width: 14,
                    height: 14,
                    decoration: BoxDecoration(
                      color: swatch,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 1.5),
                    ),
                  ),
                  const SizedBox(width: 6),
                ],
                Text(
                  value.label,
                  style: context.text.labelMedium?.copyWith(
                    color: fg,
                    decoration: available ? null : TextDecoration.lineThrough,
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
