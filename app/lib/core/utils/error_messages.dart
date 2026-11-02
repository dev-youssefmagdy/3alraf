import '../error/api_exception.dart';
import '../l10n/app_localizations.dart';

/// Localised, user-facing text for an [ApiException].
String errorMessage(AppLocalizations l10n, Object error) {
  final e = ApiException.from(error);
  switch (e.kind) {
    case ApiErrorKind.network:
      return l10n.errorNetwork;
    case ApiErrorKind.timeout:
      return l10n.errorTimeout;
    case ApiErrorKind.rateLimited:
      return l10n.errorRateLimited;
    case ApiErrorKind.server:
      return l10n.errorServer;
    case ApiErrorKind.unauthorized:
      return l10n.errorUnauthorized;
    case ApiErrorKind.notFound:
      return e.message.startsWith('HTTP ') ? l10n.errorNotFound : e.message;
    case ApiErrorKind.validation:
    case ApiErrorKind.forbidden:
    case ApiErrorKind.featureDisabled:
      return e.message.startsWith('HTTP ') ? l10n.errorGeneric : e.message;
    case ApiErrorKind.cancelled:
    case ApiErrorKind.unknown:
      return l10n.errorGeneric;
  }
}
