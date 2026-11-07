/// Build-time configuration, injected with `--dart-define`.
///
/// Example:
/// `flutter run --dart-define=API_BASE=https://3alraf.com --dart-define=ENV=dev`
class AppConfig {
  const AppConfig({
    required this.apiBase,
    required this.googleServerClientId,
    required this.env,
  });

  /// Reads the values compiled in with `--dart-define`.
  factory AppConfig.fromEnvironment() => const AppConfig(
    apiBase: String.fromEnvironment('API_BASE', defaultValue: defaultApiBase),
    googleServerClientId: String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID'),
    env: String.fromEnvironment('ENV', defaultValue: 'prod'),
  );

  static const String defaultApiBase = 'https://3alraf.com';

  /// The store's single host (`APP_URL`, no trailing slash needed). Every API
  /// call, image and share link uses it.
  final String apiBase;
  final String googleServerClientId;
  final String env;

  bool get isProd => env == 'prod';

  /// Base URL of the versioned mobile API.
  String get apiV1 => '${apiBase.replaceAll(RegExp(r'/+$'), '')}/api/v1';
}
