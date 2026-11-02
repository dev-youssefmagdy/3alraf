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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push(AppRoutes.addressEdit),
        icon: const Icon(Icons.add_rounded),
        label: Text(l10n.addressAdd),
      ),
      body: Builder(
        builder: (context) {
          if (!state.loaded && state.loading) {
            return const Center(child: CircularProgressIndicator());
          }
          if (!state.loaded && state.error != null) {
            return ErrorState(
              error: state.error,
              onRetry: () => ref.read(addressesProvider.notifier).load(),
            );
          }
          if (state.items.isEmpty) {
            return EmptyState(
              icon: Icons.location_on_outlined,
              title: l10n.addressesEmpty,
              message: l10n.addressesEmptyBody,
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.read(addressesProvider.notifier).load(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.md,
                AppSpacing.md,
                AppSpacing.md,
                96,
              ),
              children: [
                for (final a in state.items)
                  AddressCard(
                    address: a,
                    trailing: PopupMenuButton<String>(
                      onSelected: (v) {
                        if (v == 'edit') {
                          context.push(AppRoutes.addressEdit, extra: a);
                        } else {
                          _delete(context, ref, a);
                        }
                      },
                      itemBuilder: (_) => [
                        PopupMenuItem(value: 'edit', child: Text(l10n.edit)),
                        PopupMenuItem(
                          value: 'delete',
                          child: Text(l10n.delete),
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
    final a = address;
    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(
          color: selected ? context.colors.primary : Colors.transparent,
          width: 2,
        ),
      ),
      child: ListTile(
        onTap: onTap,
        leading: Icon(
          selected
              ? Icons.radio_button_checked_rounded
              : Icons.location_on_outlined,
          color: context.colors.primary,
        ),
        title: Row(
          children: [
            Flexible(
              child: Text(
                a.label?.isNotEmpty == true ? a.label! : a.name,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (a.isDefault) ...[
              const SizedBox(width: 6),
              StatusChip(label: l10n.addressDefault, tone: StatusTone.brand),
            ],
          ],
        ),
        subtitle: Text('${a.name} - ${a.phone}\n${a.city}, ${a.address}'),
        isThreeLine: true,
        trailing: trailing,
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
    final id = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
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
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.existing == null ? l10n.addressAdd : l10n.addressEdit,
        ),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            AppTextField(
              label: l10n.addressLabel,
              controller: _label,
              errorText: _err('label'),
              prefixIcon: Icons.bookmark_outline_rounded,
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.fullName,
              controller: _name,
              validator: required,
              errorText: _err('name'),
              prefixIcon: Icons.person_outline_rounded,
              autofillHints: const [AutofillHints.name],
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.phone,
              controller: _phone,
              validator: required,
              errorText: _err('phone'),
              keyboardType: TextInputType.phone,
              prefixIcon: Icons.phone_outlined,
              autofillHints: const [AutofillHints.telephoneNumber],
            ),
            const SizedBox(height: AppSpacing.sm),
            InputDecorator(
              decoration: InputDecoration(
                labelText: l10n.addressCity,
                errorText: _err('city_id'),
                prefixIcon: const Icon(Icons.location_city_outlined),
              ),
              child: InkWell(
                onTap: cities.asData == null
                    ? null
                    : () => _pickCity(cities.asData!.value),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Expanded(
                        child: cities.isLoading
                            ? const LinearProgressIndicator()
                            : Text(cityName ?? l10n.addressCityPick),
                      ),
                      const Icon(Icons.arrow_drop_down_rounded),
                    ],
                  ),
                ),
              ),
            ),
            if (cities.hasError)
              TextButton(
                onPressed: () => ref.invalidate(citiesProvider),
                child: Text(l10n.retry),
              ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.addressLine,
              controller: _line,
              validator: required,
              errorText: _err('address'),
              maxLines: 2,
              prefixIcon: Icons.home_outlined,
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.noteOptional,
              controller: _notes,
              errorText: _err('notes'),
              prefixIcon: Icons.notes_rounded,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.addressSetDefault),
              value: _default,
              onChanged: (v) => setState(() => _default = v),
            ),
            const SizedBox(height: AppSpacing.md),
            AppButton(label: l10n.save, loading: _busy, onPressed: _save),
          ],
        ),
      ),
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
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.7,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: TextField(
                decoration: InputDecoration(
                  hintText: l10n.addressCitySearch,
                  prefixIcon: const Icon(Icons.search_rounded),
                ),
                onChanged: (v) => setState(() => _q = v),
              ),
            ),
            Expanded(
              child: ListView.builder(
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final c = list[i];
                  return ListTile(
                    title: Text(c.name),
                    trailing: c.id == widget.selected
                        ? const Icon(Icons.check_rounded)
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
