import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/account/presentation/about_page.dart';
import '../../features/account/presentation/account_page.dart';
import '../../features/account/presentation/profile_pages.dart';
import '../../features/affiliate/presentation/affiliate_pages.dart';
import '../../features/content/presentation/content_pages.dart';
import '../../features/addresses/data/address_models.dart';
import '../../features/addresses/presentation/addresses_pages.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/cart/presentation/cart_page.dart';
import '../../features/checkout/presentation/checkout_page.dart';
import '../../features/orders/data/order_models.dart';
import '../../features/orders/presentation/order_confirmation_page.dart';
import '../../features/orders/presentation/orders_pages.dart';
import '../../features/orders/presentation/payment_page.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/auth/presentation/password_pages.dart';
import '../../features/auth/presentation/register_page.dart';
import '../../features/auth/presentation/verify_email_page.dart';
import '../../features/catalog/application/catalog_providers.dart';
import '../../features/catalog/presentation/brands_page.dart';
import '../../features/catalog/presentation/bundles_pages.dart';
import '../../features/catalog/presentation/catalog_widgets.dart';
import '../../features/catalog/presentation/categories_page.dart';
import '../../features/catalog/presentation/gift_finder_page.dart';
import '../../features/catalog/presentation/home_page.dart';
import '../../features/catalog/presentation/listing_page.dart';
import '../../features/catalog/presentation/product_page.dart';
import '../../features/catalog/presentation/reviews_page.dart';
import '../../features/catalog/presentation/search_page.dart';
import '../../features/wishlist/presentation/wishlist_page.dart';
import '../../features/shell/presentation/shell_scaffold.dart';
import '../../features/splash/presentation/splash_page.dart';
import '../../features/startup/application/startup_controller.dart';
import '../../features/startup/presentation/gate_pages.dart';
import '../network/network_providers.dart';
import 'app_routes.dart';
import 'link_router.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');

/// A deep link that arrived before startup finished (cold start). The
/// splash consumes it once the app is ready.
class PendingLink {
  String? uri;

  String? take() {
    final v = uri;
    uri = null;
    return v;
  }
}

final pendingLinkProvider = Provider<PendingLink>((ref) => PendingLink());

/// Pure redirect rules (unit-tested).
///
/// * `/` (splash) always renders: it runs the startup orchestration.
/// * Before startup finished every other route bounces to the splash.
/// * Protected routes need a session -> `/login?from=<route>`.
String? computeRedirect({
  required Uri uri,
  required bool startupReady,
  required bool signedIn,
}) {
  final location = uri.path;
  if (location == AppRoutes.splash) return null;
  if (!startupReady) return AppRoutes.splash;
  if (AppRoutes.startupRoutes.contains(location)) return null;
  if (!signedIn && AppRoutes.isProtected(location)) {
    return AppRoutes.loginWithReturn(uri.toString());
  }
  return null;
}

/// Re-runs the redirect when session / startup state changes.
class _RouterRefresh extends ChangeNotifier {
  void ping() => notifyListeners();
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _RouterRefresh();
  ref.onDispose(refresh.dispose);
  ref.listen(
    authControllerProvider.select((s) => s.signedIn),
    (_, _) => refresh.ping(),
  );
  ref.listen(
    startupControllerProvider.select((s) => s.isReady),
    (_, _) => refresh.ping(),
  );

  late final GoRouter router;

  // A 401 on an authenticated call already wiped the token: drop the
  // in-memory session and send the user to login (returning afterwards).
  ref.listen(unauthorizedEventsProvider, (_, _) {
    ref.read(authControllerProvider.notifier).sessionExpired();
    final current = router.routerDelegate.currentConfiguration.uri;
    final path = current.path;
    if (AppRoutes.startupRoutes.contains(path) ||
        AppRoutes.guestOnly.contains(path)) {
      return;
    }
    final from = Uri.encodeQueryComponent(current.toString());
    router.go('${AppRoutes.login}?from=$from&expired=1');
  });

