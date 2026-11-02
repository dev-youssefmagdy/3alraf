import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

class RatingStars extends StatelessWidget {
  const RatingStars({
    super.key,
    required this.rating,
    this.count,
    this.size = 16,
  });

  /// 0..5 (may be fractional).
  final double rating;
  final int? count;
  final double size;

  @override
  Widget build(BuildContext context) {
    final gold = context.extra.gold;
    return Semantics(
      label: context.l10n.ratingSemantics(rating.toStringAsFixed(1)),
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 1; i <= 5; i++)
            Icon(
              rating >= i
                  ? Icons.star_rounded
                  : (rating >= i - 0.5
                        ? Icons.star_half_rounded
                        : Icons.star_outline_rounded),
              size: size,
              color: gold,
            ),
          if (count != null) ...[
            const SizedBox(width: 4),
            Text('($count)', style: context.text.labelSmall),
          ],
        ],
      ),
    );
  }
}
