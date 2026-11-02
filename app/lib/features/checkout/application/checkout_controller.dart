import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/locale_provider.dart';
import '../../../core/network/network_providers.dart';
import '../../addresses/application/address_controller.dart';
import '../../addresses/data/address_models.dart';
import '../../auth/application/auth_controller.dart';
import '../../cart/application/cart_controller.dart';
import '../../cart/data/cart_models.dart';
import '../../orders/data/order_models.dart';
import '../../tenant/application/tenant_controller.dart';
import '../data/checkout_models.dart';
import '../data/checkout_repository.dart';

final checkoutRepositoryProvider = Provider<CheckoutRepository>(
  (ref) => CheckoutRepository(ref.watch(dioProvider)),
);

class CheckoutState {
  const CheckoutState({
    this.step = CheckoutStep.address,
    this.addressId,
    this.cityId,
    this.shippingId,
    this.packagingId,
    this.paymentId,
    this.prepayId,
    this.proofPath,
    this.notes = '',
    this.shippingOptions = const [],
    this.packaging = const [],
    this.payments = const PaymentMethods(),
    this.loadingOptions = true,
    this.loadingShipping = false,
    this.optionsError,
    this.quote,
    this.working = false,
    this.errors = const {},
    this.message,
    this.cartProblem = false,
  });

  final CheckoutStep step;
  final int? addressId;
  final int? cityId;
  final int? shippingId;
  final int? packagingId;
  final int? paymentId;
  final int? prepayId;
  final String? proofPath;
  final String notes;
  final List<ShippingOption> shippingOptions;
  final List<PackagingOption> packaging;
  final PaymentMethods payments;
  final bool loadingOptions;
  final bool loadingShipping;
  final ApiException? optionsError;

  /// Server quote for the current choices (totals shown in the summary).
  final Cart? quote;
  final bool working;

  /// 422 field errors from validate / place.
  final Map<String, List<String>> errors;

  /// Non-field failure text (network, 429...), shown once.
  final String? message;

  /// A 422 about the cart itself (discount code / stock): go back to the cart.
  final bool cartProblem;

  PaymentMethod? get payment {
    for (final m in payments.methods) {
      if (m.id == paymentId) return m;
    }
    return null;
  }

  /// Steps shown in the stepper (packaging only when the store has options).
  List<CheckoutStep> get steps => [
    CheckoutStep.address,
    CheckoutStep.shipping,
    if (packaging.isNotEmpty) CheckoutStep.packaging,
    CheckoutStep.payment,
    CheckoutStep.review,
  ];

  bool get needsPrepay =>
      (payment?.isCod ?? false) && payments.codCollectShippingUpfront;

  PaymentMethod? get prepay {
    for (final m in payments.methods) {
      if (m.id == prepayId) return m;
    }
    return null;
  }

  bool get proofRequired =>
      (payment?.requiresProof ?? false) ||
      (needsPrepay && (prepay?.requiresProof ?? false));

  String? errorFor(String field) {
    final l = errors[field];
    return (l == null || l.isEmpty) ? null : l.first;
  }

  /// Whether the shopper may leave [s] going forward.
  bool canLeave(CheckoutStep s) => switch (s) {
    CheckoutStep.address => addressId != null,
    CheckoutStep.shipping => shippingId != null,
    CheckoutStep.packaging => true,
    CheckoutStep.payment =>
      paymentId != null &&
          (!needsPrepay || prepayId != null) &&
          (!proofRequired || proofPath != null),
    CheckoutStep.review => true,
  };

  CheckoutSelection selection({String? discountCode}) => CheckoutSelection(
    addressId: addressId,
    cityId: cityId,
    shippingCompanyId: shippingId,
    paymentMethodId: paymentId,
    prepayMethodId: needsPrepay ? prepayId : null,
    packagingOptionId: packagingId,
    discountCode: discountCode,
    notes: notes,
    proofPath: proofRequired ? proofPath : null,
  );

