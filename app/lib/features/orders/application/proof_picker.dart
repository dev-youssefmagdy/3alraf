import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

/// Picks a payment-proof image and returns its local path (null = cancelled).
typedef ProofPicker = Future<String?> Function(ImageSource source);

final proofPickerProvider = Provider<ProofPicker>(
  (ref) => (source) async {
    final file = await ImagePicker().pickImage(
      source: source,
      maxWidth: 2200,
      imageQuality: 85,
    );
    return file?.path;
  },
);
