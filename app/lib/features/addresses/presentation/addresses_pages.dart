import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../application/address_controller.dart';
import '../data/address_models.dart';

/// Account > Addresses (also reused as a picker from checkout).
class AddressesPage extends ConsumerWidget {
  const AddressesPage({super.key});

  Future<void> _delete(BuildContext context, WidgetRef ref, Address a) async {
    final l10n = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.addressDeleteTitle),
        content: Text(l10n.addressDeleteBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l10n.delete),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final err = await ref.read(addressesProvider.notifier).remove(a);
    if (context.mounted) {
      context.showSnack(
        err == null ? l10n.addressDeleted : errorMessage(l10n, err),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final state = ref.watch(addressesProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.addressesTitle)),
      body: Builder(
        builder: (context) {
          if (!state.loaded && state.loading) {
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.gutter),
              children: [
                for (var i = 0; i < 3; i++) ...[
                  const AppShimmer(height: 96, radius: AppRadius.md),
                  const SizedBox(height: AppSpacing.smd),
                ],
              ],
            );
          }
          if (!state.loaded && state.error != null) {
            return ErrorState(
              error: state.error,
              onRetry: () => ref.read(addressesProvider.notifier).load(),
            );
          }
          if (state.items.isEmpty) {
            return StateBlock(
              icon: Icons.location_on_outlined,
              title: l10n.addressesEmpty,
              message: l10n.addressesEmptyBody,
              actionLabel: l10n.addressAdd,
              actionIcon: Icons.add_rounded,
              onAction: () => context.push(AppRoutes.addressEdit),
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.read(addressesProvider.notifier).load(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutter,
                AppSpacing.gutter,
                AppSpacing.gutter,
                AppSpacing.xl,
              ),
              children: [
                for (final a in state.items) ...[
                  AddressCard(
                    address: a,
                    selected: a.isDefault,
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _EditPill(
                          label: l10n.edit,
                          onTap: () =>
                              context.push(AppRoutes.addressEdit, extra: a),
                        ),
                        IconButton(
                          tooltip: l10n.delete,
                          visualDensity: VisualDensity.compact,
                          icon: Icon(
                            Icons.delete_outline_rounded,
                            size: 20,
                            color: context.colors.error,
                          ),
                          onPressed: () => _delete(context, ref, a),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.smd),
                ],
                DashedBox(
                  key: const ValueKey('address-add'),
                  onTap: () => context.push(AppRoutes.addressEdit),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.add_rounded,
                        size: 20,
                        color: context.colors.primary,
                      ),
                      const SizedBox(width: AppSpacing.xs),
                      Text(
                        l10n.addressAdd,
                        style: context.text.labelMedium?.copyWith(
                          color: context.colors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Small outline "edit" pill with a pencil.
class _EditPill extends StatelessWidget {
  const _EditPill({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: context.colors.surfaceContainerLowest,
    shape: StadiumBorder(side: BorderSide(color: context.colors.outline)),
    child: InkWell(
      customBorder: const StadiumBorder(),
      onTap: onTap,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 32),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.edit_outlined,
                size: 14,
                color: context.colors.onSurface,
              ),
              const SizedBox(width: 4),
              Text(label, style: context.text.labelSmall),
            ],
          ),
        ),
      ),
    ),
  );
}

class AddressCard extends StatelessWidget {
  const AddressCard({
    super.key,
    required this.address,
    this.trailing,
    this.selected = false,
    this.onTap,
  });

  final Address address;
  final Widget? trailing;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final c = context.colors;
    final x = context.extra;
    final a = address;
    final muted = context.text.bodySmall?.copyWith(color: c.onSurfaceVariant);
    return Material(
      color: selected ? x.brandSoft : c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(
          color: selected ? c.primary : c.outlineVariant,
          width: selected ? 1.5 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(
            AppSpacing.md,
            AppSpacing.smd,
            AppSpacing.xs,
            AppSpacing.smd,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 3),
                child: Icon(
                  Icons.location_on_outlined,
                  size: 20,
                  color: c.primary,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          a.label?.isNotEmpty == true ? a.label! : a.name,
                          style: context.text.titleMedium,
                        ),
                        if (a.isDefault)
                          StatusChip(
                            label: l10n.addressDefault,
                            tone: StatusTone.brand,
                          ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(text: '${a.name} · '),
                          TextSpan(text: '\u2066${a.phone}\u2069'),
                        ],
                      ),
                      style: muted,
                    ),
                    Text('${a.city} - ${a.address}', style: muted),
                  ],
                ),
              ),
              ?trailing,
            ],
          ),
        ),
      ),
    );
  }
}

