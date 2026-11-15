import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../utils/context_ext.dart';

/// App/Text field (`47:536`): label above (Body/M Strong, 6 gap), a 48-high
/// white control with radius 12, 2px pop focus and a 2px red error border
/// with an alert row. Supports inline server (422) errors via [errorText].
class AppTextField extends StatefulWidget {
  const AppTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.errorText,
    this.helperText,
    this.keyboardType,
    this.textInputAction,
    this.obscure = false,
    this.validator,
    this.onChanged,
    this.onSubmitted,
    this.prefixIcon,
    this.prefixText,
    this.autofillHints,
    this.maxLines = 1,
    this.inputFormatters,
    this.enabled = true,
    this.ltr = false,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final String? errorText;
  final String? helperText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscure;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final IconData? prefixIcon;

  /// Fixed prefix block (e.g. `+20` for phone fields).
  final String? prefixText;
  final Iterable<String>? autofillHints;
  final int maxLines;
  final List<TextInputFormatter>? inputFormatters;
  final bool enabled;

  /// Force left-to-right input (phone numbers, codes, URLs, e-mails).
  final bool ltr;

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  late bool _hidden = widget.obscure;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final prefix = widget.prefixText == null
        ? null
        : Container(
            margin: const EdgeInsetsDirectional.only(end: 10),
            padding: const EdgeInsets.symmetric(horizontal: 12),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: c.surfaceContainerHighest,
              borderRadius: const BorderRadiusDirectional.horizontal(
                start: Radius.circular(11),
              ).resolve(Directionality.of(context)),
            ),
            child: Text(
              widget.prefixText!,
              textDirection: TextDirection.ltr,
              style: context.text.titleSmall,
            ),
          );
    final field = TextFormField(
      controller: widget.controller,
      enabled: widget.enabled,
      obscureText: _hidden,
      maxLines: widget.obscure ? 1 : widget.maxLines,
      keyboardType: widget.keyboardType,
      textInputAction: widget.textInputAction,
      autofillHints: widget.autofillHints,
      inputFormatters: widget.inputFormatters,
      validator: widget.validator,
      onChanged: widget.onChanged,
      onFieldSubmitted: widget.onSubmitted,
      textDirection: widget.ltr ? TextDirection.ltr : null,
      style: context.text.bodyLarge,
      decoration: InputDecoration(
        hintText: widget.hint,
        errorText: widget.errorText,
        helperText: widget.helperText,
        errorMaxLines: 3,
        fillColor: widget.enabled
            ? null
            : c.surfaceContainerHighest.withValues(alpha: 0.6),
        prefixIcon:
            prefix ??
            (widget.prefixIcon == null ? null : Icon(widget.prefixIcon)),
        prefixIconConstraints: prefix == null
            ? null
            : const BoxConstraints(minHeight: 46),
        suffixIcon: widget.obscure
            ? IconButton(
                tooltip: _hidden
                    ? context.l10n.showPassword
                    : context.l10n.hidePassword,
                icon: Icon(
                  _hidden
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                ),
                onPressed: () => setState(() => _hidden = !_hidden),
              )
            : null,
      ),
    );
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Text(widget.label, style: context.text.titleSmall),
        ),
        field,
      ],
    );
  }
}