  CheckoutState copyWith({
    CheckoutStep? step,
    Object? addressId = _keep,
    Object? cityId = _keep,
    Object? shippingId = _keep,
    Object? packagingId = _keep,
    Object? paymentId = _keep,
    Object? prepayId = _keep,
    Object? proofPath = _keep,
    String? notes,
    List<ShippingOption>? shippingOptions,
    List<PackagingOption>? packaging,
    PaymentMethods? payments,
    bool? loadingOptions,
    bool? loadingShipping,
    Object? optionsError = _keep,
    Cart? quote,
    bool? working,
    Map<String, List<String>>? errors,
    Object? message = _keep,
    bool? cartProblem,
  }) => CheckoutState(
    step: step ?? this.step,
    addressId: identical(addressId, _keep) ? this.addressId : addressId as int?,
    cityId: identical(cityId, _keep) ? this.cityId : cityId as int?,
    shippingId: identical(shippingId, _keep)
        ? this.shippingId
        : shippingId as int?,
    packagingId: identical(packagingId, _keep)
        ? this.packagingId
        : packagingId as int?,
    paymentId: identical(paymentId, _keep) ? this.paymentId : paymentId as int?,
    prepayId: identical(prepayId, _keep) ? this.prepayId : prepayId as int?,
    proofPath: identical(proofPath, _keep)
        ? this.proofPath
        : proofPath as String?,
    notes: notes ?? this.notes,
    shippingOptions: shippingOptions ?? this.shippingOptions,
    packaging: packaging ?? this.packaging,
    payments: payments ?? this.payments,
    loadingOptions: loadingOptions ?? this.loadingOptions,
    loadingShipping: loadingShipping ?? this.loadingShipping,
    optionsError: identical(optionsError, _keep)
        ? this.optionsError
        : optionsError as ApiException?,
    quote: quote ?? this.quote,
    working: working ?? this.working,
    errors: errors ?? this.errors,
    message: identical(message, _keep) ? this.message : message as String?,
    cartProblem: cartProblem ?? this.cartProblem,
  );
}

const Object _keep = Object();

final checkoutControllerProvider =
    NotifierProvider.autoDispose<CheckoutController, CheckoutState>(
      CheckoutController.new,
    );

/// Drives the checkout stepper. Every total shown comes from `cart/quote`
/// or `checkout/validate`; this class only holds the shopper's choices.
class CheckoutController extends Notifier<CheckoutState> {
  int _gen = 0;

  CheckoutRepository get _repo => ref.read(checkoutRepositoryProvider);

  @override
  CheckoutState build() {
    ref.watch(tenantSlugProvider);
    ref.watch(localeProvider);
    ref.watch(authControllerProvider.select((s) => s.signedIn));
    _gen++;
    Future.microtask(_init);
    return const CheckoutState();
  }

  bool _stale(int gen) => !ref.mounted || gen != _gen;

  String? get _code => ref.read(cartControllerProvider).code;

  Future<void> _init() async {
    final gen = _gen;
    try {
      final results = await Future.wait([
        _repo.packaging(),
        _repo.paymentMethods(),
      ]);
      await ref.read(addressesProvider.notifier).load();
      if (_stale(gen)) return;
      final packaging = results[0] as List<PackagingOption>;
      final payments = results[1] as PaymentMethods;
      state = state.copyWith(
        packaging: packaging,
        payments: payments,
        loadingOptions: false,
        optionsError: null,
      );
      final def = ref.read(addressesProvider).defaultAddress;
      if (def != null) await selectAddress(def);
    } on ApiException catch (e) {
      if (_stale(gen)) return;
      state = state.copyWith(loadingOptions: false, optionsError: e);
    }
  }

  Future<void> retryInit() {
    state = state.copyWith(loadingOptions: true, optionsError: null);
    return _init();
  }

