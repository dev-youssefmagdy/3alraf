import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/app_localizations.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../store/application/store_controller.dart';
import '../../cart/application/cart_controller.dart';
import '../application/catalog_providers.dart';
import '../data/catalog_models.dart';
import '../data/product_models.dart';
import 'bundle_widgets.dart';
import 'catalog_widgets.dart';

String occasionLabel(AppLocalizations l, String key) => switch (key) {
  'birthday' => l.occBirthday,
  'mothers_day' => l.occMothersDay,
  'valentine' => l.occValentine,
  'wedding' => l.occWedding,
  'eid_fitr' => l.occEidFitr,
  'eid_adha' => l.occEidAdha,
  'ramadan' => l.occRamadan,
  'graduation' => l.occGraduation,
  'thanks' => l.occThanks,
  _ => l.occSelf,
};

String recipientLabel(AppLocalizations l, String key) => switch (key) {
  'mother' => l.recMother,
  'partner' => l.recPartner,
  'friend' => l.recFriend,
  'sister' => l.recSister,
  'colleague' => l.recColleague,
  'child' => l.recChild,
  'other' => l.recOther,
  _ => l.recSelf,
};

/// Gift finder wizard (feature `gift_finder`): budget -> occasion/recipient
/// -> category/restrictions -> suggestions -> add to cart.
class GiftFinderPage extends ConsumerStatefulWidget {
  const GiftFinderPage({super.key});

  @override
  ConsumerState<GiftFinderPage> createState() => _GiftFinderPageState();
}

class _GiftFinderPageState extends ConsumerState<GiftFinderPage> {
  final _budget = TextEditingController();
  final _restrictions = TextEditingController();
  int _step = 0;
  String? _occasion;
  String? _recipient;
  int? _categoryId;
  bool _busy = false;
  ApiException? _error;
  GiftResults? _results;

  @override
  void dispose() {
    _budget.dispose();
    _restrictions.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final r = await ref
          .read(catalogRepositoryProvider)
          .giftSearch(
            GiftAnswers(
              budget: _budget.text.trim(),
              occasion: _occasion,
              recipient: _recipient,
              categoryId: _categoryId,
              restrictions: _restrictions.text,
            ),
          );
      if (!mounted) return;
      setState(() {
        _busy = false;
        _results = r;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      final budgetError = e.firstErrorFor('budget') != null;
      setState(() {
        _busy = false;
        _error = e;
        if (budgetError) _step = 0;
      });
    }
  }

  void _next() {
    final l10n = context.l10n;
    if (_step == 0) {
      final v = double.tryParse(_budget.text.trim());
      if (v == null || v <= 0) {
        setState(
          () => _error = ApiException(
            kind: ApiErrorKind.validation,
            message: l10n.giftBudgetRequired,
            errors: {
              'budget': [l10n.giftBudgetRequired],
            },
          ),
        );
        return;
      }
    }
    setState(() {
      _error = null;
      _step++;
    });
  }

  void _restart() => setState(() {
    _results = null;
    _step = 0;
    _error = null;
  });

  /// Back to the wizard with the answers kept ("تعديل").
  void _edit() => setState(() {
    _results = null;
    _step = 0;
  });

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final enabled =
        ref.watch(storeProfileProvider)?.features['gift_finder'] ?? true;
    final r = _results;
    return Scaffold(
      appBar: AppBar(
        title: Text(r != null ? l10n.giftSuggestions : l10n.giftFinder),
        actions: [
          if (r != null)
            TextButton(onPressed: _restart, child: Text(l10n.giftStartOver)),
        ],
      ),
      body: !enabled
          ? EmptyState(
              title: l10n.featureUnavailable,
              icon: Icons.card_giftcard_rounded,
              tone: StateTone.violet,
            )
          : r != null
          ? _GiftResultsView(
              results: r,
              summary: [
                if (_budget.text.trim().isNotEmpty)
                  l10n.giftBudgetChip(
                    l10n.money(
                      _formatBudget(_budget.text.trim()),
                      ref.watch(currencyLabelProvider),
                    ),
                  ),
                if (_occasion != null) occasionLabel(l10n, _occasion!),
                if (_recipient != null) recipientLabel(l10n, _recipient!),
              ],
              onEdit: _edit,
            )
          : _wizard(context),
    );
  }

