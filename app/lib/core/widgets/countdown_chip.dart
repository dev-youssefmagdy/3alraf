import 'dart:async';

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Live `HH:MM:SS` countdown to [endsAt]; calls [onFinished] once at zero.
class CountdownChip extends StatefulWidget {
  const CountdownChip({
    super.key,
    required this.endsAt,
    this.onFinished,
    this.now,
    this.units = false,
  });

  final DateTime endsAt;
  final VoidCallback? onFinished;

  /// Clock override for tests.
  final DateTime Function()? now;

  /// Flash header style: three 34-wide sun boxes (value over unit label).
  final bool units;

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
    final x = context.extra;
    if (widget.units) {
      final l10n = context.l10n;
      Widget unit(int v, String label) => Container(
        constraints: const BoxConstraints(minWidth: 34),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
        decoration: BoxDecoration(
          color: x.sun,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              _two(v),
              style: context.text.labelMedium?.copyWith(
                color: AppColors.slate950,
                height: 1.2,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
            Text(
              label,
              style: context.textX.labelXs.copyWith(
                color: AppColors.slate950,
                height: 1.1,
              ),
            ),
          ],
        ),
      );
      return Semantics(
        label: l10n.countdownSemantics(text),
        excludeSemantics: true,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            unit(h, l10n.countdownHours),
            const SizedBox(width: 4),
            unit(_left.inMinutes % 60, l10n.countdownMinutes),
            const SizedBox(width: 4),
            unit(_left.inSeconds % 60, l10n.countdownSeconds),
          ],
        ),
      );
    }
    return Semantics(
      label: text,
      excludeSemantics: true,
      child: Container(
        height: 26,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: BoxDecoration(
          color: x.popSoft,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Directionality(
          textDirection: TextDirection.ltr,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.schedule_rounded, size: 14, color: x.pop),
              const SizedBox(width: 4),
              Text(
                text,
                style: context.textX.labelXs.copyWith(
                  fontSize: 12,
                  color: x.pop,
                  fontWeight: FontWeight.w800,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
