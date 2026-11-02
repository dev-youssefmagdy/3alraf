import 'dart:async';
import 'dart:ui' show Locale;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/config_providers.dart';
import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/storage/storage_providers.dart';
import '../../push/application/push_registrar.dart';
import '../data/auth_models.dart';
import '../data/auth_repository.dart';
import '../data/google_sign_in_service.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(dioProvider)),
);

final googleIdTokenProvider = Provider<GoogleIdTokenProvider>(
  (ref) =>
      GoogleSignInService(ref.watch(appConfigProvider).googleServerClientId),
);

class AuthState {
  const AuthState({this.signedIn = false, this.customer});

  final bool signedIn;
  final Customer? customer;

  static const signedOut = AuthState();
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    ref.listen<Locale>(localeProvider, (prev, next) {
      if (prev?.languageCode != next.languageCode && state.signedIn) {
        unawaited(
          ref.read(pushRegistrarProvider).onLocaleChanged(next.languageCode),
        );
      }
    });
    return AuthState.signedOut;
  }

  String? get _slug => ref.read(apiContextProvider).tenantSlug;
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  /// Loads the persisted session of the active tenant. The profile is
  /// fetched in the background; an expired token flips back to signed out
  /// through the 401 handler.
  Future<void> restore() async {
    final slug = _slug;
    if (slug == null) {
      state = AuthState.signedOut;
      return;
    }
    final token = await ref.read(sessionStorageProvider).authToken(slug);
    if (token == null || token.isEmpty) {
      state = AuthState.signedOut;
      return;
    }
    state = const AuthState(signedIn: true);
    try {
      final me = await _repo.me();
      if (_slug == slug) {
        state = AuthState(signedIn: true, customer: me);
        unawaited(ref.read(pushRegistrarProvider).register());
      }
    } on ApiException {
      // 401 is handled by sessionExpired(); other errors keep the session.
    }
  }

  Future<void> login(String email, String password) async {
    await _finish(await _repo.login(email, password));
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    await _finish(
      await _repo.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        phone: phone,
      ),
    );
  }

  /// Returns `false` when the user dismissed the Google dialog.
  Future<bool> loginWithGoogle() async {
    final idToken = await ref.read(googleIdTokenProvider).idToken();
    if (idToken == null || idToken.isEmpty) return false;
    await _finish(await _repo.google(idToken));
    return true;
  }

  /// Persists the token, then merges the guest cart into the customer's
  /// (`cart/merge`). The guest token is dropped once the merge succeeded.
  Future<void> _finish(AuthResult result) async {
    final slug = _slug;
    if (slug == null) return;
    final storage = ref.read(sessionStorageProvider);
    await storage.saveAuthToken(slug, result.token);
    final guest = await storage.cartToken(slug);
    if (guest != null && guest.isNotEmpty) {
      try {
        await _repo.mergeCart();
        await storage.clearCartToken(slug);
      } on ApiException {
        // Keep the guest token; the server may already have adopted it.
      }
    }
    // Flip the session last: the cart (watching it) then loads the merged
    // customer cart instead of racing the merge.
    state = AuthState(signedIn: true, customer: result.customer);
    unawaited(ref.read(pushRegistrarProvider).register());
  }

  Future<void> logout() async {
    final slug = _slug;
    if (slug == null) return;
    await ref.read(pushRegistrarProvider).unregister();
    await _repo.logout();
    await ref.read(sessionStorageProvider).clearAuthToken(slug);
    state = AuthState.signedOut;
  }

  /// `PUT /auth/me`. Pass the password trio to change the password.
  Future<Customer> updateProfile({
    required String name,
    required String email,
    String? phone,
    String? phoneCountryCode,
    String? currentPassword,
    String? password,
    String? passwordConfirmation,
  }) async {
    final customer = await _repo.updateProfile(
      name: name,
      email: email,
      phone: phone,
      phoneCountryCode: phoneCountryCode,
      currentPassword: currentPassword,
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    updateCustomer(customer);
    return customer;
  }

  /// `DELETE /auth/me`, then drops every local trace of the session.
  Future<void> deleteAccount({String? password, bool confirm = false}) async {
    final slug = _slug;
    if (slug == null) return;
    await ref.read(pushRegistrarProvider).unregister();
    await _repo.deleteAccount(password: password, confirm: confirm);
    final storage = ref.read(sessionStorageProvider);
    await storage.clearAuthToken(slug);
    await storage.clearCartToken(slug);
    state = AuthState.signedOut;
  }

  void updateCustomer(Customer customer) =>
      state = AuthState(signedIn: true, customer: customer);

  /// Local-only reset (tenant switch / 401). Secrets are cleared by callers.
  void reset() => state = AuthState.signedOut;

  /// Token was rejected by the server (already removed from storage).
  void sessionExpired() => state = AuthState.signedOut;
}