  Future<void> selectAddress(Address a) async {
    final gen = _gen;
    state = state.copyWith(
      addressId: a.id,
      cityId: a.cityId,
      shippingId: null,
      shippingOptions: const [],
      loadingShipping: true,
      errors: const {},
    );
    try {
      final options = await _repo.shipping(a.cityId);
      if (_stale(gen) || state.addressId != a.id) return;
      state = state.copyWith(
        shippingOptions: options,
        loadingShipping: false,
        // A single option is preselected.
        shippingId: options.length == 1 ? options.first.id : null,
      );
    } on ApiException catch (e) {
      if (_stale(gen)) return;
      state = state.copyWith(loadingShipping: false, optionsError: e);
      return;
    }
    await _requote();
  }

  Future<void> selectShipping(int id) async {
    state = state.copyWith(shippingId: id, errors: const {});
    await _requote();
  }

  Future<void> selectPackaging(int? id) async {
    state = state.copyWith(packagingId: id, errors: const {});
    await _requote();
  }

  Future<void> selectPayment(int id) async {
    state = state.copyWith(
      paymentId: id,
      prepayId: null,
      proofPath: null,
      errors: const {},
    );
    await _requote();
  }

  Future<void> selectPrepay(int id) async {
    state = state.copyWith(prepayId: id, errors: const {});
    await _requote();
  }

  void setProof(String? path) =>
      state = state.copyWith(proofPath: path, errors: const {});

  void setNotes(String v) => state = state.copyWith(notes: v);

  void goTo(CheckoutStep s) => state = state.copyWith(step: s, message: null);

  /// Moves to the next step when the current one is complete.
  void next() {
    if (!state.canLeave(state.step)) return;
    final steps = state.steps;
    final i = steps.indexOf(state.step);
    if (i < 0 || i >= steps.length - 1) return;
    final target = steps[i + 1];
    state = state.copyWith(step: target, message: null);
    if (target == CheckoutStep.review) validate();
  }

  void back() {
    final steps = state.steps;
    final i = steps.indexOf(state.step);
    if (i > 0) state = state.copyWith(step: steps[i - 1], message: null);
  }

  void consumeMessage() => state = state.copyWith(message: null);

  Future<void> _requote() async {
    final gen = _gen;
    try {
      final q = await _repo.quote(state.selection(discountCode: _code));
      if (_stale(gen)) return;
      state = state.copyWith(quote: q);
    } on ApiException {
      // Live totals are best effort; validate() reports real problems.
    }
  }

  void _fail(ApiException e) {
    if (e.kind == ApiErrorKind.validation) {
      final target = stepForErrors(e.errors);
      state = state.copyWith(
        working: false,
        errors: e.errors,
        message: e.errors.isEmpty ? e.message : null,
        cartProblem: target == null,
        step: target ?? state.step,
      );
    } else {
      state = state.copyWith(working: false, message: e.message);
    }
  }

  /// `checkout/validate`; returns true when the order can be placed.
  Future<bool> validate() async {
    final gen = _gen;
    state = state.copyWith(working: true, errors: const {}, message: null);
    try {
      final cart = await _repo.validate(state.selection(discountCode: _code));
      if (_stale(gen)) return false;
      state = state.copyWith(working: false, quote: cart);
      return true;
    } on ApiException catch (e) {
      if (!_stale(gen)) _fail(e);
      return false;
    }
  }

  /// `POST checkout`. Null on failure (state carries the errors).
  Future<PlacedOrder?> place() async {
    final gen = _gen;
    state = state.copyWith(working: true, errors: const {}, message: null);
    try {
      final placed = await _repo.place(state.selection(discountCode: _code));
      if (!ref.mounted) return placed;
      state = state.copyWith(working: false);
      ref.read(cartControllerProvider.notifier).clearLocal();
      return placed;
    } on ApiException catch (e) {
      if (!_stale(gen)) _fail(e);
      return null;
    }
  }
}
