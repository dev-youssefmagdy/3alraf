import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/config_providers.dart';
import '../storage/storage_providers.dart';
import 'api_client.dart';
import 'api_context.dart';

final apiContextProvider = Provider<ApiContext>((ref) => ApiContext());

/// Ticks when the customer session was invalidated by a 401; the router
/// listens to it to route back to login.
final unauthorizedEventsProvider = NotifierProvider<UnauthorizedNotifier, int>(
  UnauthorizedNotifier.new,
);

class UnauthorizedNotifier extends Notifier<int> {
  @override
  int build() => 0;

  void notify() => state++;
}

final dioProvider = Provider<Dio>((ref) {
  final dio = buildDio(
    config: ref.watch(appConfigProvider),
    context: ref.watch(apiContextProvider),
    storage: ref.watch(sessionStorageProvider),
    onUnauthorized: () =>
        ref.read(unauthorizedEventsProvider.notifier).notify(),
  );
  ref.onDispose(dio.close);
  return dio;
});
