import 'package:flutter/material.dart';

/// Store logo image (the only thing in the Home app bar: no text label).
/// The asset has generous transparent padding, so it is zoomed to fill
/// [width]×[height]. [semanticLabel] carries the store name for screen
/// readers. In dark mode the logo sits on a small white plate so the dark
/// lettering stays readable.
class AppLogo extends StatelessWidget {
  const AppLogo({
    super.key,
    required this.semanticLabel,
    this.width = 56,
    this.height = 46,
    this.zoom = 1.6,
  });

  static const asset = 'assets/branding/splash_logo.png';

  final String semanticLabel;
  final double width;
  final double height;

  /// How far to zoom into the padded asset.
  final double zoom;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final side = height * zoom;
    Widget logo = SizedBox(
      width: width,
      height: height,
      child: ClipRect(
        child: OverflowBox(
          maxWidth: side,
          maxHeight: side,
          child: Image.asset(
            asset,
            width: side,
            height: side,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.medium,
            excludeFromSemantics: true,
          ),
        ),
      ),
    );
    if (dark) {
      logo = DecoratedBox(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
        ),
        child: logo,
      );
    }
    return Semantics(
      key: const ValueKey('app-logo'),
      image: true,
      label: semanticLabel,
      child: logo,
    );
  }
}
