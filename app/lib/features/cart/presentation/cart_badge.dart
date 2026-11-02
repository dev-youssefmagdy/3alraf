import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/cart_controller.dart';

/// Wraps a nav icon with the cart unit count. The badge pops (scale bounce)
/// whenever the count changes.
class CartBadge extends ConsumerStatefulWidget {
  const CartBadge({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<CartBadge> createState() => _CartBadgeState();
}

class _CartBadgeState extends ConsumerState<CartBadge>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pop = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 320),
  );
  late final Animation<double> _scale = TweenSequence<double>([
    TweenSequenceItem(tween: Tween(begin: 1.0, end: 1.45), weight: 40),
    TweenSequenceItem(tween: Tween(begin: 1.45, end: 1.0), weight: 60),
  ]).animate(CurvedAnimation(parent: _pop, curve: Curves.easeOut));

  @override
  void dispose() {
    _pop.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<int>(cartCountProvider, (prev, next) {
      if (prev != null && prev != next) _pop.forward(from: 0);
    });
    final count = ref.watch(cartCountProvider);
    return ScaleTransition(
      scale: _scale,
      child: Badge(
        isLabelVisible: count > 0,
        label: Text(count > 99 ? '99+' : '$count'),
        child: widget.child,
      ),
    );
  }
}
