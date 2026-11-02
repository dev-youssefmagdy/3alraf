import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import 'network_image_x.dart';
import 'price_text.dart';
import 'rating_stars.dart';
import 'status_chip.dart';

/// Grid/rail card for a product. Plain fields (no domain model yet; Phase 4
/// maps its model onto this).
class ProductCard extends StatelessWidget {
  const ProductCard({
    super.key,
    required this.title,
    required this.price,
    required this.currency,
    this.imageUrl,
    this.oldPrice,
    this.discountPercent,
    this.rating,
    this.reviewCount,
    this.inStock = true,
    this.favorite = false,
    this.onTap,
    this.onFavorite,
    this.onAddToCart,
    this.heroTag,
  });

  final String title;
  final String price;
  final String currency;
  final String? imageUrl;
  final String? oldPrice;
  final String? discountPercent;
  final double? rating;
  final int? reviewCount;
  final bool inStock;
  final bool favorite;
  final VoidCallback? onTap;
  final VoidCallback? onFavorite;
  final VoidCallback? onAddToCart;
  final Object? heroTag;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    Widget image = NetworkImageX(
      url: imageUrl,
      width: double.infinity,
      cacheWidth: 360,
    );
    if (heroTag != null) image = Hero(tag: heroTag!, child: image);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  image,
                  if (discountPercent != null)
                    PositionedDirectional(
                      top: AppSpacing.sm,
                      start: AppSpacing.sm,
                      child: StatusChip(
                        label: l10n.discountBadge(discountPercent!),
                        tone: StatusTone.danger,
                      ),
                    ),
                  if (onFavorite != null)
                    PositionedDirectional(
                      top: 0,
                      end: 0,
                      child: IconButton(
                        tooltip: favorite
                            ? context.l10n.wishlistRemove
                            : context.l10n.wishlistAdd,
                        onPressed: onFavorite,
                        icon: Icon(
                          favorite
                              ? Icons.favorite_rounded
                              : Icons.favorite_border_rounded,
                          color: context.colors.primary,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.sm + 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.text.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (rating != null) ...[
                    const SizedBox(height: 4),
                    RatingStars(rating: rating!, count: reviewCount, size: 14),
                  ],
                  const SizedBox(height: 6),
                  if (inStock)
                    PriceText(
                      amount: price,
                      currency: currency,
                      oldAmount: oldPrice,
                    )
                  else
                    StatusChip(label: l10n.outOfStock),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
