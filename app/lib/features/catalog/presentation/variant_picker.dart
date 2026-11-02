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
              o.name,
              style: context.text.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
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
    return Semantics(
      button: true,
      selected: selected,
      enabled: available,
      label: value.label,
      excludeSemantics: true,
      child: InkWell(
        onTap: available ? onTap : null,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Container(
          constraints: const BoxConstraints(minHeight: 44, minWidth: 44),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.sm),
            color: selected ? context.extra.tint : null,
            border: Border.all(
              color: selected ? scheme.primary : scheme.outlineVariant,
              width: selected ? 2 : 1,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (swatch != null) ...[
                CircleAvatar(radius: 9, backgroundColor: swatch),
                const SizedBox(width: 6),
              ],
              Text(
                value.label,
                style: TextStyle(
                  color: available
                      ? scheme.onSurface
                      : scheme.onSurface.withValues(alpha: 0.4),
                  decoration: available ? null : TextDecoration.lineThrough,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
