import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/count_badge.dart';

/// Bottom navigation slots, in visual order (start → end).
enum AppNavItem { home, categories, gifts, cart, account }

/// App/Bottom nav (`42:325`): 64 bar + safe area, white, top radius 24,
/// `shadowBar`. Five equal slots: home · categories · raised gifts (centre)
/// · cart · account. Wishlist is deliberately not here (account menu and
/// drawer). When [showGift] is false (gift finder disabled in the store
/// settings) the centre slot is dropped and four slots remain.
///
/// The raised 56 sun circle itself is [GiftNavButton], placed by the shell
/// with [RaisedCenterFabLocation] so its whole area is tappable; this bar
/// draws the label under it and owns the slot's semantics.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({
    super.key,
    required this.selected,
    required this.onSelect,
    this.showGift = true,
    this.cartCount = 0,
  });

  /// Highlighted slot (null = none).
  final AppNavItem? selected;
  final ValueChanged<AppNavItem> onSelect;
  final bool showGift;
  final int cartCount;

  static const double barHeight = 64;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final items = [
      _Slot(
        AppNavItem.home,
        Icons.home_outlined,
        Icons.home_rounded,
        l10n.navHome,
      ),
      _Slot(
        AppNavItem.categories,
        Icons.grid_view_outlined,
        Icons.grid_view_rounded,
        l10n.navCategories,
      ),
      if (showGift)
        _Slot(
          AppNavItem.gifts,
          Icons.card_giftcard_rounded,
          Icons.card_giftcard_rounded,
          l10n.navGifts,
        ),
      _Slot(
        AppNavItem.cart,
        Icons.shopping_cart_outlined,
        Icons.shopping_cart_rounded,
        l10n.navCart,
      ),
      _Slot(
        AppNavItem.account,
        Icons.person_outline_rounded,
        Icons.person_rounded,
        l10n.navAccount,
      ),
    ];
    return DecoratedBox(
      decoration: BoxDecoration(
        color: c.surfaceContainerLowest,
        borderRadius: const BorderRadius.vertical(
          top: Radius.circular(AppRadius.lg),
        ),
        boxShadow: AppShadows.bar,
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: barHeight,
          child: Row(
            children: [
              for (final s in items)
                Expanded(
                  child: _NavButton(
                    key: ValueKey('nav-${s.item.name}'),
                    slot: s,
                    selected: s.item == selected,
                    cartCount: cartCount,
                    onTap: () => onSelect(s.item),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Slot {
  const _Slot(this.item, this.icon, this.selectedIcon, this.label);
  final AppNavItem item;
  final IconData icon;
  final IconData selectedIcon;
  final String label;
}

class _NavButton extends StatelessWidget {
  const _NavButton({
    super.key,
    required this.slot,
    required this.selected,
    required this.cartCount,
    required this.onTap,
  });

  final _Slot slot;
  final bool selected;
  final int cartCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    final gift = slot.item == AppNavItem.gifts;
    final cart = slot.item == AppNavItem.cart;
    final fg = selected ? c.primary : x.textMuted;
    final label = Text(
      slot.label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      textScaler: MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.3),
      style: context.textX.labelXs.copyWith(
        color: gift && selected ? x.pop : fg,
      ),
    );
    Widget icon = Icon(
      selected ? slot.selectedIcon : slot.icon,
      size: 24,
      color: fg,
    );
    if (cart) icon = CountBadge(count: cartCount, child: icon);
    final Widget top = gift
        // The raised circle is drawn by GiftNavButton above this space.
        ? const SizedBox(height: 30)
        : AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            width: 56,
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: selected ? x.brandSoft : Colors.transparent,
              borderRadius: BorderRadius.circular(AppRadius.pill),
            ),
            child: icon,
          );
    final semanticsLabel = cart && cartCount > 0
        ? context.l10n.navCartCount(cartCount)
        : slot.label;
    return Semantics(
      button: true,
      selected: selected,
      label: semanticsLabel,
      excludeSemantics: true,
      child: InkResponse(
        onTap: onTap,
        containedInkWell: true,
        highlightShape: BoxShape.rectangle,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [top, const SizedBox(height: 2), label],
        ),
      ),
    );
  }
}

/// The raised centre gift: 56 sun circle with a 4px white ring and
/// `shadowRaised`; pop-600 with a white icon when [active]. Its semantics
/// are owned by the gifts slot in [AppBottomNav].
class GiftNavButton extends StatelessWidget {
  const GiftNavButton({super.key, required this.onTap, this.active = false});

  final VoidCallback onTap;
  final bool active;

  static const double size = 56;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    return ExcludeSemantics(
      child: GestureDetector(
        key: const ValueKey('nav-gift-circle'),
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active ? x.pop : x.sun,
            border: Border.all(
              color: context.colors.surfaceContainerLowest,
              width: 4,
            ),
            boxShadow: AppShadows.raised,
          ),
          child: Icon(
            Icons.card_giftcard_rounded,
            size: 24,
            color: active ? Colors.white : AppColors.slate950,
          ),
        ),
      ),
    );
  }
}

/// Puts the floating gift circle centred over the bottom bar, raised 20px
/// above the bar's top edge.
class RaisedCenterFabLocation extends FloatingActionButtonLocation {
  const RaisedCenterFabLocation({this.raise = 20});

  final double raise;

  @override
  Offset getOffset(ScaffoldPrelayoutGeometry g) {
    final x = (g.scaffoldSize.width - g.floatingActionButtonSize.width) / 2;
    final y = g.contentBottom - raise;
    return Offset(x, y);
  }
}
