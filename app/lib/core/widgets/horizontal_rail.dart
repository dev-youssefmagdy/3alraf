import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Horizontally scrolling rail of fixed-height items.
class HorizontalRail extends StatelessWidget {
  const HorizontalRail({
    super.key,
    required this.height,
    required this.itemCount,
    required this.itemBuilder,
    this.itemWidth,
    this.padding = const EdgeInsets.symmetric(horizontal: AppSpacing.md),
  });

  final double height;
  final double? itemWidth;
  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: padding,
        itemCount: itemCount,
        separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm + 4),
        itemBuilder: (context, i) {
          final child = itemBuilder(context, i);
          return itemWidth == null
              ? child
              : SizedBox(width: itemWidth, child: child);
        },
      ),
    );
  }
}
