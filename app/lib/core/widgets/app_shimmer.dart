import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

import '../theme/app_theme.dart';

/// Shimmering placeholder block; compose several for skeleton screens.
class AppShimmer extends StatelessWidget {
  const AppShimmer({
    super.key,
    this.width,
    this.height = 16,
    this.radius = AppRadius.sm,
    this.circle = false,
  });

  final double? width;
  final double height;
  final double radius;
  final bool circle;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return ExcludeSemantics(
      child: Shimmer.fromColors(
        baseColor: scheme.surfaceContainerHigh,
        highlightColor: scheme.surfaceContainerLowest,
        child: Container(
          width: circle ? height : width,
          height: height,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: circle ? BoxShape.circle : BoxShape.rectangle,
            borderRadius: circle ? null : BorderRadius.circular(radius),
          ),
        ),
      ),
    );
  }
}
