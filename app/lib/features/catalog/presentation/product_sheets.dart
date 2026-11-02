import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../auth/application/auth_controller.dart';
import '../application/catalog_providers.dart';

/// "Notify me" bottom sheet for out-of-stock items (`POST stock-requests`).
Future<void> showNotifyMeSheet(
  BuildContext context, {
  required int productId,
  required int variantId,
}) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  useSafeArea: true,
  builder: (_) => _NotifySheet(productId: productId, variantId: variantId),
);

class _NotifySheet extends ConsumerStatefulWidget {
  const _NotifySheet({required this.productId, required this.variantId});

  final int productId;
  final int variantId;

  @override
  ConsumerState<_NotifySheet> createState() => _NotifySheetState();
}

class _NotifySheetState extends ConsumerState<_NotifySheet> {
  late final _name = TextEditingController(
    text: ref.read(authControllerProvider).customer?.name ?? '',
  );
  final _phone = TextEditingController();
  final _note = TextEditingController();
  bool _busy = false;
  ApiException? _error;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final nav = Navigator.of(context);
    final l10n = context.l10n;
    try {
      final message = await ref
          .read(catalogRepositoryProvider)
          .requestStock(
            productId: widget.productId,
            variantId: widget.variantId,
            name: _name.text.trim(),
            phone: _phone.text.trim(),
            note: _note.text,
          );
      nav.pop();
      messenger.showSnackBar(
        SnackBar(content: Text(message ?? l10n.notifyMeSent)),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final e = _error;
    final hasFieldErrors =
        e != null &&
        (e.firstErrorFor('name') != null ||
            e.firstErrorFor('phone') != null ||
            e.firstErrorFor('note') != null);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        MediaQuery.viewInsetsOf(context).bottom + AppSpacing.md,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.notifyMe, style: context.text.titleLarge),
            const SizedBox(height: AppSpacing.xs),
            Text(l10n.notifyMeBody, style: context.text.bodyMedium),
            const SizedBox(height: AppSpacing.md),
            AppTextField(
              label: l10n.fullName,
              controller: _name,
              errorText: e?.firstErrorFor('name'),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.phone,
              controller: _phone,
              keyboardType: TextInputType.phone,
              errorText: e?.firstErrorFor('phone'),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextField(
              label: l10n.noteOptional,
              controller: _note,
              errorText: e?.firstErrorFor('note'),
              maxLines: 2,
            ),
            if (e != null && !hasFieldErrors) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                errorMessage(l10n, e),
                style: TextStyle(color: context.colors.error),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: l10n.notifyMeSend,
              loading: _busy,
              onPressed: _send,
            ),
          ],
        ),
      ),
    );
  }
}

/// Star + text review form (`POST products/{slug}/reviews`). Resolves to the
/// moderation message once saved.
Future<void> showReviewSheet(BuildContext context, {required String slug}) =>
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      builder: (_) => _ReviewSheet(slug: slug),
    );

class _ReviewSheet extends ConsumerStatefulWidget {
  const _ReviewSheet({required this.slug});
  final String slug;

  @override
  ConsumerState<_ReviewSheet> createState() => _ReviewSheetState();
}

class _ReviewSheetState extends ConsumerState<_ReviewSheet> {
  final _body = TextEditingController();
  int _rating = 0;
  bool _busy = false;
  ApiException? _error;
  bool _ratingMissing = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final l10n = context.l10n;
    if (_rating == 0) {
      setState(() => _ratingMissing = true);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _ratingMissing = false;
    });
    final messenger = ScaffoldMessenger.of(context);
    final nav = Navigator.of(context);
    try {
      final message = await ref
          .read(catalogRepositoryProvider)
          .addReview(widget.slug, _rating, _body.text);
      ref.invalidate(productProvider(widget.slug));
      nav.pop();
      messenger.showSnackBar(
        SnackBar(content: Text(message ?? l10n.reviewThanks)),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final e = _error;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        MediaQuery.viewInsetsOf(context).bottom + AppSpacing.md,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.writeReview, style: context.text.titleLarge),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                for (var i = 1; i <= 5; i++)
                  IconButton(
                    tooltip: l10n.ratingSemantics('$i'),
                    iconSize: 36,
                    onPressed: () => setState(() {
                      _rating = i;
                      _ratingMissing = false;
                    }),
                    icon: Icon(
                      i <= _rating
                          ? Icons.star_rounded
                          : Icons.star_border_rounded,
                      color: context.extra.gold,
                    ),
                  ),
              ],
            ),
            AppTextField(
              label: l10n.reviewBody,
              controller: _body,
              maxLines: 4,
              errorText: e?.firstErrorFor('body'),
            ),
            if (_ratingMissing ||
                (e != null && e.firstErrorFor('body') == null)) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                _ratingMissing ? l10n.reviewPickRating : errorMessage(l10n, e!),
                style: TextStyle(color: context.colors.error),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: l10n.submitReview,
              loading: _busy,
              onPressed: _send,
            ),
          ],
        ),
      ),
    );
  }
}
