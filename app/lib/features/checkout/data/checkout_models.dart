import '../../../core/network/json.dart';

class ShippingOption {
  const ShippingOption({
    required this.id,
    required this.name,
    required this.etaDays,
    required this.supportsCod,
    required this.fee,
    required this.free,
    required this.orderTotal,
  });

  factory ShippingOption.fromJson(Map<String, Object?> j) => ShippingOption(
    id: intOf(j['shipping_company_id']),
    name: str(j['name']),
    etaDays: strOrNull(j['eta_days']),
    supportsCod: boolOf(j['supports_cod']),
    fee: str(j['fee'], '0.00'),
    free: boolOf(j['free']),
    orderTotal: str(j['order_total'], '0.00'),
  );

  final int id;
  final String name;
  final String? etaDays;
  final bool supportsCod;
  final String fee;
  final bool free;
  final String orderTotal;
}

class PackagingOption {
  const PackagingOption({
    required this.id,
    required this.name,
    required this.description,
    required this.image,
    required this.price,
  });

  factory PackagingOption.fromJson(Map<String, Object?> j) => PackagingOption(
    id: intOf(j['id']),
    name: str(j['name']),
    description: strOrNull(j['description']),
    image: strOrNull(j['image']),
    price: str(j['price'], '0.00'),
  );

  final int id;
  final String name;
  final String? description;
  final String? image;
  final String price;
}

class PaymentMethod {
  const PaymentMethod({
    required this.id,
    required this.type,
    required this.provider,
    required this.name,
    required this.instructions,
    required this.accountNumber,
    required this.requiresProof,
    required this.feePercent,
    required this.feeFixed,
  });

  factory PaymentMethod.fromJson(Map<String, Object?> j) => PaymentMethod(
    id: intOf(j['id']),
    type: str(j['type']),
    provider: str(j['provider']),
    name: str(j['name']),
    instructions: strOrNull(j['instructions']),
    accountNumber: strOrNull(j['account_number']),
    requiresProof: boolOf(j['requires_proof']),
    feePercent: str(j['fee_percent'], '0'),
    feeFixed: str(j['fee_fixed'], '0'),
  );

  final int id;
  final String type;
  final String provider;
  final String name;
  final String? instructions;
  final String? accountNumber;
  final bool requiresProof;
  final String feePercent;
  final String feeFixed;

  bool get isCod => type == 'cod';
  bool get isOnline => type == 'online';
}

class PaymentMethods {
  const PaymentMethods({
    this.methods = const [],
    this.codCollectShippingUpfront = false,
  });

  final List<PaymentMethod> methods;
  final bool codCollectShippingUpfront;

  /// Methods that can carry the prepaid-shipping leg of a COD order.
  List<PaymentMethod> get prepayCandidates =>
      methods.where((m) => m.requiresProof).toList();
}

/// The shopper's choices, mapped 1:1 to the `cart/quote`, `checkout/validate`
/// and `checkout` fields. Money is never part of it.
class CheckoutSelection {
  const CheckoutSelection({
    this.addressId,
    this.cityId,
    this.shippingCompanyId,
    this.paymentMethodId,
    this.prepayMethodId,
    this.packagingOptionId,
    this.discountCode,
    this.notes,
    this.proofPath,
  });

  final int? addressId;
  final int? cityId;
  final int? shippingCompanyId;
  final int? paymentMethodId;
  final int? prepayMethodId;
  final int? packagingOptionId;
  final String? discountCode;
  final String? notes;
  final String? proofPath;

  Map<String, Object?> toJson() => {
    'address_id': ?addressId,
    'city_id': ?cityId,
    'shipping_company_id': ?shippingCompanyId,
    'payment_method_id': ?paymentMethodId,
    'prepay_method_id': ?prepayMethodId,
    'packaging_option_id': ?packagingOptionId,
    if (discountCode != null && discountCode!.isNotEmpty)
      'discount_code': discountCode,
    if (notes != null && notes!.trim().isNotEmpty) 'notes': notes!.trim(),
  };
}

/// Checkout steps in order.
enum CheckoutStep { address, shipping, packaging, payment, review }

/// Where to send the shopper for a 422: the earliest step owning a failing
/// field. `null` means the cart itself (discount code / stock).
CheckoutStep? stepForErrors(Map<String, List<String>> errors) {
  if (errors.isEmpty) return CheckoutStep.review;
  const owners = <String, CheckoutStep?>{
    'address_id': CheckoutStep.address,
    'name': CheckoutStep.address,
    'phone': CheckoutStep.address,
    'phone_country_code': CheckoutStep.address,
    'city_id': CheckoutStep.address,
    'address': CheckoutStep.address,
    'shipping_company_id': CheckoutStep.shipping,
    'packaging_option_id': CheckoutStep.packaging,
    'payment_method_id': CheckoutStep.payment,
    'prepay_method_id': CheckoutStep.payment,
    'proof': CheckoutStep.payment,
    'discount_code': null,
  };
  CheckoutStep? best;
  var sawCart = false;
  for (final key in errors.keys) {
    if (!owners.containsKey(key)) {
      best ??= CheckoutStep.review;
      continue;
    }
    final step = owners[key];
    if (step == null) {
      sawCart = true;
      continue;
    }
    if (best == null || step.index < best.index) best = step;
  }
  if (best != null) return best;
  return sawCart ? null : CheckoutStep.review;
}