  static const _stepCount = 4;
  static const _presets = ['300', '500', '1000', '2000'];
  static const double _budgetMin = 50;
  static const double _budgetMax = 10000;

  Widget _chips<T>({
    required List<T> values,
    required T? selected,
    required String Function(T) label,
    required ValueChanged<T?> onSelected,
  }) => Wrap(
    spacing: AppSpacing.sm,
    runSpacing: AppSpacing.sm,
    children: [
      for (final v in values)
        ChoiceChip(
          label: Text(label(v)),
          selected: selected == v,
          onSelected: (s) => onSelected(s ? v : null),
        ),
    ],
  );

  Widget _wizard(BuildContext context) {
    final l10n = context.l10n;
    final e = _error;
    final menu =
        ref.watch(menuProvider).asData?.value ?? const <CategoryNode>[];
    final currency = ref.watch(currencyLabelProvider);
    final budget = double.tryParse(_budget.text.trim());
    final questions = [
      l10n.giftStepBudget,
      l10n.giftStepOccasion,
      l10n.giftStepCategory,
      l10n.giftStepRecipient,
    ];
    final steps = <Widget>[
      Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppTextField(
            label: l10n.giftBudget,
            controller: _budget,
            ltr: true,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
            ],
            errorText: e?.firstErrorFor('budget'),
            hint: currency,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: AppSpacing.sm),
          Slider(
            min: _budgetMin,
            max: _budgetMax,
            divisions: ((_budgetMax - _budgetMin) / 50).round(),
            label: budget == null ? null : _formatBudget(_budget.text.trim()),
            value: (budget ?? _budgetMin).clamp(_budgetMin, _budgetMax),
            onChanged: (v) => setState(() {
              _budget.text = v.round().toString();
              _error = null;
            }),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            child: Row(
              children: [
                Text(
                  _formatBudget('${_budgetMin.round()}'),
                  style: context.text.bodySmall,
                ),
                const Spacer(),
                Text(
                  _formatBudget('${_budgetMax.round()}'),
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          _chips<String>(
            values: _presets,
            selected: _presets.contains(_budget.text.trim())
                ? _budget.text.trim()
                : null,
            label: (v) => l10n.money(_formatBudget(v), currency),
            onSelected: (v) => setState(() {
              if (v != null) _budget.text = v;
              _error = null;
            }),
          ),
        ],
      ),
      _chips<String>(
        values: giftOccasions,
        selected: _occasion,
        label: (o) => occasionLabel(l10n, o),
        onSelected: (o) => setState(() => _occasion = o),
      ),
      _chips<int?>(
        values: [null, for (final c in menu) c.id],
        selected: _categoryId,
        label: (id) => id == null
            ? l10n.giftAnyCategory
            : menu.firstWhere((c) => c.id == id).name,
        onSelected: (id) => setState(() => _categoryId = id),
      ),
      Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _chips<String>(
            values: giftRecipients,
            selected: _recipient,
            label: (r) => recipientLabel(l10n, r),
            onSelected: (r) => setState(() => _recipient = r),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(l10n.giftStepExtras, style: context.text.titleLarge),
          const SizedBox(height: AppSpacing.smd),
          AppTextField(
            label: l10n.giftRestrictions,
            hint: l10n.giftRestrictionsHint,
            controller: _restrictions,
          ),
          if (e != null && e.firstErrorFor('budget') == null)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.md),
              child: Text(
                errorMessage(l10n, e),
                style: context.text.bodySmall?.copyWith(
                  color: context.colors.error,
                ),
              ),
            ),
        ],
      ),
    ];
    final last = _step == steps.length - 1;
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.only(bottom: AppSpacing.lg),
            children: [
              _GiftHeader(
                step: _step,
                total: _stepCount,
                question: questions[_step],
                onRestart: _step == 0 ? null : _restart,
              ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.gutter),
                child: steps[_step],
              ),
            ],
          ),
        ),
        StickyActionBar(
          leading: _step > 0
              ? SizedBox(
                  width: 150,
                  child: AppButton(
                    label: l10n.back,
                    size: AppButtonSize.l,
                    variant: AppButtonVariant.outlined,
                    onPressed: () => setState(() => _step--),
                  ),
                )
              : null,
          action: AppButton(
            label: last ? l10n.giftFind : l10n.next,
            variant: AppButtonVariant.pop,
            size: AppButtonSize.l,
            loading: _busy,
            onPressed: last ? _search : _next,
          ),
        ),
      ],
    );
  }
}

