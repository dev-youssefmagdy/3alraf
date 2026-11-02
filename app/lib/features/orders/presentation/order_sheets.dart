import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/order_models.dart';
import 'order_widgets.dart';

/// Pops with the chosen reason code.
class CancelSheet extends StatefulWidget {
  const CancelSheet({super.key});

  @override
  State<CancelSheet> createState() => _CancelSheetState();
}

class _CancelSheetState extends State<CancelSheet> {
  String? _code;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.orderCancelTitle, style: context.text.titleLarge),
            Text(l10n.orderCancelReason),
            RadioGroup<String>(
              groupValue: _code,
              onChanged: (v) => setState(() => _code = v),
              child: Column(
                children: [
                  for (final c in orderReasonCodes)
                    RadioListTile<String>(
                      value: c,
                      title: Text(reasonLabel(l10n, c)),
                    ),
                ],
              ),
            ),
            AppButton(
              label: l10n.orderCancel,
              onPressed: _code == null
                  ? null
                  : () => Navigator.pop(context, _code),
            ),
          ],
        ),
      ),
    );
  }
}

class ReturnRequestData {
  const ReturnRequestData(this.quantities, this.reason);

  final Map<int, int> quantities;
  final String? reason;
}

/// Pops with a [ReturnRequestData].
class ReturnSheet extends StatefulWidget {
  const ReturnSheet({super.key, required this.order});

  final Order order;

  @override
  State<ReturnSheet> createState() => _ReturnSheetState();
}

class _ReturnSheetState extends State<ReturnSheet> {
  final Map<int, int> _qty = {};
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items = widget.order.items.where((i) => i.returnable > 0).toList();
    final any = _qty.values.any((q) => q > 0);
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l10n.orderReturnTitle, style: context.text.titleLarge),
              Text(l10n.orderReturnItems),
              for (final i in items)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(i.name),
                  trailing: QuantityStepper(
                    value: _qty[i.id] ?? 0,
                    min: 0,
                    max: i.returnable,
                    onChanged: (v) => setState(() => _qty[i.id] = v),
                  ),
                ),
              const SizedBox(height: AppSpacing.sm),
              AppTextField(label: l10n.orderReturnReason, controller: _reason),
              const SizedBox(height: AppSpacing.md),
              AppButton(
                label: l10n.orderReturn,
                onPressed: any
                    ? () => Navigator.pop(
                        context,
                        ReturnRequestData(_qty, _reason.text),
                      )
                    : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
