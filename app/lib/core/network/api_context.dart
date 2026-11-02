/// Mutable request context shared by the interceptors: which store (tenant)
/// and language the next request is made for.
class ApiContext {
  ApiContext({this.tenantSlug, this.locale = 'ar'});

  String? tenantSlug;
  String locale;
}

/// Marks a request as targeting the central host (no `X-Tenant`, no
/// auth/cart headers): `GET /tenants`, `GET /app-config`.
const String kCentralExtra = 'central';

/// Set on a request to skip the 401 -> logout handling (e.g. login itself).
const String kSkipUnauthorizedExtra = 'skip_unauthorized';
