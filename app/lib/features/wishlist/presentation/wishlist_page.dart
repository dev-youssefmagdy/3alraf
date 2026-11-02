import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/routing/app_routes.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../shell/presentation/shell_scaffold.dart';
import '../application/wishlist_controller.dart';

/// Wishlist tab (a protected route; the guest branch is a safety net for an
/// expired session).
class WishlistPage extends ConsumerWidget {
  const WishlistPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final signedIn = ref.watch(
      authControllerProvider.select((s) => s.signedIn),
    );
    final state = ref.watch(wishlistProvider);
    final Widget body;
    if (!signedIn) {
      body = EmptyState(
        title: l10n.navWishlist,
        message: l10n.wishlistSignInBody,
        icon: Icons.favorite_border_rounded,
        actionLabel: l10n.login,
        onAction: () =>
            context.push(AppRoutes.loginWithReturn(AppRoutes.wishlist)),
      );
    } else if (state.loading && state.items.isEmpty) {
      body = const ProductGridSkeleton();
    } else if (state.error != null && state.items.isEmpty) {
      body = ErrorState(
        error: state.error,
        onRetry: () => ref.read(wishlistProvider.notifier).load(),
      );
    } else if (state.items.isEmpty) {
      body = EmptyState(
        title: l10n.wishlistEmptyTitle,
        message: l10n.wishlistEmptyBody,
        icon: Icons.favorite_border_rounded,
        actionLabel: l10n.startShopping,
        onAction: () => context.go(AppRoutes.home),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: () => ref.read(wishlistProvider.notifier).load(),
        child: GridView.builder(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          gridDelegate: productGridDelegate(context),
          itemCount: state.items.length,
          itemBuilder: (_, i) => ProductTile(
            key: ValueKey(state.items[i].id),
            product: state.items[i],
            heroScope: 'wishlist',
          ),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        leading: const ShellMenuButton(),
        title: Text(l10n.navWishlist),
      ),
      body: body,
    );
  }
}
