import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Skeleton bone (App/Skeleton `47:656`): slate-100 with the real element's
/// radius, a 1.2s sweep in the reading direction, static under reduced
/// motion. Compose several for skeleton screens; never show a spinner on a
/// first load.
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
    final bone = Container(
      width: circle ? height : width,
      height: height,
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        shape: circle ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: circle ? null : BorderRadius.circular(radius),
      ),
    );
    if (context.reduceMotion) return ExcludeSemantics(child: bone);
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return ExcludeSemantics(
      child: Shimmer.fromColors(
        baseColor: scheme.surfaceContainerHighest,
        highlightColor: scheme.surfaceContainerLowest,
        period: const Duration(milliseconds: 1200),
        direction: rtl ? ShimmerDirection.rtl : ShimmerDirection.ltr,
        child: bone,
      ),
    );
  }
}
