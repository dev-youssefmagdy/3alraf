import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../application/proof_picker.dart';

/// Asks camera or gallery, then returns the chosen file path.
Future<String?> pickProof(BuildContext context, WidgetRef ref) async {
  final l10n = context.l10n;
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: Text(l10n.proofGallery),
            onTap: () => Navigator.pop(ctx, ImageSource.gallery),
          ),
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: Text(l10n.proofCamera),
            onTap: () => Navigator.pop(ctx, ImageSource.camera),
          ),
        ],
      ),
    ),
  );
  if (source == null) return null;
  return ref.read(proofPickerProvider)(source);
}

/// Thumbnail of the chosen proof with change / remove actions.
class ProofPreview extends StatelessWidget {
  const ProofPreview({
    super.key,
    required this.path,
    required this.onChange,
    this.onRemove,
  });

  final String? path;
  final VoidCallback onChange;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final p = path;
    if (p == null) {
      return OutlinedButton.icon(
        onPressed: onChange,
        icon: const Icon(Icons.upload_file_rounded),
        label: Text(l10n.proofChoose),
      );
    }
    return Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(AppRadius.sm),
          child: Image.file(
            File(p),
            width: 72,
            height: 72,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const SizedBox(
              width: 72,
              height: 72,
              child: Icon(Icons.insert_drive_file_outlined),
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(l10n.proofAttached, style: context.text.bodyMedium),
        ),
        TextButton(onPressed: onChange, child: Text(l10n.proofChange)),
        if (onRemove != null)
          IconButton(
            tooltip: l10n.proofRemove,
            onPressed: onRemove,
            icon: const Icon(Icons.close_rounded),
          ),
      ],
    );
  }
}

/// Upload block for an existing order (wallet transfer proof).
class ProofUploader extends ConsumerStatefulWidget {
  const ProofUploader({super.key, required this.onUpload});

  /// Uploads the file; throw on failure so the error is shown.
  final Future<void> Function(String path) onUpload;

  @override
  ConsumerState<ProofUploader> createState() => _ProofUploaderState();
}

class _ProofUploaderState extends ConsumerState<ProofUploader> {
  String? _path;
  bool _busy = false;

  Future<void> _choose() async {
    final p = await pickProof(context, ref);
    if (p != null && mounted) setState(() => _path = p);
  }

  Future<void> _send() async {
    final p = _path;
    if (p == null) return;
    setState(() => _busy = true);
    try {
      await widget.onUpload(p);
      if (mounted) setState(() => _path = null);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ProofPreview(
          path: _path,
          onChange: _choose,
          onRemove: _path == null ? null : () => setState(() => _path = null),
        ),
        if (_path != null) ...[
          const SizedBox(height: AppSpacing.sm),
          AppButton(
            label: l10n.orderUploadProof,
            icon: Icons.cloud_upload_outlined,
            loading: _busy,
            onPressed: _send,
          ),
        ],
      ],
    );
  }
}