/// Create / edit. Pops with the saved [Address] so checkout can select it.
class AddressFormPage extends ConsumerStatefulWidget {
  const AddressFormPage({super.key, this.existing});

  final Address? existing;

  @override
  ConsumerState<AddressFormPage> createState() => _AddressFormPageState();
}

class _AddressFormPageState extends ConsumerState<AddressFormPage> {
  final _form = GlobalKey<FormState>();
  late final _label = TextEditingController(text: widget.existing?.label);
  late final _name = TextEditingController(text: widget.existing?.name);
  late final _phone = TextEditingController(text: widget.existing?.phone);
  late final _line = TextEditingController(text: widget.existing?.address);
  late final _notes = TextEditingController(text: widget.existing?.notes);
  late int? _cityId = widget.existing?.cityId;
  late bool _default = widget.existing?.isDefault ?? false;
  bool _busy = false;
  Map<String, List<String>> _errors = const {};

  @override
  void dispose() {
    for (final c in [_label, _name, _phone, _line, _notes]) {
      c.dispose();
    }
    super.dispose();
  }

  String? _err(String f) => _errors[f]?.firstOrNull;

  Future<void> _pickCity(List<City> cities) async {
    final id = await showAppBottomSheet<int>(
      context: context,
      builder: (_) => CityPickerSheet(cities: cities, selected: _cityId),
    );
    if (id != null) setState(() => _cityId = id);
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    if (!(_form.currentState?.validate() ?? false)) return;
    if (_cityId == null) {
      setState(
        () => _errors = {
          'city_id': [l10n.fieldRequired],
        },
      );
      return;
    }
    setState(() {
      _busy = true;
      _errors = const {};
    });
    final router = GoRouter.of(context);
    try {
      final saved = await ref
          .read(addressesProvider.notifier)
          .save(
            AddressInput(
              name: _name.text.trim(),
              phone: _phone.text.trim(),
              cityId: _cityId!,
              address: _line.text.trim(),
              label: _label.text.trim(),
              notes: _notes.text.trim(),
              isDefault: _default,
            ),
            id: widget.existing?.id,
          );
      if (!mounted) return;
      context.showSnack(l10n.addressSaved);
      router.pop(saved);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.kind == ApiErrorKind.validation) {
        setState(() => _errors = e.errors);
      } else {
        context.showSnack(errorMessage(l10n, e));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cities = ref.watch(citiesProvider);
    final cityName = cities.asData?.value
        .where((c) => c.id == _cityId)
        .map((c) => c.name)
        .firstOrNull;
    String? required(String? v) =>
        (v == null || v.trim().isEmpty) ? l10n.fieldRequired : null;
    return FormPageScaffold(
      title: widget.existing == null ? l10n.addressAdd : l10n.addressEdit,
      bottom: StickyActionBar(
        action: AppButton(
          label: l10n.save,
          size: AppButtonSize.l,
          loading: _busy,
          onPressed: _save,
        ),
      ),
      children: [
        Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AppTextField(
                label: l10n.addressLabel,
                controller: _label,
                errorText: _err('label'),
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                label: l10n.fullName,
                controller: _name,
                validator: required,
                errorText: _err('name'),
                autofillHints: const [AutofillHints.name],
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                label: l10n.phone,
                controller: _phone,
                validator: required,
                errorText: _err('phone'),
                keyboardType: TextInputType.phone,
                ltr: true,
                autofillHints: const [AutofillHints.telephoneNumber],
              ),
              const SizedBox(height: AppSpacing.md),
              _SelectField(
                label: l10n.addressCity,
                value: cityName,
                hint: l10n.addressCityPick,
                loading: cities.isLoading,
                errorText: _err('city_id'),
                onTap: cities.asData == null
                    ? null
                    : () => _pickCity(cities.asData!.value),
              ),
              if (cities.hasError)
                TextButton(
                  onPressed: () => ref.invalidate(citiesProvider),
                  child: Text(l10n.retry),
                ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                label: l10n.addressLine,
                controller: _line,
                validator: required,
                errorText: _err('address'),
                maxLines: 2,
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                label: l10n.noteOptional,
                controller: _notes,
                errorText: _err('notes'),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  l10n.addressSetDefault,
                  style: context.text.titleSmall,
                ),
                value: _default,
                onChanged: (v) => setState(() => _default = v),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Searchable city list in a bottom sheet; pops the chosen city id.
class CityPickerSheet extends StatefulWidget {
  const CityPickerSheet({super.key, required this.cities, this.selected});

  final List<City> cities;
  final int? selected;

  @override
  State<CityPickerSheet> createState() => _CityPickerSheetState();
}

class _CityPickerSheetState extends State<CityPickerSheet> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final list = widget.cities
        .where((c) => c.name.toLowerCase().contains(_q.toLowerCase()))
        .toList();
    return AppSheet(
      title: l10n.addressCity,
      scrollable: false,
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.6,
        child: Column(
          children: [
            TextField(
              decoration: InputDecoration(
                hintText: l10n.addressCitySearch,
                prefixIcon: const Icon(Icons.search_rounded),
              ),
              onChanged: (v) => setState(() => _q = v),
            ),
            const SizedBox(height: AppSpacing.sm),
            Expanded(
              child: ListView.separated(
                itemCount: list.length,
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (_, i) {
                  final c = list[i];
                  final sel = c.id == widget.selected;
                  return ListTile(
                    title: Text(
                      c.name,
                      style: context.text.titleSmall?.copyWith(
                        color: sel ? context.colors.primary : null,
                      ),
                    ),
                    selected: sel,
                    trailing: sel
                        ? Icon(
                            Icons.check_rounded,
                            color: context.colors.primary,
                          )
                        : null,
                    onTap: () => Navigator.pop(context, c.id),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Field (Select) `7:138`: label above, a 48 white control with a chevron
/// that opens a picker sheet; 2px red border + alert row on error.
class _SelectField extends StatelessWidget {
  const _SelectField({
    required this.label,
    required this.hint,
    required this.onTap,
    this.value,
    this.errorText,
    this.loading = false,
  });

  final String label;
  final String hint;
  final String? value;
  final String? errorText;
  final bool loading;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final error = errorText;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Text(label, style: context.text.titleSmall),
        ),
        Semantics(
          button: true,
          label: label,
          value: value ?? hint,
          excludeSemantics: true,
          child: Material(
            color: c.surfaceContainerLowest,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.sm),
              side: BorderSide(
                color: error != null ? c.error : c.outline,
                width: error != null ? 2 : 1,
              ),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: onTap,
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 48),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  child: Row(
                    children: [
                      Expanded(
                        child: loading
                            ? const LinearProgressIndicator()
                            : Text(
                                value ?? hint,
                                style: context.text.bodyLarge?.copyWith(
                                  color: value == null
                                      ? context.extra.textMuted
                                      : null,
                                ),
                              ),
                      ),
                      Icon(
                        Icons.keyboard_arrow_down_rounded,
                        color: c.onSurfaceVariant,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Row(
              children: [
                Icon(Icons.error_outline_rounded, size: 14, color: c.error),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    error,
                    style: context.text.bodySmall?.copyWith(color: c.error),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
