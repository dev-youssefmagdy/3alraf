import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/context_ext.dart';

/// Opens an App/Bottom sheet (`47:657`): white, top radius 24, scrim at
/// 50 %, safe-area aware and scroll-controlled. Build the content with
/// [AppSheet] for the handle, header, padding and sticky actions.
Future<T?> showAppBottomSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
  bool isDismissible = true,
  bool useRootNavigator = false,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: false,
    isDismissible: isDismissible,
    useRootNavigator: useRootNavigator,
    barrierColor: Colors.black.withValues(alpha: 0.5),
    builder: builder,
  );
}

/// Sheet body: 40×4 handle, H2 [title] with a 44 close button at the
/// inline-end, 16 content padding, and an optional sticky [actions] row
/// (pass AppButtons; they get equal widths).
class AppSheet extends StatelessWidget {
  const AppSheet({
    super.key,
    required this.child,
    this.title,
    this.actions = const [],
    this.scrollable = true,
  });

  final String? title;
  final Widget child;
  final List<Widget> actions;
  final bool scrollable;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final inset = MediaQuery.viewInsetsOf(context).bottom;
    final body = Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      child: child,
    );
    return Padding(
      padding: EdgeInsets.only(bottom: inset),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 10, bottom: 6),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: c.outline,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          if (title != null)
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(
                AppSpacing.md,
                0,
                AppSpacing.xs,
                AppSpacing.sm,
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Semantics(
                      header: true,
                      child: Text(title!, style: context.text.headlineSmall),
                    ),
                  ),
                  IconButton(
                    tooltip: context.l10n.close,
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.of(context).maybePop(),
                  ),
                ],
              ),
            ),
          Flexible(
            child: scrollable ? SingleChildScrollView(child: body) : body,
          ),
          if (actions.isNotEmpty)
            DecoratedBox(
              decoration: BoxDecoration(
                color: c.surfaceContainerLowest,
                boxShadow: AppShadows.bar,
              ),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  children: [
                    for (var i = 0; i < actions.length; i++) ...[
                      if (i > 0) const SizedBox(width: AppSpacing.smd),
                      Expanded(child: actions[i]),
                    ],
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
