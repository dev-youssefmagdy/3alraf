import 'dart:io';

Map<String, Object?> lineJson(
  int id, {
  int quantity = 1,
  String name = 'Serum',
  String price = '100.00',
  bool available = true,
}) => {
  'id': id,
  'product_id': id + 10,
  'variant_id': 0,
  'bundle_id': null,
  'is_bundle': false,
  'components': <Object>[],
  'quantity': quantity,
  'name': name,
  'slug': 'serum-$id',
  'image': null,
  'unit_price': price,
  'available': available,
};

Map<String, Object?> totalsJson({
  String subtotal = '200.00',
  String discount = '0.00',
  String shipping = '0.00',
  String total = '200.00',
}) => {
  'subtotal': subtotal,
  'discount': discount,
  'shipping': shipping,
  'shipping_discount': '0.00',
  'shipping_net': shipping,
  'tax': '0.00',
  'payment_fee': '0.00',
  'total': total,
  'cod_fee': '0.00',
  'cod_fee_label': 'COD fee',
  'packaging_fee': '0.00',
  'cod_amount': '0.00',
  'prepaid_shipping': '0.00',
};

Map<String, Object?> cartJson({
  List<Map<String, Object?>>? items,
  Map<String, Object?>? totals,
}) => {
  'items': items ?? [lineJson(1, quantity: 2)],
  'packaging': null,
  'has_flash': false,
  'totals': totals ?? totalsJson(),
};

Map<String, Object?> orderJson({
  String token = 'tok-1',
  String number = 'ORD-000001',
  String status = 'pending',
  String paymentStatus = 'unpaid',
  bool canPayOnline = false,
  bool canUploadProof = false,
  String total = '340.00',
}) => {
  'number': number,
  'token': token,
  'status': {'value': status, 'label': status},
  'payment_status': paymentStatus,
  'proof_status': null,
  'payment_method': {'id': 1, 'type': 'cod', 'name': 'Cash on delivery'},
  'subtotal': '300.00',
  'discount': '0.00',
  'shipping': '40.00',
  'tax': '0.00',
  'payment_fee': '0.00',
  'total': total,
  'paid_amount': '0.00',
  'due': total,
  'cod_fee': '0.00',
  'cod_fee_label': null,
  'cod_amount': total,
  'prepaid_shipping': '0.00',
  'currency': 'EGP',
  'packaging_fee': '0.00',
  'packaging_name': null,
  'placed_at': '2026-11-01T23:51:45+02:00',
  'items_count': 3,
  'customer': {'name': 'Ali'},
  'city': 'Cairo',
  'address': 'St 1',
  'notes': null,
  'shipping_company': 'Fast Shipping',
  'has_proof': false,
  'pay_now': total,
  'can_pay_online': canPayOnline,
  'can_upload_proof': canUploadProof,
  'items': [
    {
      'id': 1,
      'name': 'P cheap',
      'sku': 'CHEAP',
      'unit_price': '100.00',
      'quantity': 3,
      'line_subtotal': '300.00',
      'line_discount': '0.00',
      'returned_qty': 1,
    },
  ],
  'timeline': [
    {'status': 'pending', 'note': null, 'at': '2026-11-01T23:51:45+02:00'},
  ],
  'returns': <Object>[],
};

Map<String, Object?> addressJson(int id, {int cityId = 1, bool def = false}) =>
    {
      'id': id,
      'label': 'Home',
      'name': 'Ali',
      'phone': '01000000100',
      'phone_full': '+201000000100',
      'city_id': cityId,
      'city': 'Cairo',
      'address': 'St $id',
      'notes': null,
      'is_default': def,
    };

/// Writes a small temp file and returns its path.
Future<String> writeTemp(String name) async {
  final dir = await Directory.systemTemp.createTemp('alraf_test');
  final f = File('${dir.path}/$name');
  await f.writeAsBytes(const [1, 2, 3]);
  return f.path;
}
