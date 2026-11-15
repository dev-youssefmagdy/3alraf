import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../cart/application/cart_controller.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import '../data/product_models.dart';
import 'catalog_widgets.dart';
import 'product_sheets.dart';
import 'variant_picker.dart';

/// Adds one variant to the cart through the cart controller (the same call
/// the product page makes) and shows the success snackbar with "view cart".
/// Errors are shown as an error snackbar. Returns true when added.
Future<bool> addVariantToCart(
  BuildContext context,
  WidgetRef ref, {
  required int productId,
  required int variantId,
  int quantity = 1,
}) async {
  final l10n = context.l10n;
  final messenger = ScaffoldMessenger.of(context);
  final router = GoRouter.of(context);
  try {
    final message = await ref
        .read(cartControllerProvider.notifier)
        .add(productId: productId, variantId: variantId, quantity: quantity);
    HapticFeedback.mediumImpact();
    if (!context.mounted) return true;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        appSnackBar(
          context,
          message ?? l10n.addedToCart,
          tone: SnackTone.success,
          actionLabel: l10n.viewCart,
          onAction: () => router.go(AppRoutes.cart),
        ),
      );
    return true;
  } on ApiException catch (e) {
    if (context.mounted) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          appSnackBar(context, errorMessage(l10n, e), tone: SnackTone.error),
        );
    }
    return false;
  }
}

/// Product card CTA: loads the product detail (the same `productProvider`
/// the product page uses), then
/// * simple and in stock -> adds it to the cart,
/// * variable -> opens the variant sheet,
/// * sold out -> opens the notify-me sheet.
Future<void> quickAdd(
  BuildContext context,
  WidgetRef ref,
  ProductSummary product,
) async {
  final container = ProviderScope.containerOf(context, listen: false);
  final sub = container.listen(productProvider(product.slug).future, (_, _) {});
  final ProductDetail detail;
  try {
    detail = await sub.read();
  } on Object catch (e) {
    sub.close();
    if (context.mounted) {
      context.showSnack(errorMessage(context.l10n, e), tone: SnackTone.error);
    }
    return;
  }
  sub.close();
  if (!context.mounted) return;
  if (!detail.anyInStock) {
    // Not awaited: the card's spinner stops once the sheet is up.
    unawaited(
      showNotifyMeSheet(
        context,
        productId: detail.id,
        variantId: detail.variants.firstOrNull?.id ?? 0,
      ),
    );
    return;
  }
  if (detail.isVariable) {
    unawaited(showVariantSheet(context, product: detail));
    return;
  }
  final v = detail.simpleVariant;
  if (v == null) return;
  await addVariantToCart(context, ref, productId: detail.id, variantId: v.id);
}

/// Variant picker bottom sheet (App/Bottom sheet): photo, name, price,
/// labelled chip groups, quantity and a Primary L "add to cart".
Future<void> showVariantSheet(
  BuildContext context, {
  required ProductDetail product,
}) => showAppBottomSheet<void>(
  context: context,
  builder: (_) => _VariantSheet(product: product),
);

class _VariantSheet extends ConsumerStatefulWidget {
  const _VariantSheet({required this.product});

  final ProductDetail product;

  @override
  ConsumerState<_VariantSheet> createState() => _VariantSheetState();
}

class _VariantSheetState extends ConsumerState<_VariantSheet> {
  late Map<int, int> _selection = VariantPicker.defaultSelection(
    widget.product,
  );
  int _qty = 1;
  bool _busy = false;

  ProductVariant? get _variant =>
      VariantPicker.resolve(widget.product, _selection);

  Future<void> _add(ProductVariant v) async {
    setState(() => _busy = true);
    final nav = Navigator.of(context);
    final ok = await addVariantToCart(
      context,
      ref,
      productId: widget.product.id,
      variantId: v.id,
      quantity: _qty,
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) nav.pop();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final p = widget.product;
    final v = _variant;
    final currency = ref.watch(currencyLabelProvider);
    final invalid = v == null && _selection.length == p.options.length;
    final canAdd = v != null && v.inStock;
    return AppSheet(
      title: l10n.chooseOptions,
      actions: [
        AppButton(
          label: l10n.addToCart,
          icon: Icons.shopping_cart_outlined,
          size: AppButtonSize.l,
          loading: _busy,
          onPressed: canAdd ? () => _add(v) : null,
        ),
      ],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              NetworkImageX(
                url: v?.image ?? p.images.firstOrNull?.url,
                width: 64,
                height: 64,
                radius: AppRadius.sm,
                cacheWidth: 128,
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: context.text.titleSmall,
                    ),
                    PriceText(
                      amount: v?.price ?? p.price,
                      oldAmount: v != null ? v.oldPrice : p.regularPrice,
                      currency: currency,
                    ),
                  ],
                ),
              ),
            ],
          ),
          VariantPickerView(
            product: p,
            selection: _selection,
            onSelect: (o, val) => setState(() {
              _selection = {..._selection, o: val};
              _qty = 1;
            }),
          ),
          if (invalid)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.sm),
              child: Text(
                l10n.unavailableCombo,
                style: context.text.bodySmall?.copyWith(
                  color: context.colors.error,
                ),
              ),
            ),
          if (canAdd) ...[
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(
                  child: Text(l10n.quantity, style: context.text.titleSmall),
                ),
                QuantityStepper(
                  value: _qty,
                  max: v.stock ?? 99,
                  onChanged: (q) => setState(() => _qty = q),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