  router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: AppRoutes.splash,
    refreshListenable: refresh,
    redirect: (context, state) {
      final ready = ref.read(startupControllerProvider).isReady;
      // Deep links (https://3alraf.com/{ar|en}/...) map to app routes.
      final rewritten = rewriteIncomingLocation(state.uri);
      final target = computeRedirect(
        uri: rewritten == null ? state.uri : Uri.parse(rewritten),
        startupReady: ready,
        signedIn: ref.read(authControllerProvider).signedIn,
      );
      if (!ready && state.uri.path != AppRoutes.splash) {
        ref.read(pendingLinkProvider).uri = rewritten ?? state.uri.toString();
      }
      return target ?? rewritten;
    },
    routes: [
      GoRoute(path: AppRoutes.splash, builder: (_, _) => const SplashPage()),
      GoRoute(
        path: AppRoutes.chooseLanguage,
        builder: (_, _) => const LanguageSelectionPage(),
      ),
      GoRoute(
        path: AppRoutes.onboarding,
        builder: (_, _) => const OnboardingPage(),
      ),
      GoRoute(
        path: AppRoutes.updateRequired,
        builder: (_, _) => const UpdateRequiredPage(),
      ),
      GoRoute(
        path: AppRoutes.maintenance,
        builder: (_, _) => const MaintenancePage(),
      ),
      GoRoute(
        path: AppRoutes.login,
        builder: (_, state) => LoginPage(
          from: state.uri.queryParameters['from'],
          expired: state.uri.queryParameters['expired'] == '1',
        ),
      ),
      GoRoute(
        path: AppRoutes.register,
        builder: (_, state) =>
            RegisterPage(from: state.uri.queryParameters['from']),
      ),
      GoRoute(
        path: AppRoutes.forgotPassword,
        builder: (_, _) => const ForgotPasswordPage(),
      ),
      GoRoute(
        path: AppRoutes.resetPassword,
        builder: (_, state) =>
            ResetPasswordPage(email: state.uri.queryParameters['email']),
      ),
      GoRoute(
        path: AppRoutes.verifyEmail,
        builder: (_, state) =>
            VerifyEmailPage(from: state.uri.queryParameters['from']),
      ),
      GoRoute(
        path: AppRoutes.search,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const SearchPage(),
      ),
      GoRoute(
        path: AppRoutes.searchResults,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ListingPage(
          key: ValueKey('q:${state.uri.queryParameters['q']}'),
          scope: ListingScope(q: state.uri.queryParameters['q'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/category/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ListingPage(
          key: ValueKey('c:${state.pathParameters['slug']}'),
          scope: ListingScope(category: state.pathParameters['slug']),
        ),
      ),
      GoRoute(
        path: '/brand/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ListingPage(
          scope: ListingScope(brand: state.pathParameters['slug']),
        ),
      ),
      GoRoute(
        path: '/tag/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            ListingPage(scope: ListingScope(tag: state.pathParameters['slug'])),
      ),
      GoRoute(
        path: '/custom/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ListingPage(
          scope: ListingScope(customPage: state.pathParameters['slug']),
        ),
      ),
      GoRoute(
        path: '/product/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => ProductPage(
          slug: state.pathParameters['slug'] ?? '',
          heroTag: state.extra is ProductHeroTag
              ? state.extra as ProductHeroTag
              : null,
        ),
        routes: [
          GoRoute(
            path: 'reviews',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, state) =>
                ReviewsPage(slug: state.pathParameters['slug'] ?? ''),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.brands,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const BrandsPage(),
      ),
      GoRoute(
        path: AppRoutes.bundles,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const BundlesPage(),
      ),
      GoRoute(
        path: '/bundle/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            BundlePage(slug: state.pathParameters['slug'] ?? ''),
      ),
      GoRoute(
        path: AppRoutes.giftFinder,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const GiftFinderPage(),
      ),
      GoRoute(
        path: AppRoutes.checkout,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const CheckoutPage(),
      ),
      GoRoute(
        path: AppRoutes.orders,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const OrdersPage(),
      ),
      GoRoute(
        path: '/orders/:token',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            OrderDetailPage(token: state.pathParameters['token'] ?? ''),
      ),
      GoRoute(
        path: '/orders/:token/confirmation',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => OrderConfirmationPage(
          token: state.pathParameters['token'] ?? '',
          placed: state.extra is PlacedOrder
              ? state.extra as PlacedOrder
              : null,
        ),
      ),
      GoRoute(
        path: '/orders/:token/pay',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => PaymentPage(
          token: state.pathParameters['token'] ?? '',
          url: state.extra is String ? state.extra as String : null,
        ),
      ),
      GoRoute(
        path: AppRoutes.accountProfile,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const ProfilePage(),
      ),
      GoRoute(
        path: AppRoutes.accountPassword,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const ChangePasswordPage(),
      ),
      GoRoute(
        path: AppRoutes.accountDelete,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const DeleteAccountPage(),
      ),
      GoRoute(
        path: AppRoutes.about,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const AboutPage(),
      ),
      GoRoute(
        path: AppRoutes.contact,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const ContactPage(),
      ),
      GoRoute(
        path: '/page/:slug',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            StaticPageView(slug: state.pathParameters['slug'] ?? ''),
      ),
      GoRoute(
        path: AppRoutes.blog,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const BlogListPage(),
        routes: [
          GoRoute(
            path: ':slug',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, state) =>
                BlogPostPage(slug: state.pathParameters['slug'] ?? ''),
          ),
        ],
      ),
      GoRoute(
        path: '/launch/:code',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) =>
            LaunchPage(code: state.pathParameters['code'] ?? ''),
      ),
      GoRoute(
        path: AppRoutes.affiliate,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const AffiliateHubPage(),
        routes: [
          GoRoute(
            path: 'register',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, _) => const AffiliateRegisterPage(),
          ),
          GoRoute(
            path: 'products',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, _) => const AffiliateProductsPage(),
          ),
          GoRoute(
            path: 'payouts',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, _) => const AffiliatePayoutsPage(),
          ),
          GoRoute(
            path: 'ambassador',
            parentNavigatorKey: rootNavigatorKey,
            builder: (_, _) => const AmbassadorPage(),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.addresses,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, _) => const AddressesPage(),
      ),
      GoRoute(
        path: AppRoutes.addressEdit,
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) => AddressFormPage(
          existing: state.extra is Address ? state.extra as Address : null,
        ),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => ShellScaffold(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.home,
                builder: (_, _) => const HomePage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.categories,
                builder: (_, _) => const CategoriesPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.cart,
                builder: (_, _) => const CartPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.wishlist,
                builder: (context, _) => const WishlistPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.account,
                builder: (context, _) => const AccountPage(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});
