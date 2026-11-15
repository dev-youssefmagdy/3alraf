import 'package:flutter/material.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
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
  codAmount: o.codAmount,
  prepaidShipping: o.prepaidShipping,
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

/// Vertical order timeline (`21:1935`) over the server's status history,
/// oldest first: past entries are emerald checks, the latest one is the
/// current pop step. Connectors are emerald between done steps.
class OrderTimeline extends StatelessWidget {
  const OrderTimeline({super.key, required this.entries});

  final List<TimelineEntry> entries;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final c = context.colors;
    // The server sends the history oldest first.
    final list = entries;
    return Column(
      children: [
        for (var i = 0; i < list.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 32,
                  child: Column(
                    children: [
                      _dot(context, i, i == list.length - 1),
                      if (i < list.length - 1)
                        Expanded(
                          child: Container(
                            width: 3,
                            margin: const EdgeInsets.symmetric(vertical: 2),
                            decoration: BoxDecoration(
                              color: x.success,
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: AppSpacing.smd),
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      top: 4,
                      bottom: i < list.length - 1 ? AppSpacing.md : 0,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          list[i].status,
                          style: context.text.titleSmall?.copyWith(
                            color: i == list.length - 1 ? x.pop : null,
                          ),
                        ),
                        Text(
                          [
                            formatDate(list[i].at),
                            if (list[i].note != null) list[i].note!,
                          ].where((e) => e.isNotEmpty).join(' · '),
                          style: context.text.bodySmall?.copyWith(
                            color: c.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _dot(BuildContext context, int i, bool current) {
    final x = context.extra;
    return Container(
      width: 32,
      height: 32,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: current ? x.pop : x.success,
        border: current ? Border.all(color: x.popSoft, width: 3) : null,
      ),
      child: current
          ? Text(
              '${i + 1}',
              style: context.textX.labelXs.copyWith(color: Colors.white),
            )
          : const Icon(Icons.check_rounded, size: 18, color: Colors.white),
    );
  }
}
