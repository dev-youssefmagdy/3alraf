abstract final class AppRoutes {
  static const splash = '/';
  static const home = '/home';
  static const categories = '/categories';
  static const cart = '/cart';
  static const wishlist = '/wishlist';
  static const account = '/account';
  static const chooseLanguage = '/language';
  static const onboarding = '/onboarding';
  static const updateRequired = '/update-required';
  static const maintenance = '/maintenance';
  static const login = '/login';
  static const register = '/register';
  static const forgotPassword = '/forgot-password';
  static const resetPassword = '/reset-password';
  static const verifyEmail = '/verify-email';

  static const search = '/search';
  static const searchResults = '/search/results';
  static const brands = '/brands';
  static const bundles = '/bundles';
  static const giftFinder = '/gift-finder';
  static const checkout = '/checkout';
  static const orders = '/orders';
  static const addresses = '/account/addresses';
  static const addressEdit = '/account/addresses/edit';
  static const accountProfile = '/account/profile';
  static const accountPassword = '/account/password';
  static const accountDelete = '/account/delete';
  static const about = '/about';
  static const contact = '/contact';
  static const blog = '/blog';
  static const affiliate = '/affiliate';
  static const affiliateRegister = '/affiliate/register';
  static const affiliateProducts = '/affiliate/products';
  static const affiliatePayouts = '/affiliate/payouts';
  static const affiliateAmbassador = '/affiliate/ambassador';

  static String page(String slug) => '/page/$slug';
  static String blogPost(String slug) => '/blog/$slug';
  static String launch(String code) => '/launch/$code';

  static String product(String slug) => '/product/$slug';
  static String productReviews(String slug) => '/product/$slug/reviews';
  static String category(String slug) => '/category/$slug';
  static String brand(String slug) => '/brand/$slug';
  static String tag(String slug) => '/tag/$slug';
  static String customPage(String slug) => '/custom/$slug';
  static String bundle(String slug) => '/bundle/$slug';
  static String order(String token) => '/orders/$token';
  static String orderConfirmation(String token) =>
      '/orders/$token/confirmation';
  static String orderPay(String token) => '/orders/$token/pay';
  static String results(String q) =>
      '$searchResults?q=${Uri.encodeQueryComponent(q)}';

  /// Routes that need a signed-in customer (and everything below them).
  /// `/orders` itself (the list) is protected, but `/orders/{token}` is NOT:
  /// the API serves orders by their unguessable public token to guests too
  /// (public tracking links, deep links).
  static const protectedPrefixes = [
    wishlist,
    '/addresses',
    addresses,
    accountProfile,
    accountPassword,
    accountDelete,
    checkout,
    verifyEmail,
  ];

  /// Protected only when matched exactly.
  static const protectedExact = [orders];

  /// First path segments the app owns; any other single-segment link is a
  /// store custom page (mirrors the web catch-all `/{slug}`).
  static const knownTopLevel = {
    'home',
    'categories',
    'cart',
    'wishlist',
    'account',
    'language',
    'onboarding',
    'update-required',
    'maintenance',
    'login',
    'register',
    'forgot-password',
    'reset-password',
    'verify-email',
    'search',
    'brands',
    'bundles',
    'gift-finder',
    'checkout',
    'orders',
    'order',
    'about',
    'contact',
    'blog',
    'affiliate',
    'product',
    'category',
    'brand',
    'tag',
    'bundle',
    'custom',
    'launch',
    'page',
    'l',
  };

  /// Screens that make no sense once signed in.
  static const guestOnly = [login, register, forgotPassword, resetPassword];

  /// Startup screens (reachable while/after startup, never protected).
  static const startupRoutes = [
    splash,
    chooseLanguage,
    onboarding,
    updateRequired,
    maintenance,
  ];

  static bool isProtected(String location) {
    final path = location.length > 1 && location.endsWith('/')
        ? location.substring(0, location.length - 1)
        : location;
    return protectedExact.contains(path) ||
        protectedPrefixes.any((p) => path == p || path.startsWith('$p/'));
  }

  static String loginWithReturn(String from) =>
      '$login?from=${Uri.encodeQueryComponent(from)}';
}
