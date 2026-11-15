import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import 'carousel.dart';

/// Swiper-like horizontal rail (§10.5): fixed-height items with a 12 gap and
/// 16 start/end padding. When [itemWidth] is set the rail snaps to item
/// start edges (right edge in RTL) and the next card peeks (156 cards show
/// about 2.3 on a 390 screen). [showProgress] adds the 4px progress track
/// (brand fill growing from the start side). Never autoplays.
class HorizontalRail extends StatefulWidget {
  const HorizontalRail({
    super.key,
    required this.height,
    required this.itemCount,
    required this.itemBuilder,
    this.itemWidth,
    this.gap = AppSpacing.grid,
    this.padding = const EdgeInsets.symmetric(horizontal: AppSpacing.gutter),
    this.snap = true,
    this.showProgress = false,
    this.progressColor,
    this.progressTrackColor,
  });

  final double height;
  final double? itemWidth;
  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final double gap;
  final EdgeInsetsGeometry padding;

  /// Snap to items; only applies when [itemWidth] is known.
  final bool snap;
  final bool showProgress;

  /// Progress fill / track overrides (e.g. on the pop flash block).
  final Color? progressColor;
  final Color? progressTrackColor;

  @override
  State<HorizontalRail> createState() => _HorizontalRailState();
}

class _HorizontalRailState extends State<HorizontalRail> {
  final _controller = ScrollController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final w = widget.itemWidth;
    final list = SizedBox(
      height: widget.height,
      child: ListView.separated(
        controller: _controller,
        scrollDirection: Axis.horizontal,
        padding: widget.padding,
        physics: widget.snap && w != null
            ? SnapScrollPhysics(itemExtent: w + widget.gap)
            : null,
        itemCount: widget.itemCount,
        separatorBuilder: (_, _) => SizedBox(width: widget.gap),
        itemBuilder: (context, i) {
          final child = widget.itemBuilder(context, i);
          return w == null ? child : SizedBox(width: w, child: child);
        },
      ),
    );
    if (!widget.showProgress) return list;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        list,
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.gutter,
            AppSpacing.smd,
            AppSpacing.gutter,
            0,
          ),
          child: RailProgress(
            controller: _controller,
            color: widget.progressColor,
            trackColor: widget.progressTrackColor,
          ),
        ),
      ],
    );
  }
}

/// 4px rail progress: `outlineVariant` track, brand fill from the start side.
class RailProgress extends StatefulWidget {
  const RailProgress({
    super.key,
    required this.controller,
    this.color,
    this.trackColor,
  });

  final ScrollController controller;
  final Color? color;
  final Color? trackColor;

  @override
  State<RailProgress> createState() => _RailProgressState();
}

class _RailProgressState extends State<RailProgress> {
  @override
  void initState() {
    super.initState();
    // The rail has no dimensions until after its first layout.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) setState(() {});
    });
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final scheme = Theme.of(context).colorScheme;
    return ExcludeSemantics(
      child: AnimatedBuilder(
        animation: controller,
        builder: (context, _) {
          var f = 1.0;
          if (controller.hasClients &&
              controller.position.hasContentDimensions) {
            final p = controller.position;
            final total = p.maxScrollExtent + p.viewportDimension;
            if (total > 0) {
              f = ((p.pixels + p.viewportDimension) / total).clamp(0.08, 1.0);
            }
          }
          return Container(
            height: 4,
            decoration: BoxDecoration(
              color: widget.trackColor ?? scheme.outlineVariant,
              borderRadius: BorderRadius.circular(2),
            ),
            alignment: AlignmentDirectional.centerStart,
            child: FractionallySizedBox(
              widthFactor: f,
              child: Container(
                decoration: BoxDecoration(
                  color: widget.color ?? scheme.primary,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