/// `1000` -> `1,000` (Western digits); non-numbers are returned as typed.
String _formatBudget(String raw) {
  final v = double.tryParse(raw);
  if (v == null) return raw;
  return NumberFormat('#,##0.##', 'en').format(v);
}

/// Violet wizard header: sun gift circle, title, "step n of 4", restart
/// link, 4-segment sun progress and the question (Display/M).
class _GiftHeader extends StatelessWidget {
  const _GiftHeader({
    required this.step,
    required this.total,
    required this.question,
    this.onRestart,
  });

  final int step;
  final int total;
  final String question;
  final VoidCallback? onRestart;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    return Container(
      color: x.violet,
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gutter,
        AppSpacing.md,
        AppSpacing.gutter,
        AppSpacing.lg,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: x.sun, shape: BoxShape.circle),
                child: const Icon(
                  Icons.card_giftcard_rounded,
                  color: AppColors.slate950,
                ),
              ),
              const SizedBox(width: AppSpacing.smd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.giftFinder,
                      style: context.text.titleLarge?.copyWith(
                        color: Colors.white,
                      ),
                    ),
                    Text(
                      l10n.giftStepOf(step + 1, total),
                      style: context.text.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.85),
                      ),
                    ),
                  ],
                ),
              ),
              if (onRestart != null)
                TextButton(
                  onPressed: onRestart,
                  style: TextButton.styleFrom(foregroundColor: Colors.white),
                  child: Text(l10n.giftStartOver),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Semantics(
            label: l10n.giftStepOf(step + 1, total),
            excludeSemantics: true,
            child: Row(
              children: [
                for (var i = 0; i < total; i++) ...[
                  if (i > 0) const SizedBox(width: 6),
                  Expanded(
                    child: Container(
                      height: 6,
                      decoration: BoxDecoration(
                        color: i <= step ? x.sun : Colors.white,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          Semantics(
            header: true,
            child: Text(
              question,
              style: context.text.displaySmall?.copyWith(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }
}

class _GiftResultsView extends ConsumerStatefulWidget {
  const _GiftResultsView({
    required this.results,
    required this.summary,
    required this.onEdit,
  });

  final GiftResults results;
  final List<String> summary;
  final VoidCallback onEdit;

  @override
  ConsumerState<_GiftResultsView> createState() => _GiftResultsViewState();
}

class _GiftResultsViewState extends ConsumerState<_GiftResultsView> {
  late final Set<int> _picked = {
    for (final i in widget.results.results)
      if (i.simple) i.productId,
  };
  final Map<int, int> _variants = {}; // productId -> variantId
  bool _adding = false;

  bool _ready(GiftItem i) =>
      _picked.contains(i.productId) &&
      (i.simple || _variants.containsKey(i.productId));

  Future<void> _add() async {
    final l10n = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    final ids = [
      for (final i in widget.results.results)
        if (_ready(i)) i.productId,
    ];
    if (ids.isEmpty) return;
    setState(() => _adding = true);
    try {
      final added = await ref
          .read(catalogRepositoryProvider)
          .giftAddToCart(widget.results.token, ids, _variants);
      await ref.read(cartControllerProvider.notifier).refresh();
      HapticFeedback.mediumImpact();
      if (!mounted) return;
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          appSnackBar(
            context,
            l10n.giftAdded(added),
            tone: SnackTone.success,
            actionLabel: l10n.viewCart,
            onAction: () => router.go(AppRoutes.cart),
          ),
        );
    } on ApiException catch (e) {
      if (!mounted) return;
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          appSnackBar(context, errorMessage(l10n, e), tone: SnackTone.error),
        );
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  Future<void> _chooseVariant(GiftItem item) async {
    final l10n = context.l10n;
    final picked = await showAppBottomSheet<int>(
      context: context,
      builder: (ctx) => AppSheet(
        title: l10n.giftChooseVariant,
        child: Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (final v in item.variants)
              ChoiceChip(
                label: Text(v.label ?? '#${v.id}'),
                selected: _variants[item.productId] == v.id,
                onSelected: (_) => Navigator.of(ctx).pop(v.id),
              ),
          ],
        ),
      ),
    );
    if (picked != null && mounted) {
      setState(() {
        _variants[item.productId] = picked;
        _picked.add(item.productId);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final x = context.extra;
    final currency = ref.watch(currencyLabelProvider);
    final r = widget.results;
    final summary = Container(
      color: x.violetSoft,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gutter,
        vertical: AppSpacing.smd,
      ),
      child: Wrap(
        spacing: AppSpacing.sm,
        runSpacing: AppSpacing.sm,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          for (final s in widget.summary)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: x.violet.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(AppRadius.pill),
              ),
              child: Text(
                s,
                style: context.textX.labelXs.copyWith(
                  color: x.violetText,
                  fontSize: 12,
                ),
              ),
            ),
          OutlinedButton.icon(
            onPressed: widget.onEdit,
            icon: const Icon(Icons.edit_outlined, size: 16),
            label: Text(l10n.edit),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(44, 36),
              backgroundColor: Colors.white,
              textStyle: context.textX.labelXs.copyWith(fontSize: 12),
            ),
          ),
        ],
      ),
    );
    if (r.results.isEmpty && r.bundles.isEmpty && r.nearBudget.isEmpty) {
      return Column(
        children: [
          summary,
          Expanded(
            child: StateBlock(
              title: l10n.giftNoResults,
              message: l10n.giftNoResultsBody,
              icon: Icons.card_giftcard_rounded,
              tone: StateTone.violet,
              actionLabel: l10n.edit,
              actionVariant: AppButtonVariant.outlined,
              onAction: widget.onEdit,
            ),
          ),
        ],
      );
    }
    final selected = r.results.where(_ready).length;
    final cardH = ProductCard.extentFor(
      context,
      ProductRail.railCardWidth,
      withCta: false,
    );
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.only(bottom: AppSpacing.lg),
            children: [
              summary,
              if (r.results.isNotEmpty) ...[
                SectionHeader(
                  title: l10n.giftSuggestionsCount(r.results.length),
                ),
                HorizontalRail(
                  height: cardH + 8 + 48 * scale.clamp(1, 1.4) + 4,
                  itemWidth: ProductRail.railCardWidth,
                  itemCount: r.results.length,
                  showProgress: r.results.length > 2,
                  itemBuilder: (_, i) {
                    final item = r.results[i];
                    return _GiftCard(
                      item: item,
                      index: i,
                      currency: currency,
                      cardHeight: cardH,
                      picked: _picked.contains(item.productId),
                      variantChosen: _variants.containsKey(item.productId),
                      onToggle: () {
                        if (!item.simple &&
                            !_variants.containsKey(item.productId)) {
                          _chooseVariant(item);
                          return;
                        }
                        setState(
                          () => _picked.contains(item.productId)
                              ? _picked.remove(item.productId)
                              : _picked.add(item.productId),
                        );
                      },
                      onVariant: () => _chooseVariant(item),
                    );
                  },
                ),
              ],
              if (r.bundles.isNotEmpty) ...[
                SectionHeader(title: l10n.bundles),
                HorizontalRail(
                  height: bundleCardExtent(context),
                  itemWidth: bundleCardWidth,
                  itemCount: r.bundles.length,
                  itemBuilder: (_, i) => BundleCard(bundle: r.bundles[i]),
                ),
              ],
              if (r.nearBudget.isNotEmpty) ...[
                SectionHeader(title: l10n.giftNearBudget),
                HorizontalRail(
                  height: cardH,
                  itemWidth: ProductRail.railCardWidth,
                  itemCount: r.nearBudget.length,
                  showProgress: r.nearBudget.length > 2,
                  itemBuilder: (_, i) {
                    final item = r.nearBudget[i];
                    return ProductCard(
                      title: item.name,
                      price: item.price,
                      currency: currency,
                      imageUrl: item.image,
                      tintIndex: i,
                      pricePrefix: item.simple ? null : l10n.priceStartingFrom,
                      onTap: () => openProduct(context, item.slug),
                    );
                  },
                ),
              ],
            ],
          ),
        ),
        if (r.results.isNotEmpty)
          StickyActionBar(
            dark: true,
            leading: Text(
              l10n.giftSelectedCount(selected),
              style: context.text.titleSmall?.copyWith(color: x.onInk),
            ),
            action: AppButton(
              label: l10n.giftAddSelected(selected),
              icon: Icons.shopping_cart_outlined,
              variant: AppButtonVariant.sun,
              size: AppButtonSize.l,
              loading: _adding,
              onPressed: selected == 0 ? null : _add,
            ),
          ),
      ],
    );
  }
}

/// Suggestion card + the select checkbox and the sun reason chip; variable
/// items get a "choose an option" link that opens the variant sheet.
class _GiftCard extends StatelessWidget {
  const _GiftCard({
    required this.item,
    required this.index,
    required this.currency,
    required this.cardHeight,
    required this.picked,
    required this.variantChosen,
    required this.onToggle,
    required this.onVariant,
  });

  final GiftItem item;
  final int index;
  final String currency;
  final double cardHeight;
  final bool picked;
  final bool variantChosen;
  final VoidCallback onToggle;
  final VoidCallback onVariant;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final reason = item.explanation;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: cardHeight,
          child: ProductCard(
            title: item.name,
            price: item.price,
            currency: currency,
            imageUrl: item.image,
            tintIndex: index,
            pricePrefix: item.simple ? null : l10n.priceStartingFrom,
            onTap: () => openProduct(context, item.slug),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Row(
          children: [
            Semantics(
              label: '${l10n.giftPick} ${item.name}',
              child: Checkbox(
                value: picked && (item.simple || variantChosen),
                onChanged: (_) => onToggle(),
              ),
            ),
            Expanded(
              child: !item.simple && !variantChosen
                  ? TextButton(
                      onPressed: onVariant,
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        alignment: AlignmentDirectional.centerStart,
                        textStyle: context.textX.labelXs.copyWith(fontSize: 12),
                      ),
                      child: Text(
                        l10n.giftChooseVariant,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    )
                  : reason != null && reason.isNotEmpty
                  ? Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Tooltip(
                        message: reason,
                        child: StatusChip(label: reason, tone: StatusTone.sun),
                      ),
                    )
                  : Text(
                      l10n.giftPick,
                      style: context.textX.labelXs.copyWith(
                        color: context.extra.textMuted,
                      ),
                    ),
            ),
          ],
        ),
      ],
    );
  }
}
