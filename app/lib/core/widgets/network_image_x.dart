import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import 'app_shimmer.dart';

/// Cached network image with shimmer placeholder and graceful error tile.
/// Pass [cacheWidth] (logical px) to decode at display size.
class NetworkImageX extends StatelessWidget {
  const NetworkImageX({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.radius = 0,
    this.cacheWidth,
    this.semanticLabel,
  });

  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final double radius;
  final int? cacheWidth;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final dpr = MediaQuery.devicePixelRatioOf(context);
    final scheme = Theme.of(context).colorScheme;
    final Widget fallback = Container(
      width: width,
      height: height,
      color: scheme.surfaceContainerHigh,
      child: Icon(
        Icons.image_not_supported_outlined,
        color: scheme.onSurfaceVariant,
      ),
    );
    final Widget image = (url == null || url!.isEmpty)
        ? fallback
        : CachedNetworkImage(
            imageUrl: url!,
            width: width,
            height: height,
            fit: fit,
            memCacheWidth: cacheWidth == null
                ? null
                : (cacheWidth! * dpr).round(),
            fadeInDuration: const Duration(milliseconds: 150),
            placeholder: (_, _) =>
                AppShimmer(width: width, height: height ?? 100, radius: 0),
            errorWidget: (_, _, _) => fallback,
          );
    final clipped = radius > 0
        ? ClipRRect(borderRadius: BorderRadius.circular(radius), child: image)
        : image;
    return Semantics(
      image: true,
      label: semanticLabel,
      excludeSemantics: semanticLabel != null,
      child: clipped,
    );
  }
}
