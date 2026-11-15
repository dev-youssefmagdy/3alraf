import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';
import 'app_button.dart';
import 'countdown_chip.dart';
import 'network_image_x.dart';
import 'price_text.dart';
import 'rating_stars.dart';
import 'status_chip.dart';

/// Which CTA a [ProductCard] shows (only when [ProductCard.onAddToCart] is
/// set): Primary "add to cart", Secondary "choose options" (opens the variant
/// sheet) or Sun "notify me" (opens the notify sheet).
enum ProductCardCta { add, options, notify }

/// App/Product card (`43:322`): white, 1px outlineVariant border, radius 16,
/// padding 8. Square media (radius 12) on a rotating soft tint with the badge
/// at top-start and a 32 wishlist circle at top-end; 2-line name; rating
/// (hidden when out of stock); Price/M row; optional flash countdown,
/// low-stock line and a full-width 44 CTA.
///
/// Grid width is ~173 (2 columns, gap 12); rail width is 156.
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
    this.tintIndex,
    this.badgeLabel,
    this.badgeTone = StatusTone.violet,
    this.pricePrefix,
    this.lowStockLabel,
    this.flashEndsAt,
    this.cta = ProductCardCta.add,
    this.ctaLoading = false,
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

  /// Index used to rotate the media tint; defaults to a hash of [title].
  final int? tintIndex;

  /// Extra badge (e.g. "جديد") when there is no discount.
  final String? badgeLabel;
  final StatusTone badgeTone;

  /// Shown before the price, e.g. "ابتداءً من" for variable products.
  final String? pricePrefix;

  /// e.g. "متبقي 3 فقط" (warning text).
  final String? lowStockLabel;
  final DateTime? flashEndsAt;
  final ProductCardCta cta;
  final bool ctaLoading;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final tint = x.cardTint(tintIndex ?? title.length);
    Widget image = ColoredBox(
      color: tint,
      child: NetworkImageX(
        url: imageUrl,
        width: double.infinity,
        height: double.infinity,
        cacheWidth: 360,
      ),
    );
    if (heroTag != null) image = Hero(tag: heroTag!, child: image);
    final badge = !inStock
        ? StatusChip(label: l10n.outOfStock, tone: StatusTone.ink)
        : discountPercent != null
        ? StatusChip(
            label: l10n.discountBadge(discountPercent!),
            tone: StatusTone.pop,
          )
        : badgeLabel != null
        ? StatusChip(label: badgeLabel!, tone: badgeTone)
        : null;
    final effectiveCta = inStock ? cta : ProductCardCta.notify;
    List<Widget> info() => [
      const SizedBox(height: 6),
      Text(
        title,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: context.text.titleSmall,
      ),
      if (rating != null && inStock) ...[
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: AlignmentDirectional.centerStart,
          child: RatingStars(rating: rating!, count: reviewCount, size: 13),
        ),
      ],
      const SizedBox(height: 4),
      PriceText(
        amount: price,
        currency: currency,
        oldAmount: oldPrice,
        prefix: pricePrefix,
      ),
      if (flashEndsAt != null && inStock) ...[
        const SizedBox(height: 4),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: AlignmentDirectional.centerStart,
          child: CountdownChip(endsAt: flashEndsAt!),
        ),
      ],
      if (lowStockLabel != null && inStock) ...[
        const SizedBox(height: 2),
        Text(
          lowStockLabel!,
          style: context.textX.labelXs.copyWith(color: x.warningText),
        ),
      ],
    ];
    return Material(
      color: c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(color: c.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: LayoutBuilder(
          builder: (context, box) => Padding(
            padding: const EdgeInsets.all(AppSpacing.sm),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                        child: image,
                      ),
                      if (badge != null)
                        PositionedDirectional(top: 6, start: 6, child: badge),
                      if (onFavorite != null)
                        PositionedDirectional(
                          top: 0,
                          end: 0,
                          child: WishlistCircle(
                            favorite: favorite,
                            onPressed: onFavorite!,
                          ),
                        ),
                    ],
                  ),
                ),
                if (box.hasBoundedHeight)
                  // Fixed cell (grid / rail): the info block takes the free
                  // space (clipped, never overflowing) and the CTA sits at
                  // the bottom so buttons line up across cards.
                  Expanded(
                    child: ClipRect(
                      child: SingleChildScrollView(
                        physics: const NeverScrollableScrollPhysics(),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: info(),
                        ),
                      ),
                    ),
                  )
                else
                  ...info(),
                if (onAddToCart != null) ...[
                  // In a fixed-height cell (grid / rail) the CTA sits at the
                  // bottom so the buttons line up across cards.
                  const SizedBox(height: 6),
                  switch (effectiveCta) {
                    ProductCardCta.add => AppButton(
                      label: l10n.addToCart,
                      icon: Icons.shopping_cart_outlined,
                      loading: ctaLoading,
                      onPressed: onAddToCart,
                      dense: true,
                    ),
                    ProductCardCta.options => AppButton(
                      label: l10n.chooseOptions,
                      variant: AppButtonVariant.outlined,
                      onPressed: onAddToCart,
                      dense: true,
                    ),
                    ProductCardCta.notify => AppButton(
                      label: l10n.notifyMe,
                      variant: AppButtonVariant.sun,
                      onPressed: onAddToCart,
                      dense: true,
                    ),
                  },
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// Height of a card [width] wide in a fixed-extent grid or rail: square
  /// media + name (2 lines) + rating + price + the CTA, plus the flash pill
  /// row when [withFlash]. Text rows grow with the text scale.
  static double extentFor(
    BuildContext context,
    double width, {
    bool withCta = true,
    bool withFlash = false,
    bool withSale = false,
  }) {
    final scale = MediaQuery.textScalerOf(context).scale(1);
    // padding 16 + gaps (6 + 2 + 4) + text rows (44 name, 18 rating, 26 price)
    var h = width + 12 + 88 * scale + 8;
    // On narrow cards the struck old price wraps under the price (12/16).
    if (withSale || withFlash) h += 16 * scale;
    if (withFlash) h += 4 + 26 * scale.clamp(1, 1.4);
    if (withCta) h += 6 + 44 + 4 * scale;
    return h;
  }
}

/// 32 white wishlist circle with `shadowRaised`; filled = pop circle with a
/// white heart. 48 tap target.
class WishlistCircle extends StatelessWidget {
  const WishlistCircle({
    super.key,
    required this.favorite,
    required this.onPressed,
    this.size = 32,
  });

  final bool favorite;
  final VoidCallback onPressed;
  final double size;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final l10n = context.l10n;
    return IconButton(
      tooltip: favorite ? l10n.wishlistRemove : l10n.wishlistAdd,
      onPressed: onPressed,
      padding: EdgeInsets.zero,
      icon: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: favorite ? x.pop : Colors.white,
          boxShadow: AppShadows.raised,
        ),
        child: Icon(
          favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
          size: size * 0.56,
          color: favorite ? Colors.white : x.pop,
        ),
      ),
    );
  }
}
