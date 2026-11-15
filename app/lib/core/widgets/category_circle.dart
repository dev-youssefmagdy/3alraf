import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import 'network_image_x.dart';

/// App/Category circle (`43:379`): a 76-wide item with a 72 solid ring
/// (colour by [index] from `ringColours`) around a 64 photo circle and a
/// 12/16 bold 2-line label. Without a photo it shows the first letter on
/// brand-50. Pack them in [CategoryCircleRow] (8 gaps, 16 padding).
class CategoryCircle extends StatelessWidget {
  const CategoryCircle({
    super.key,
    required this.label,
    required this.onTap,
    this.imageUrl,
    this.index = 0,
    this.size = 64,
    this.contain = false,
    this.ring = true,
  });

  final String label;
  final String? imageUrl;
  final VoidCallback onTap;
  final int index;

  /// Photo diameter; the ring adds 8.
  final double size;
  final bool contain;
  final bool ring;

  static const double itemWidth = 76;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final letter = label.isEmpty ? '' : label.characters.first.toUpperCase();
    final outer = size + 8;
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: SizedBox(
          width: outer + 4,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: outer,
                height: outer,
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: ring ? x.ring(index) : context.colors.outlineVariant,
                ),
                child: Container(
                  clipBehavior: Clip.antiAlias,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: x.brandSoft,
                    border: Border.all(
                      color: context.colors.surfaceContainerLowest,
                      width: 2,
                    ),
                  ),
                  child: imageUrl == null || imageUrl!.isEmpty
                      ? Center(
                          child: Text(
                            letter,
                            style: context.text.headlineSmall?.copyWith(
                              color: context.colors.primary,
                            ),
                          ),
                        )
                      : NetworkImageX(
                          url: imageUrl,
                          fit: contain ? BoxFit.contain : BoxFit.cover,
                          cacheWidth: size.round(),
                        ),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                maxLines: 2,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: context.text.labelSmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Horizontal row of [CategoryCircle]s packed with 8 gaps and 16 padding so
/// about 4.6 items are visible and the next one always peeks. No snapping
/// (free scroll), never autoplays.
class CategoryCircleRow extends StatelessWidget {
  const CategoryCircleRow({
    super.key,
    required this.itemCount,
    required this.itemBuilder,
    this.height = 116,
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final double height;

  @override
  Widget build(BuildContext context) {
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return SizedBox(
      height: height + 32 * (scale - 1).clamp(0, 2),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.gutter),
        itemCount: itemCount,
        separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
        itemBuilder: itemBuilder,
      ),
    );
  }
}
