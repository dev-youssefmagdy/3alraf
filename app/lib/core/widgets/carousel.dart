import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/physics.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Swiper-like snapping for horizontal `ListView` rails (§10.5): a fling is
/// projected with friction and then settles on the nearest item start edge
/// (the right edge in RTL). [itemExtent] is item width + gap.
class SnapScrollPhysics extends ScrollPhysics {
  const SnapScrollPhysics({required this.itemExtent, super.parent});

  final double itemExtent;

  @override
  SnapScrollPhysics applyTo(ScrollPhysics? ancestor) =>
      SnapScrollPhysics(itemExtent: itemExtent, parent: buildParent(ancestor));

  double _target(ScrollMetrics position, double velocity) {
    final projected = FrictionSimulation(
      0.135,
      position.pixels,
      velocity,
    ).finalX;
    // Never skip more than ~3 items on a single swipe.
    final from = position.pixels / itemExtent;
    var page = (projected / itemExtent).roundToDouble();
    page = page.clamp(from.floorToDouble() - 3, from.ceilToDouble() + 3);
    if (page == from.roundToDouble() && velocity.abs() > 300) {
      page = velocity > 0 ? from.floorToDouble() + 1 : from.ceilToDouble() - 1;
    }
    return (page * itemExtent).clamp(
      position.minScrollExtent,
      position.maxScrollExtent,
    );
  }

  @override
  Simulation? createBallisticSimulation(
    ScrollMetrics position,
    double velocity,
  ) {
    if ((velocity <= 0 && position.pixels <= position.minScrollExtent) ||
        (velocity >= 0 && position.pixels >= position.maxScrollExtent)) {
      return super.createBallisticSimulation(position, velocity);
    }
    final tolerance = toleranceFor(position);
    final target = _target(position, velocity);
    if ((target - position.pixels).abs() < tolerance.distance) return null;
    return ScrollSpringSimulation(
      spring,
      position.pixels,
      target,
      velocity,
      tolerance: tolerance,
    );
  }

  @override
  bool get allowImplicitScrolling => false;
}

/// App/Carousel dots (`46:284`): active 20×6 brand pill, inactive 6×6
/// slate-300, gap 6, width animates over 250ms. Follows [Directionality].
class CarouselDots extends StatelessWidget {
  const CarouselDots({
    super.key,
    required this.index,
    required this.count,
    this.activeColor,
    this.color,
  });

  final int index;
  final int count;
  final Color? activeColor;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.carouselSlide(index + 1, count),
      excludeSemantics: true,
      child: AnimatedSmoothIndicator(
        activeIndex: index,
        count: count,
        duration: const Duration(milliseconds: 250),
        effect: ExpandingDotsEffect(
          dotHeight: 6,
          dotWidth: 6,
          expansionFactor: 20 / 6,
          spacing: 6,
          activeDotColor: activeColor ?? context.colors.primary,
          dotColor: color ?? context.colors.outline,
        ),
      ),
    );
  }
}

/// App/Hero carousel (`46:285`): full-width slides (radius 24) inside the 16
/// gutter with dots 12 below. The only autoplaying row: every 5s with a
/// 400ms cubic(.2,.8,.2,1) slide, paused while touched, while the route is
/// covered or the app is in the background, and off entirely under reduced
/// motion. Infinite when there is more than one slide; first slide on the
/// right in RTL.
class HeroCarousel extends StatefulWidget {
  const HeroCarousel({
    super.key,
    required this.itemCount,
    required this.itemBuilder,
    this.aspectRatio = 358 / 184,
    this.interval = const Duration(seconds: 5),
    this.autoPlay = true,
    this.padding = const EdgeInsets.symmetric(horizontal: AppSpacing.gutter),
    this.onPageChanged,
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final double aspectRatio;
  final Duration interval;
  final bool autoPlay;
  final EdgeInsetsGeometry padding;
  final ValueChanged<int>? onPageChanged;

  static const curve = Cubic(.2, .8, .2, 1);

  @override
  State<HeroCarousel> createState() => _HeroCarouselState();
}

class _HeroCarouselState extends State<HeroCarousel>
    with WidgetsBindingObserver {
  static const _loops = 1000;
  late final PageController _controller = PageController(
    initialPage: _many ? widget.itemCount * _loops : 0,
  );
  Timer? _timer;
  int _index = 0;
  bool _touching = false;
  bool _foreground = true;

  bool get _many => widget.itemCount > 1;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncTimer();
  }

  @override
  void didUpdateWidget(HeroCarousel old) {
    super.didUpdateWidget(old);
    if (old.itemCount != widget.itemCount && _index >= widget.itemCount) {
      _index = 0;
    }
    _syncTimer();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    _syncTimer();
  }

  bool get _shouldPlay =>
      widget.autoPlay &&
      _many &&
      _foreground &&
      !_touching &&
      mounted &&
      !context.reduceMotion;

  void _syncTimer() {
    if (_shouldPlay) {
      _timer ??= Timer.periodic(widget.interval, (_) => _advance());
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  void _advance() {
    if (!mounted || !_controller.hasClients) return;
    if (!TickerMode.valuesOf(context).enabled) return; // route covered
    _controller.nextPage(
      duration: const Duration(milliseconds: 400),
      curve: HeroCarousel.curve,
    );
  }

  void _setTouching(bool v) {
    _touching = v;
    // Restart the interval after a drag so a slide never jumps right away.
    _timer?.cancel();
    _timer = null;
    _syncTimer();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.itemCount;
    if (n == 0) return const SizedBox.shrink();
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: widget.padding,
          child: AspectRatio(
            aspectRatio: widget.aspectRatio,
            child: Listener(
              onPointerDown: (_) => _setTouching(true),
              onPointerUp: (_) => _setTouching(false),
              onPointerCancel: (_) => _setTouching(false),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.lg),
                child: PageView.builder(
                  controller: _controller,
                  itemCount: _many ? null : 1,
                  onPageChanged: (i) {
                    setState(() => _index = i % n);
                    widget.onPageChanged?.call(i % n);
                  },
                  itemBuilder: (c, i) => widget.itemBuilder(c, i % n),
                ),
              ),
            ),
          ),
        ),
        if (_many)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.smd),
            child: CarouselDots(index: _index, count: n),
          ),
      ],
    );
  }
}
