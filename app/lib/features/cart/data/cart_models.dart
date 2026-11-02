import '../../../core/network/json.dart';

/// True when a server money string is zero/empty. Used ONLY to decide
/// whether to show a totals row; no arithmetic is ever done on money.
bool isZeroMoney(String? v) {
  if (v == null || v.isEmpty) return true;
  final d = double.tryParse(v);
  return d == null || d == 0;
}

class CartComponent {
  const CartComponent({required this.name, required this.quantity});

  factory CartComponent.fromJson(Map<String, Object?> j) => CartComponent(
    name: str(j['name'] ?? j['product_name']),
    quantity: intOf(j['quantity'], 1),
  );

  final String name;
  final int quantity;
}

class CartLine {
  const CartLine({
    required this.id,
    required this.productId,
    required this.variantId,
    required this.bundleId,
    required this.isBundle,
    required this.components,
    required this.quantity,
    required this.name,
    required this.slug,
    required this.image,
    required this.unitPrice,
    required this.available,
  });

  factory CartLine.fromJson(Map<String, Object?> j) => CartLine(
    id: intOf(j['id']),
    productId: intOrNull(j['product_id']),
    variantId: intOf(j['variant_id']),
    bundleId: intOrNull(j['bundle_id']),
    isBundle: boolOf(j['is_bundle']),
    components: asMapList(j['components']).map(CartComponent.fromJson).toList(),
    quantity: intOf(j['quantity'], 1),
    name: str(j['name']),
    slug: strOrNull(j['slug']),
    image: strOrNull(j['image']),
    unitPrice: str(j['unit_price'], '0.00'),
    available: j['available'] == null ? true : boolOf(j['available']),
  );

  final int id;
  final int? productId;
  final int variantId;
  final int? bundleId;
  final bool isBundle;
  final List<CartComponent> components;
  final int quantity;
  final String name;
  final String? slug;
  final String? image;
  final String unitPrice;
  final bool available;

  CartLine withQuantity(int q) => CartLine(
    id: id,
    productId: productId,
    variantId: variantId,
    bundleId: bundleId,
    isBundle: isBundle,
    components: components,
    quantity: q,
    name: name,
    slug: slug,
    image: image,
    unitPrice: unitPrice,
    available: available,
  );
}

/// Server-computed totals, kept as the strings the API sent.
class CartTotals {
  const CartTotals({
    this.subtotal = '0.00',
    this.discount = '0.00',
    this.shipping = '0.00',
    this.shippingDiscount = '0.00',
    this.shippingNet = '0.00',
    this.tax = '0.00',
    this.paymentFee = '0.00',
    this.total = '0.00',
    this.codFee = '0.00',
    this.codFeeLabel,
    this.packagingFee = '0.00',
    this.codAmount = '0.00',
    this.prepaidShipping = '0.00',
  });

  factory CartTotals.fromJson(Map<String, Object?> j) => CartTotals(
    subtotal: str(j['subtotal'], '0.00'),
    discount: str(j['discount'], '0.00'),
    shipping: str(j['shipping'], '0.00'),
    shippingDiscount: str(j['shipping_discount'], '0.00'),
    shippingNet: str(j['shipping_net'], '0.00'),
    tax: str(j['tax'], '0.00'),
    paymentFee: str(j['payment_fee'], '0.00'),
    total: str(j['total'], '0.00'),
    codFee: str(j['cod_fee'], '0.00'),
    codFeeLabel: strOrNull(j['cod_fee_label']),
    packagingFee: str(j['packaging_fee'], '0.00'),
    codAmount: str(j['cod_amount'], '0.00'),
    prepaidShipping: str(j['prepaid_shipping'], '0.00'),
  );

  final String subtotal;
  final String discount;
  final String shipping;
  final String shippingDiscount;
  final String shippingNet;
  final String tax;
  final String paymentFee;
  final String total;
  final String codFee;
  final String? codFeeLabel;
  final String packagingFee;
  final String codAmount;
  final String prepaidShipping;
}

class Cart {
  const Cart({
    this.items = const [],
    this.totals = const CartTotals(),
    this.packagingName,
    this.hasFlash = false,
  });

  factory Cart.fromJson(Map<String, Object?> j) {
    final pack = j['packaging'];
    return Cart(
      items: asMapList(j['items']).map(CartLine.fromJson).toList(),
      totals: CartTotals.fromJson(asMap(j['totals'])),
      packagingName: pack is Map ? strOrNull(asMap(pack)['name']) : null,
      hasFlash: boolOf(j['has_flash']),
    );
  }

  final List<CartLine> items;
  final CartTotals totals;
  final String? packagingName;
  final bool hasFlash;

  bool get isEmpty => items.isEmpty;

  /// Badge number: units in the cart (a count, not money).
  int get count => items.fold(0, (n, l) => n + l.quantity);

  /// True when some line cannot be bought any more.
  bool get hasUnavailable => items.any((l) => !l.available);

  Cart withItems(List<CartLine> next) => Cart(
    items: next,
    totals: totals,
    packagingName: packagingName,
    hasFlash: hasFlash,
  );
}
