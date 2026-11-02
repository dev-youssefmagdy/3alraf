import '../../../core/l10n/app_localizations.dart';
import '../../../core/widgets/widgets.dart';
import '../../cart/data/cart_models.dart';
import '../data/order_models.dart';

/// Order amounts re-packaged for the shared totals card (no arithmetic).
CartTotals orderTotals(Order o) => CartTotals(
  subtotal: o.subtotal,
  discount: o.discount,
  shippingNet: o.shipping,
  tax: o.tax,
  paymentFee: o.paymentFee,
  total: o.total,
  codFee: o.codFee,
  codFeeLabel: o.codFeeLabel,
  packagingFee: o.packagingFee,
);

StatusTone statusTone(String status) => switch (status) {
  'delivered' => StatusTone.success,
  'cancelled' || 'returned' => StatusTone.danger,
  'pending' => StatusTone.warning,
  _ => StatusTone.brand,
};

String paymentStatusLabel(AppLocalizations l10n, String v) => switch (v) {
  'unpaid' => l10n.payUnpaid,
  'paid' => l10n.payPaid,
  'partially_paid' || 'partial' => l10n.payPartial,
  'refunded' => l10n.payRefunded,
  _ => v,
};

String proofStatusLabel(AppLocalizations l10n, String v) => switch (v) {
  'pending' => l10n.proofPending,
  'approved' => l10n.proofApproved,
  'rejected' => l10n.proofRejected,
  _ => v,
};

String reasonLabel(AppLocalizations l10n, String code) => switch (code) {
  'color_differs' => l10n.reasonColorDiffers,
  'size_unclear' => l10n.reasonSizeUnclear,
  'damaged_packaging' => l10n.reasonDamagedPackaging,
  'delivery_delay' => l10n.reasonDeliveryDelay,
  'description_inaccurate' => l10n.reasonDescriptionInaccurate,
  'quality' => l10n.reasonQuality,
  'wrong_item' => l10n.reasonWrongItem,
  'changed_mind' => l10n.reasonChangedMind,
  _ => l10n.reasonOther,
};

String formatDate(DateTime? d) {
  if (d == null) return '';
  final l = d.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${l.year}-${two(l.month)}-${two(l.day)} ${two(l.hour)}:${two(l.minute)}';
}
