import 'app_routes.dart';

/// Result of resolving a banner / deep link.
class LinkTarget {
  const LinkTarget.route(this.route) : external = null;
  const LinkTarget.external(this.external) : route = null;

  final String? route;
  final Uri? external;
}

/// Maps a server link (absolute store URL or in-app path) onto an app
/// route; anything else that is http(s) opens in the browser. Pure and
/// unit-tested. Locale prefixes (`/ar/...`, `/en/...`) are ignored.
LinkTarget? resolveLink(String? link) {
  if (link == null || link.trim().isEmpty || link.trim() == '#') return null;
  final uri = Uri.tryParse(link.trim());
  if (uri == null) return null;
  var segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
  if (segments.isNotEmpty && const ['ar', 'en'].contains(segments.first)) {
    segments = segments.sublist(1);
  }
  String? route;
  if (segments.isEmpty) {
    route = uri.hasAuthority ? null : AppRoutes.home;
  } else {
    final head = segments.first;
    final slug = segments.length > 1 ? segments[1] : null;
    switch (head) {
      case 'product' when slug != null:
        route = AppRoutes.product(slug);
      case 'category' when slug != null:
        route = AppRoutes.category(slug);
      case 'brand' when slug != null:
        route = AppRoutes.brand(slug);
      case 'tag' when slug != null:
        route = AppRoutes.tag(slug);
      case 'bundle' when slug != null:
        route = AppRoutes.bundle(slug);
      case 'bundles':
        route = AppRoutes.bundles;
      case 'brands':
        route = AppRoutes.brands;
      case 'gift-finder':
        route = AppRoutes.giftFinder;
      case 'search':
        final q = uri.queryParameters['q'];
        route = (q == null || q.isEmpty)
            ? AppRoutes.search
            : AppRoutes.results(q);
      case 'wishlist':
        route = AppRoutes.wishlist;
      case 'cart':
        route = AppRoutes.cart;
      case 'account':
        route = segments.length == 1 ? AppRoutes.account : null;
      case 'order' || 'orders' when slug != null:
        // Public order tracking: the token is the credential.
        route = segments.length == 2
            ? AppRoutes.order(slug)
            : (segments[2] == 'confirmation'
                  ? AppRoutes.orderConfirmation(slug)
                  : null);
      case 'page' when slug != null:
        route = AppRoutes.page(slug);
      case 'blog':
        route = slug == null ? AppRoutes.blog : AppRoutes.blogPost(slug);
      case 'launch' when slug != null:
        route = AppRoutes.launch(slug);
      case 'about':
        route = AppRoutes.about;
      case 'contact':
        route = AppRoutes.contact;
      case 'affiliate':
        route = AppRoutes.affiliate;
      default:
        // Web catch-all `/{slug}` = custom landing page.
        if (segments.length == 1 &&
            (uri.scheme.isEmpty ||
                uri.scheme == 'http' ||
                uri.scheme == 'https') &&
            !AppRoutes.knownTopLevel.contains(head)) {
          route = AppRoutes.customPage(head);
        }
    }
  }
  if (route != null) return LinkTarget.route(route);
  if (uri.scheme == 'http' || uri.scheme == 'https') {
    return LinkTarget.external(uri);
  }
  return null;
}

/// Rewrites an incoming (deep) link that the router does not know as-is:
/// `/{ar|en}/...`, web `order/{token}`, `search?q=`, custom-page slugs.
/// Returns `null` when [uri] already is an app route.
String? rewriteIncomingLocation(Uri uri) {
  final path = uri.path;
  final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
  if (segments.isEmpty) return null;
  final hasLocale = const ['ar', 'en'].contains(segments.first);
  final head = hasLocale && segments.length > 1 ? segments[1] : segments.first;
  final needs =
      hasLocale ||
      head == 'order' ||
      (segments.length == 1 &&
          head == 'search' &&
          (uri.queryParameters['q'] ?? '').isNotEmpty) ||
      (segments.length == 1 && !AppRoutes.knownTopLevel.contains(head));
  if (!needs) return null;
  final target = resolveLink(
    Uri(
      path: path,
      queryParameters: uri.queryParameters.isEmpty ? null : uri.queryParameters,
    ).toString(),
  );
  final route = target?.route;
  if (route == null || route == uri.toString()) return null;
  return route;
}
