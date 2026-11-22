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
    final gap = widget.gap;
    final Widget scroller;
    if (w == null) {
      scroller = ListView.separated(
        controller: _controller,
        scrollDirection: Axis.horizontal,
        padding: widget.padding,
        itemCount: widget.itemCount,
        separatorBuilder: (_, _) => SizedBox(width: gap),
        itemBuilder: widget.itemBuilder,
      );
    } else {
      // Fixed item extent (card + trailing gap): the scroll length is exact
      // from the first frame, so a fling is never restarted by the list
      // re-estimating its length (that used to carry a swipe to the end).
      // The last card's trailing gap is taken out of the end padding.
      final pad = widget.padding.resolve(Directionality.of(context));
      final rtl = Directionality.of(context) == TextDirection.rtl;
      final start = rtl ? pad.right : pad.left;
      final end = rtl ? pad.left : pad.right;
      scroller = ListView.builder(
        controller: _controller,
        scrollDirection: Axis.horizontal,
        padding: EdgeInsetsDirectional.fromSTEB(
          start,
          pad.top,
          (end - gap).clamp(0, double.infinity),
          pad.bottom,
        ),
        physics: widget.snap ? SnapScrollPhysics(itemExtent: w + gap) : null,
        itemExtent: w + gap,
        itemCount: widget.itemCount,
        itemBuilder: (context, i) => Padding(
          padding: EdgeInsetsDirectional.only(end: gap),
          child: widget.itemBuilder(context, i),
        ),
      );
    }
    final list = SizedBox(height: widget.height, child: scroller);
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
