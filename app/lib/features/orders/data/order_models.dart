import '../../../core/network/json.dart';

class OrderItem {
  const OrderItem({
    required this.id,
    required this.name,
    required this.sku,
    required this.unitPrice,
    required this.quantity,
    required this.lineSubtotal,
    required this.lineDiscount,
    required this.returnedQty,
    this.bundleName,
  });

  factory OrderItem.fromJson(Map<String, Object?> j) => OrderItem(
    id: intOf(j['id']),
    name: str(j['name']),
    sku: strOrNull(j['sku']),
    unitPrice: str(j['unit_price'], '0.00'),
    quantity: intOf(j['quantity'], 1),
    lineSubtotal: str(j['line_subtotal'], '0.00'),
    lineDiscount: str(j['line_discount'], '0.00'),
    returnedQty: intOf(j['returned_qty']),
    bundleName: strOrNull(j['bundle_name']),
  );

  final int id;
  final String name;
  final String? sku;
  final String unitPrice;
  final int quantity;
  final String lineSubtotal;
  final String lineDiscount;
  final int returnedQty;
  final String? bundleName;

  /// Units that can still be returned.
  int get returnable => (quantity - returnedQty).clamp(0, quantity);
}

class TimelineEntry {
  const TimelineEntry({required this.status, this.note, this.at});

  factory TimelineEntry.fromJson(Map<String, Object?> j) => TimelineEntry(
    status: str(j['status']),
    note: strOrNull(j['note']),
    at: dateOrNull(j['at']),
  );

  final String status;
  final String? note;
  final DateTime? at;
}

class ReturnInfo {
  const ReturnInfo({
    required this.number,
    required this.status,
    this.reason,
    this.refundAmount,
  });

  factory ReturnInfo.fromJson(Map<String, Object?> j) => ReturnInfo(
    number: str(j['number']),
    status: str(j['status']),
    reason: strOrNull(j['reason']),
    refundAmount: strOrNull(j['refund_amount']),
  );

  final String number;
  final String status;
  final String? reason;
  final String? refundAmount;
}

/// An order. The list endpoint returns the summary subset (no items); detail,
/// placement and payment-return return everything. Money fields are the
/// server strings.
class Order {
  const Order({
    required this.number,
    required this.token,
    required this.statusValue,
    required this.statusLabel,
    required this.paymentStatus,
    required this.proofStatus,
    required this.paymentMethodName,
    required this.paymentMethodType,
    required this.subtotal,
    required this.discount,
    required this.shipping,
    required this.tax,
    required this.paymentFee,
    required this.total,
    required this.paidAmount,
    required this.due,
    required this.codFee,
    required this.codFeeLabel,
    required this.codAmount,
    required this.prepaidShipping,
    required this.currency,
    required this.packagingFee,
    required this.packagingName,
    required this.placedAt,
    required this.itemsCount,
    this.customerName,
    this.city,
    this.address,
    this.notes,
    this.shippingCompany,
    this.hasProof = false,
    this.payNow,
    this.canPayOnline = false,
    this.canUploadProof = false,
    this.items = const [],
    this.timeline = const [],
    this.returns = const [],
  });

