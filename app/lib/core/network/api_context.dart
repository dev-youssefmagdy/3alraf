/// Mutable request context shared by the interceptors: the language the next
/// request is made in.
class ApiContext {
  ApiContext({this.locale = 'ar'});

  String locale;
}

/// Set on a request to skip the 401 -> logout handling (e.g. login itself).
const String kSkipUnauthorizedExtra = 'skip_unauthorized';
