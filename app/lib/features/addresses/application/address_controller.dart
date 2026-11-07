import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../data/address_models.dart';
import '../data/address_repository.dart';

final addressRepositoryProvider = Provider<AddressRepository>(
  (ref) => AddressRepository(ref.watch(dioProvider)),
);

/// Cities of the active store (address form + checkout).
final citiesProvider = FutureProvider<List<City>>((ref) {
  ref.watch(localeProvider);
  return ref.watch(addressRepositoryProvider).cities();
});

class AddressesState {
  const AddressesState({
    this.items = const [],
    this.loading = false,
    this.loaded = false,
    this.error,
  });

  final List<Address> items;
  final bool loading;
  final bool loaded;
  final ApiException? error;

  Address? get defaultAddress {
    for (final a in items) {
      if (a.isDefault) return a;
    }
    return items.isEmpty ? null : items.first;
  }
}

final addressesProvider = NotifierProvider<AddressController, AddressesState>(
  AddressController.new,
);

/// Saved addresses of the signed-in customer; emptied on locale change and
/// sign-out.
class AddressController extends Notifier<AddressesState> {
  AddressRepository get _repo => ref.read(addressRepositoryProvider);

  @override
  AddressesState build() {
    ref.watch(localeProvider);
    final signedIn = ref.watch(
      authControllerProvider.select((s) => s.signedIn),
    );
    if (!signedIn) return const AddressesState();
    Future.microtask(load);
    return const AddressesState(loading: true);
  }

  Future<void> load() async {
    state = AddressesState(
      items: state.items,
      loading: true,
      loaded: state.loaded,
    );
    try {
      final items = await _repo.list();
      if (!ref.mounted) return;
      state = AddressesState(items: items, loaded: true);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      state = AddressesState(
        items: state.items,
        loaded: state.loaded,
        error: e,
      );
    }
  }

  /// Throws [ApiException] (422 field errors) for the form.
  Future<Address> save(AddressInput input, {int? id}) async {
    final saved = id == null
        ? await _repo.create(input)
        : await _repo.update(id, input);
    await load();
    return saved;
  }

  Future<ApiException?> remove(Address a) async {
    try {
      await _repo.delete(a.id);
      await load();
      return null;
    } on ApiException catch (e) {
      return e;
    }
  }
}