  factory Order.fromJson(Map<String, Object?> j) {
    final status = asMap(j['status']);
    final method = asMap(j['payment_method']);
    return Order(
      number: str(j['number']),
      token: str(j['token']),
      statusValue: str(status['value']),
      statusLabel: str(status['label'], str(status['value'])),
      paymentStatus: str(j['payment_status']),
      proofStatus: strOrNull(j['proof_status']),
      paymentMethodName: strOrNull(method['name']),
      paymentMethodType: strOrNull(method['type']),
      subtotal: str(j['subtotal'], '0.00'),
      discount: str(j['discount'], '0.00'),
      shipping: str(j['shipping'], '0.00'),
      tax: str(j['tax'], '0.00'),
      paymentFee: str(j['payment_fee'], '0.00'),
      total: str(j['total'], '0.00'),
      paidAmount: str(j['paid_amount'], '0.00'),
      due: str(j['due'], '0.00'),
      codFee: str(j['cod_fee'], '0.00'),
      codFeeLabel: strOrNull(j['cod_fee_label']),
      codAmount: str(j['cod_amount'], '0.00'),
      prepaidShipping: str(j['prepaid_shipping'], '0.00'),
      currency: str(j['currency']),
      packagingFee: str(j['packaging_fee'], '0.00'),
      packagingName: strOrNull(j['packaging_name']),
      placedAt: dateOrNull(j['placed_at']),
      itemsCount: intOrNull(j['items_count']),
      customerName: strOrNull(asMap(j['customer'])['name']),
      city: strOrNull(j['city']),
      address: strOrNull(j['address']),
      notes: strOrNull(j['notes']),
      shippingCompany: strOrNull(j['shipping_company']),
      hasProof: boolOf(j['has_proof']),
      payNow: strOrNull(j['pay_now']),
      canPayOnline: boolOf(j['can_pay_online']),
      canUploadProof: boolOf(j['can_upload_proof']),
      items: asMapList(j['items']).map(OrderItem.fromJson).toList(),
      timeline: asMapList(j['timeline']).map(TimelineEntry.fromJson).toList(),
      returns: asMapList(j['returns']).map(ReturnInfo.fromJson).toList(),
    );
  }

  final String number;
  final String token;
  final String statusValue;
  final String statusLabel;
  final String paymentStatus;
  final String? proofStatus;
  final String? paymentMethodName;
  final String? paymentMethodType;
  final String subtotal;
  final String discount;
  final String shipping;
  final String tax;
  final String paymentFee;
  final String total;
  final String paidAmount;
  final String due;
  final String codFee;
  final String? codFeeLabel;
  final String codAmount;
  final String prepaidShipping;
  final String currency;
  final String packagingFee;
  final String? packagingName;
  final DateTime? placedAt;
  final int? itemsCount;
  final String? customerName;
  final String? city;
  final String? address;
  final String? notes;
  final String? shippingCompany;
  final bool hasProof;
  final String? payNow;
  final bool canPayOnline;
  final bool canUploadProof;
  final List<OrderItem> items;
  final List<TimelineEntry> timeline;
  final List<ReturnInfo> returns;

  bool get isPaid => paymentStatus == 'paid';

  /// Cancellation is offered while the order is still before shipping; the
  /// server makes the final call (422 / 404 when not allowed).
  bool get canCancel =>
      const {'pending', 'confirmed', 'processing'}.contains(statusValue);

  bool get canReturn => statusValue == 'delivered';
}

/// `next_action` after placing an order.
enum NextAction { none, redirectToPayment, retryPayment, uploadProof }

NextAction nextActionOf(String? v) => switch (v) {
  'redirect_to_payment' => NextAction.redirectToPayment,
  'retry_payment' => NextAction.retryPayment,
  'upload_proof' => NextAction.uploadProof,
  _ => NextAction.none,
};

class PlacedOrder {
  const PlacedOrder({
    required this.order,
    required this.nextAction,
    this.paymentUrl,
  });

  final Order order;
  final NextAction nextAction;
  final String? paymentUrl;
}

/// Result of `GET payments/return/{token}`.
class PaymentReturn {
  const PaymentReturn({required this.order, required this.paid});

  final Order order;
  final bool paid;
}

class OrdersPageData {
  const OrdersPageData({
    required this.orders,
    required this.currentPage,
    required this.lastPage,
  });

  final List<Order> orders;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

/// Structured cancellation / return reason codes (server list).
const orderReasonCodes = [
  'color_differs',
  'size_unclear',
  'damaged_packaging',
  'delivery_delay',
  'description_inaccurate',
  'quality',
  'wrong_item',
  'changed_mind',
  'other',
];
