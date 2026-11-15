import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Pop count badge (18 high, 2px white ring) at the top-end of [child].
/// Hidden at zero; "99+" above 99. Pops (scale bounce) whenever [count]
/// changes, unless reduced motion is on.
class CountBadge extends StatefulWidget {
  const CountBadge({super.key, required this.count, required this.child});

  final int count;
  final Widget child;

  @override
  State<CountBadge> createState() => _CountBadgeState();
}

class _CountBadgeState extends State<CountBadge>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pop = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 320),
  );
  late final Animation<double> _scale = TweenSequence<double>([
    TweenSequenceItem(tween: Tween(begin: 1.0, end: 1.35), weight: 40),
    TweenSequenceItem(tween: Tween(begin: 1.35, end: 1.0), weight: 60),
  ]).animate(CurvedAnimation(parent: _pop, curve: Curves.easeOut));

  @override
  void didUpdateWidget(CountBadge old) {
    super.didUpdateWidget(old);
    if (old.count != widget.count && !context.reduceMotion) {
      _pop.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _pop.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.count;
    if (n <= 0) return widget.child;
    return Stack(
      clipBehavior: Clip.none,
      children: [
        widget.child,
        PositionedDirectional(
          top: -7,
          end: -9,
          child: ScaleTransition(
            scale: _scale,
            child: Container(
              constraints: const BoxConstraints(minWidth: 18),
              height: 18,
              padding: const EdgeInsets.symmetric(horizontal: 4),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: context.extra.pop,
                borderRadius: BorderRadius.circular(9),
                border: Border.all(
                  color: context.colors.surfaceContainerLowest,
                  width: 2,
                ),
              ),
              child: Text(
                n > 99 ? '99+' : '$n',
                textDirection: TextDirection.ltr,
                style: context.textX.labelXs.copyWith(
                  color: Colors.white,
                  fontSize: 10,
                  height: 1,
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
