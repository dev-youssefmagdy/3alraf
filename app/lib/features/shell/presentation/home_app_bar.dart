import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/app_logo.dart';
import '../../cart/presentation/cart_badge.dart';
import '../../store/application/store_controller.dart';
import 'shell_scaffold.dart';

/// App/App bar, Home variant (`42:18`): menu (opens the drawer) at start ·
/// logo image only (no store-name text; the name is the logo's semantics
/// label) · spacer · cart with the pop count badge at end. 56 high, white,
/// 1px outlineVariant bottom border (from the theme).
class HomeAppBar extends ConsumerWidget implements PreferredSizeWidget {
  const HomeAppBar({super.key, this.actions = const []});

  /// Extra actions placed before the cart.
  final List<Widget> actions;

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight + 1);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final name = ref.watch(storeControllerProvider).name;
    return AppBar(
      leading: const ShellMenuButton(),
      titleSpacing: 0,
      centerTitle: false,
      title: AppLogo(semanticLabel: name.isEmpty ? l10n.appName : name),
      actions: [
        ...actions,
        IconButton(
          key: const ValueKey('appbar-cart'),
          tooltip: l10n.navCart,
          icon: const CartBadge(child: Icon(Icons.shopping_cart_outlined)),
          onPressed: () => context.go(AppRoutes.cart),
        ),
        const SizedBox(width: 4),
      ],
    );
  }
}
