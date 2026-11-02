import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/utils/context_ext.dart';
import '../../cart/application/cart_controller.dart';
import '../../cart/presentation/cart_badge.dart';
import '../../tenant/application/startup_controller.dart';
import '../../tenant/domain/tenant_decision.dart';
import 'app_drawer.dart';

/// Lets tab pages open the shell drawer from their own AppBar.
final shellScaffoldKey = GlobalKey<ScaffoldState>(debugLabel: 'shell');

class ShellMenuButton extends StatelessWidget {
  const ShellMenuButton({super.key});

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: context.l10n.menu,
    icon: const Icon(Icons.menu_rounded),
    onPressed: () => shellScaffoldKey.currentState?.openDrawer(),
  );
}

/// Bottom navigation shell (Home, Categories, Cart, Wishlist, Account) with
/// the side drawer and the offline banner.
class ShellScaffold extends ConsumerWidget {
  const ShellScaffold({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    ref.watch(cartControllerProvider); // starts loading for the badge
    final offline = ref.watch(
      startupControllerProvider.select((s) => s.result?.offline ?? false),
    );
    return Scaffold(
      key: shellScaffoldKey,
      drawer: const AppDrawer(),
      body: Column(
        children: [
          if (offline)
            MaterialBanner(
              content: Text(l10n.offlineBanner),
              leading: const Icon(Icons.wifi_off_rounded),
              actions: [
                TextButton(
                  onPressed: () async {
                    final router = GoRouter.of(context);
                    final decision = await ref
                        .read(startupControllerProvider.notifier)
                        .recheck();
                    if (decision is ShowSelection) {
                      router.go(AppRoutes.selectCountry);
                    }
                  },
                  child: Text(l10n.retry),
                ),
              ],
            ),
          Expanded(child: shell),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (i) =>
            shell.goBranch(i, initialLocation: i == shell.currentIndex),
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home_rounded),
            label: l10n.navHome,
          ),
          NavigationDestination(
            icon: const Icon(Icons.grid_view_outlined),
            selectedIcon: const Icon(Icons.grid_view_rounded),
            label: l10n.navCategories,
          ),
          NavigationDestination(
            icon: const CartBadge(child: Icon(Icons.shopping_bag_outlined)),
            selectedIcon: const CartBadge(
              child: Icon(Icons.shopping_bag_rounded),
            ),
            label: l10n.navCart,
          ),
          NavigationDestination(
            icon: const Icon(Icons.favorite_border_rounded),
            selectedIcon: const Icon(Icons.favorite_rounded),
            label: l10n.navWishlist,
          ),
          NavigationDestination(
            icon: const Icon(Icons.person_outline_rounded),
            selectedIcon: const Icon(Icons.person_rounded),
            label: l10n.navAccount,
          ),
        ],
      ),
    );
  }
}
