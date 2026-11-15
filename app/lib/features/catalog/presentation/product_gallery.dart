import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/product_models.dart';

/// Product gallery (§10.5): a full-width square [PageView] of images and
/// videos with an ink "1 / 5" counter at the bottom-end, an optional
/// [badge] at the bottom-start and a 60-thumb row linked to the pager
/// (selected = 2px brand border; video thumbs get an ink scrim + play).
/// Videos play muted while their slide is active and pause when it leaves;
/// never under reduced motion. The first slide joins the Hero flight; tapping
/// an image opens the full-screen viewer.
class ProductGallery extends StatefulWidget {
  const ProductGallery({
    super.key,
    required this.images,
    required this.controller,
    this.heroTag,
    this.badge,
  });

  final List<ProductImage> images;
  final PageController controller;
  final Object? heroTag;
  final Widget? badge;

  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  int _page = 0;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_sync);
  }

  @override
  void didUpdateWidget(ProductGallery old) {
    super.didUpdateWidget(old);
    if (old.controller != widget.controller) {
      old.controller.removeListener(_sync);
      widget.controller.addListener(_sync);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_sync);
    super.dispose();
  }

  void _sync() {
    final c = widget.controller;
    if (!c.hasClients || c.page == null) return;
    final p = c.page!.round();
    if (p != _page) setState(() => _page = p);
  }

  void _goTo(int i) => widget.controller.animateToPage(
    i,
    duration: const Duration(milliseconds: 250),
    curve: const Cubic(.2, .8, .2, 1),
  );

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items = widget.images;
    final count = items.isEmpty ? 1 : items.length;
    final stills = [
      for (final m in items)
        if (!m.isVideo) m,
    ];
    final x = context.extra;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AspectRatio(
          aspectRatio: 1,
          child: Stack(
            children: [
              Positioned.fill(
                child: ColoredBox(
                  color: x.cardTint(0),
                  child: PageView.builder(
                    controller: widget.controller,
                    itemCount: count,
                    itemBuilder: (context, i) {
                      if (items.isEmpty) {
                        return const NetworkImageX(
                          url: null,
                          width: double.infinity,
                          height: double.infinity,
                        );
                      }
                      final m = items[i];
                      if (m.isVideo) {
                        return _VideoSlide(media: m, active: i == _page);
                      }
                      Widget image = NetworkImageX(
                        url: m.url,
                        width: double.infinity,
                        height: double.infinity,
                        cacheWidth: 800,
                      );
                      if (i == 0 && widget.heroTag != null) {
                        image = Hero(tag: widget.heroTag!, child: image);
                      }
                      return GestureDetector(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            fullscreenDialog: true,
                            builder: (_) => FullscreenGallery(
                              images: stills,
                              initialPage: stills
                                  .indexOf(m)
                                  .clamp(0, stills.length - 1),
                            ),
                          ),
                        ),
                        child: image,
                      );
                    },
                  ),
                ),
              ),
              if (widget.badge != null)
                PositionedDirectional(
                  bottom: AppSpacing.smd,
                  start: AppSpacing.smd,
                  child: widget.badge!,
                ),
              if (count > 1)
                PositionedDirectional(
                  bottom: AppSpacing.smd,
                  end: AppSpacing.smd,
                  child: Semantics(
                    label: l10n.carouselSlide(_page + 1, count),
                    excludeSemantics: true,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: x.ink.withValues(alpha: 0.85),
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                      ),
                      child: Directionality(
                        textDirection: TextDirection.ltr,
                        child: Text(
                          l10n.galleryCounter(_page + 1, count),
                          style: context.textX.labelXs.copyWith(
                            color: x.onInk,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        if (count > 1)
          SizedBox(
            height: 60 + AppSpacing.smd * 2,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.gutter,
                vertical: AppSpacing.smd,
              ),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
              itemBuilder: (context, i) => _Thumb(
                media: items[i],
                selected: i == _page,
                label: items[i].isVideo
                    ? l10n.galleryVideo
                    : l10n.carouselSlide(i + 1, items.length),
                onTap: () => _goTo(i),
              ),
            ),
          ),
      ],
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({
    required this.media,
    required this.selected,
    required this.label,
    required this.onTap,
  });

  final ProductImage media;
  final bool selected;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final x = context.extra;
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          width: 60,
          height: 60,
          padding: const EdgeInsets.all(2),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.sm),
            border: Border.all(
              color: selected ? c.primary : c.outlineVariant,
              width: selected ? 2 : 1,
            ),
          ),
          child: Stack(
            fit: StackFit.expand,
            children: [
              NetworkImageX(
                url: media.still,
                radius: AppRadius.sm - 3,
                cacheWidth: 120,
              ),
              if (media.isVideo)
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: x.ink.withValues(alpha: 0.45),
                    borderRadius: BorderRadius.circular(AppRadius.sm - 3),
                  ),
                  child: const Icon(
                    Icons.play_arrow_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A muted, looping video slide. The controller is created when the slide
/// first becomes active (so off-screen videos never download) and pauses
/// when it leaves. Under reduced motion the poster stays with a play button.
class _VideoSlide extends StatefulWidget {
  const _VideoSlide({required this.media, required this.active});

  final ProductImage media;
  final bool active;

  @override
  State<_VideoSlide> createState() => _VideoSlideState();
}

class _VideoSlideState extends State<_VideoSlide> {
  VideoPlayerController? _video;
  bool _ready = false;
  bool _failed = false;
  bool _userStarted = false;

  bool get _autoplay => !context.reduceMotion || _userStarted;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _apply();
  }

  @override
  void didUpdateWidget(_VideoSlide old) {
    super.didUpdateWidget(old);
    _apply();
  }

  Future<void> _apply() async {
    if (!widget.active) {
      await _video?.pause();
      return;
    }
    if (!_autoplay || _failed) return;
    if (_video == null) {
      final v = VideoPlayerController.networkUrl(Uri.parse(widget.media.url));
      _video = v;
      try {
        await v.initialize();
        await v.setVolume(0);
        await v.setLooping(true);
        if (!mounted) return;
        setState(() => _ready = true);
      } on Object {
        if (mounted) setState(() => _failed = true);
        return;
      }
    }
    if (mounted && widget.active) await _video?.play();
  }

  @override
  void dispose() {
    _video?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final v = _video;
    final poster = NetworkImageX(
      url: widget.media.still,
      width: double.infinity,
      height: double.infinity,
      cacheWidth: 800,
    );
    return Semantics(
      label: context.l10n.galleryVideo,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (_ready && v != null)
            FittedBox(
              fit: BoxFit.cover,
              clipBehavior: Clip.hardEdge,
              child: SizedBox(
                width: v.value.size.width,
                height: v.value.size.height,
                child: VideoPlayer(v),
              ),
            )
          else
            poster,
          if (!_ready && !_autoplay)
            Center(
              child: IconButton.filled(
                tooltip: context.l10n.galleryVideo,
                iconSize: 32,
                style: IconButton.styleFrom(
                  backgroundColor: context.extra.ink.withValues(alpha: 0.7),
                  foregroundColor: Colors.white,
                ),
                onPressed: () {
                  setState(() => _userStarted = true);
                  _apply();
                },
                icon: const Icon(Icons.play_arrow_rounded),
              ),
            ),
        ],
      ),
    );
  }
}

class FullscreenGallery extends StatefulWidget {
  const FullscreenGallery({
    super.key,
    required this.images,
    required this.initialPage,
  });

  final List<ProductImage> images;
  final int initialPage;

  @override
  State<FullscreenGallery> createState() => _FullscreenGalleryState();
}

class _FullscreenGalleryState extends State<FullscreenGallery> {
  late final PageController _controller = PageController(
    initialPage: widget.initialPage,
  );
  late int _page = widget.initialPage;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text('${_page + 1} / ${widget.images.length}'),
      ),
      body: PageView.builder(
        controller: _controller,
        itemCount: widget.images.length,
        onPageChanged: (i) => setState(() => _page = i),
        itemBuilder: (_, i) => InteractiveViewer(
          minScale: 1,
          maxScale: 5,
          child: Center(
            child: NetworkImageX(
              url: widget.images[i].url,
              fit: BoxFit.contain,
              width: double.infinity,
            ),
          ),
        ),
      ),
    );
  }
}
