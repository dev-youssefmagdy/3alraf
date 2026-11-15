import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/count_badge.dart';
import '../application/cart_controller.dart';

/// Wraps an icon with the cart unit count (pop badge with a white ring).
/// The badge pops whenever the count changes.
class CartBadge extends ConsumerWidget {
  const CartBadge({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) =>
      CountBadge(count: ref.watch(cartCountProvider), child: child);
}
