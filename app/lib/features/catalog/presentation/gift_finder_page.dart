import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/l10n/app_localizations.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../tenant/application/tenant_controller.dart';
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

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final enabled =
        ref.watch(storeInfoProvider)?.features['gift_finder'] ?? true;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.giftFinder),
        actions: [
          if (_results != null)
            TextButton(
              onPressed: () => setState(() {
                _results = null;
                _step = 0;
              }),
              child: Text(l10n.giftStartOver),
            ),
        ],
      ),
      body: !enabled
          ? EmptyState(
              title: l10n.featureUnavailable,
              icon: Icons.card_giftcard_rounded,
            )
          : _results != null
          ? _GiftResultsView(results: _results!)
          : _wizard(context),
    );
  }

  Widget _wizard(BuildContext context) {
    final l10n = context.l10n;
    final e = _error;
    final menu =
        ref.watch(menuProvider).asData?.value ?? const <CategoryNode>[];
    final steps = <Widget>[
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.giftStepBudget, style: context.text.titleLarge),
          const SizedBox(height: AppSpacing.md),
          AppTextField(
            label: l10n.giftBudget,
            controller: _budget,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
            ],
            errorText: e?.firstErrorFor('budget'),
            hint: ref.watch(currencyLabelProvider),
          ),
        ],
      ),
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.giftStepOccasion, style: context.text.titleLarge),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final o in giftOccasions)
                ChoiceChip(
                  label: Text(occasionLabel(l10n, o)),
                  selected: _occasion == o,
                  onSelected: (s) => setState(() => _occasion = s ? o : null),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(l10n.giftStepRecipient, style: context.text.titleLarge),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final r in giftRecipients)
                ChoiceChip(
                  label: Text(recipientLabel(l10n, r)),
                  selected: _recipient == r,
                  onSelected: (s) => setState(() => _recipient = s ? r : null),
                ),
            ],
          ),
        ],
      ),
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.giftStepExtras, style: context.text.titleLarge),
          const SizedBox(height: AppSpacing.md),
          if (menu.isNotEmpty)
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: [
                for (final c in menu)
                  ChoiceChip(
                    label: Text(c.name),
                    selected: _categoryId == c.id,
                    onSelected: (s) =>
                        setState(() => _categoryId = s ? c.id : null),
                  ),
              ],
            ),
          const SizedBox(height: AppSpacing.md),
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
                style: TextStyle(color: context.colors.error),
              ),
            ),
        ],
      ),
    ];
    final last = _step == steps.length - 1;
    return Column(
      children: [
        LinearProgressIndicator(value: (_step + 1) / steps.length),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [steps[_step]],
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(
              children: [
                if (_step > 0)
                  Expanded(
                    child: AppButton(
                      label: l10n.back,
                      variant: AppButtonVariant.outlined,
                      onPressed: () => setState(() => _step--),
                    ),
                  ),
                if (_step > 0) const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: AppButton(
                    label: last ? l10n.giftFind : l10n.next,
                    loading: _busy,
                    onPressed: last ? _search : _next,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _GiftResultsView extends ConsumerStatefulWidget {
  const _GiftResultsView({required this.results});

  final GiftResults results;

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
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(l10n.giftAdded(added)),
            action: SnackBarAction(
              label: l10n.viewCart,
              onPressed: () => router.go(AppRoutes.cart),
            ),
          ),
        );
    } on ApiException catch (e) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(errorMessage(l10n, e))));
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final currency = ref.watch(currencyLabelProvider);
    final r = widget.results;
    if (r.results.isEmpty && r.bundles.isEmpty && r.nearBudget.isEmpty) {
      return EmptyState(
        title: l10n.giftNoResults,
        message: l10n.giftNoResultsBody,
        icon: Icons.card_giftcard_rounded,
      );
    }
    final selected = r.results.where(_ready).length;
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.only(bottom: AppSpacing.lg),
            children: [
              if (r.results.isNotEmpty)
                SectionHeader(title: l10n.giftSuggestions),
              for (final i in r.results)
                _GiftTile(
                  item: i,
                  currency: currency,
                  picked: _picked.contains(i.productId),
                  variantId: _variants[i.productId],
                  onToggle: () => setState(
                    () => _picked.contains(i.productId)
                        ? _picked.remove(i.productId)
                        : _picked.add(i.productId),
                  ),
                  onVariant: (v) => setState(() {
                    _variants[i.productId] = v;
                    _picked.add(i.productId);
                  }),
                ),
              if (r.bundles.isNotEmpty) ...[
                SectionHeader(title: l10n.bundles),
                HorizontalRail(
                  height: 290,
                  itemWidth: 190,
                  itemCount: r.bundles.length,
                  itemBuilder: (_, i) => BundleCard(bundle: r.bundles[i]),
                ),
              ],
              if (r.nearBudget.isNotEmpty) ...[
                SectionHeader(title: l10n.giftNearBudget),
                for (final i in r.nearBudget)
                  ListTile(
                    leading: SizedBox(
                      width: 56,
                      height: 56,
                      child: NetworkImageX(
                        url: i.image,
                        radius: AppRadius.sm,
                        cacheWidth: 56,
                      ),
                    ),
                    title: Text(i.name),
                    subtitle: Text(l10n.currencyAmount(i.price, currency)),
                    onTap: () => openProduct(context, i.slug),
                  ),
              ],
            ],
          ),
        ),
        if (r.results.isNotEmpty)
          Material(
            elevation: 8,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: AppButton(
                  label: l10n.giftAddSelected(selected),
                  icon: Icons.add_shopping_cart_rounded,
                  loading: _adding,
                  onPressed: selected == 0 ? null : _add,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _GiftTile extends StatelessWidget {
  const _GiftTile({
    required this.item,
    required this.currency,
    required this.picked,
    required this.variantId,
    required this.onToggle,
    required this.onVariant,
  });

  final GiftItem item;
  final String currency;
  final bool picked;
  final int? variantId;
  final VoidCallback onToggle;
  final ValueChanged<int> onVariant;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Card(
      margin: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs,
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.sm),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Checkbox(value: picked, onChanged: (_) => onToggle()),
                SizedBox(
                  width: 72,
                  height: 72,
                  child: InkWell(
                    onTap: () => openProduct(context, item.slug),
                    child: NetworkImageX(
                      url: item.image,
                      radius: AppRadius.sm,
                      cacheWidth: 72,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.text.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      PriceText(amount: item.price, currency: currency),
                    ],
                  ),
                ),
              ],
            ),
            if (item.explanation != null)
              Padding(
                padding: const EdgeInsets.only(top: AppSpacing.xs),
                child: Text(item.explanation!, style: context.text.bodySmall),
              ),
            if (!item.simple && item.variants.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(l10n.giftChooseVariant, style: context.text.labelMedium),
              Wrap(
                spacing: AppSpacing.sm,
                children: [
                  for (final v in item.variants)
                    ChoiceChip(
                      label: Text(v.label ?? '#${v.id}'),
                      selected: variantId == v.id,
                      onSelected: (_) => onVariant(v.id),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
