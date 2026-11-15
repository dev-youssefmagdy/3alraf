import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/context_ext.dart';
import '../../cart/application/cart_controller.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/widgets/offline_banner.dart';
import '../../store/application/store_controller.dart';
import 'app_bottom_nav.dart';
import '../../startup/application/startup_controller.dart';
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

/// Bottom navigation shell (Home, Categories, raised Gifts, Cart, Account)
/// with the side drawer and the offline banner. The wishlist stays a shell
/// branch (reached from the account menu and the drawer) but has no bottom
/// nav slot; while it is open the Account slot is highlighted.
class ShellScaffold extends ConsumerWidget {
  const ShellScaffold({super.key, required this.shell});

  final StatefulNavigationShell shell;

  // Branch order in app_router.dart.
  static const _homeBranch = 0;
  static const _categoriesBranch = 1;
  static const _cartBranch = 2;
  static const _wishlistBranch = 3;
  static const _accountBranch = 4;

  static AppNavItem? itemForBranch(int branch) => switch (branch) {
    _homeBranch => AppNavItem.home,
    _categoriesBranch => AppNavItem.categories,
    _cartBranch => AppNavItem.cart,
    _wishlistBranch || _accountBranch => AppNavItem.account,
    _ => null,
  };

  void _select(BuildContext context, AppNavItem item) {
    final branch = switch (item) {
      AppNavItem.home => _homeBranch,
      AppNavItem.categories => _categoriesBranch,
      AppNavItem.cart => _cartBranch,
      AppNavItem.account => _accountBranch,
      AppNavItem.gifts => null,
    };
    if (branch == null) {
      context.push(AppRoutes.giftFinder);
      return;
    }
    shell.goBranch(branch, initialLocation: branch == shell.currentIndex);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(cartControllerProvider); // starts loading for the badge
    final offline = ref.watch(
      startupControllerProvider.select((s) => s.result?.offline ?? false),
    );
    final giftOn =
        ref.watch(storeProfileProvider)?.features['gift_finder'] ?? false;
    final cartCount = ref.watch(cartCountProvider);
    return Scaffold(
      key: shellScaffoldKey,
      drawer: const AppDrawer(),
      body: Column(
        children: [
          if (offline)
            OfflineBanner(
              onRetry: () =>
                  ref.read(startupControllerProvider.notifier).recheck(),
            ),
          Expanded(
            child: MediaQuery.removePadding(
              context: context,
              removeTop: offline,
              child: shell,
            ),
          ),
        ],
      ),
      floatingActionButton: giftOn
          ? GiftNavButton(onTap: () => _select(context, AppNavItem.gifts))
          : null,
      floatingActionButtonLocation: const RaisedCenterFabLocation(),
      bottomNavigationBar: AppBottomNav(
        selected: itemForBranch(shell.currentIndex),
        showGift: giftOn,
        cartCount: cartCount,
        onSelect: (item) => _select(context, item),
      ),
    );
  }
}
