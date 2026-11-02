import 'package:google_sign_in/google_sign_in.dart';

/// Obtains a Google ID token for `POST /auth/google`. Behind an interface so
/// tests (and builds without Google configured) can swap it.
abstract class GoogleIdTokenProvider {
  /// Whether Google sign-in is configured in this build.
  bool get isAvailable;

  /// Returns the ID token, or `null` when the user cancelled.
  Future<String?> idToken();
}

class GoogleSignInService implements GoogleIdTokenProvider {
  GoogleSignInService(this.serverClientId);

  final String serverClientId;
  Future<void>? _init;

  @override
  bool get isAvailable => serverClientId.isNotEmpty;

  @override
  Future<String?> idToken() async {
    final gsi = GoogleSignIn.instance;
    await (_init ??= gsi.initialize(serverClientId: serverClientId));
    try {
      final account = await gsi.authenticate();
      return account.authentication.idToken;
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled ||
          e.code == GoogleSignInExceptionCode.interrupted) {
        return null;
      }
      rethrow;
    }
  }
}
