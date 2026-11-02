import 'dart:async';

import 'package:flutter/material.dart';

import '../utils/context_ext.dart';

/// Live `HH:MM:SS` countdown to [endsAt]; calls [onFinished] once at zero.
class CountdownChip extends StatefulWidget {
  const CountdownChip({
    super.key,
    required this.endsAt,
    this.onFinished,
    this.now,
  });

  final DateTime endsAt;
  final VoidCallback? onFinished;

  /// Clock override for tests.
  final DateTime Function()? now;

  @override
  State<CountdownChip> createState() => _CountdownChipState();
}

class _CountdownChipState extends State<CountdownChip> {
  Timer? _timer;
  late Duration _left;
  bool _fired = false;

  DateTime _now() => (widget.now ?? DateTime.now)();

  @override
  void initState() {
    super.initState();
    _tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  void _tick() {
    final left = widget.endsAt.difference(_now());
    if (!mounted) return;
    setState(() => _left = left.isNegative ? Duration.zero : left);
    if (_left == Duration.zero && !_fired) {
      _fired = true;
      _timer?.cancel();
      widget.onFinished?.call();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  static String _two(int n) => n.toString().padLeft(2, '0');

  @override
  Widget build(BuildContext context) {
    final h = _left.inHours;
    final text =
        '${_two(h)}:${_two(_left.inMinutes % 60)}:${_two(_left.inSeconds % 60)}';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: context.colors.error,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Directionality(
        textDirection: TextDirection.ltr,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.timer_outlined, size: 14, color: Colors.white),
            const SizedBox(width: 4),
            Text(
              text,
              style: context.text.labelMedium?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
