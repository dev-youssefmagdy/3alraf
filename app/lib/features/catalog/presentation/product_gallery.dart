import 'package:flutter/material.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

import '../../../core/utils/context_ext.dart';
import '../../../core/widgets/widgets.dart';
import '../data/product_models.dart';

/// Swipeable product images; the first one takes part in the list -> detail
/// Hero flight. Tap opens a full-screen viewer with pinch zoom.
class ProductGallery extends StatelessWidget {
  const ProductGallery({
    super.key,
    required this.images,
    required this.controller,
    this.heroTag,
  });

  final List<ProductImage> images;
  final PageController controller;
  final Object? heroTag;

  @override
  Widget build(BuildContext context) {
    final count = images.isEmpty ? 1 : images.length;
    return AspectRatio(
      aspectRatio: 1,
      child: Stack(
        alignment: Alignment.bottomCenter,
        children: [
          PageView.builder(
            controller: controller,
            itemCount: count,
            itemBuilder: (context, i) {
              final url = images.isEmpty ? null : images[i].url;
              Widget image = NetworkImageX(
                url: url,
                width: double.infinity,
                height: double.infinity,
                fit: BoxFit.contain,
                cacheWidth: 800,
              );
              if (i == 0 && heroTag != null) {
                image = Hero(tag: heroTag!, child: image);
              }
              return GestureDetector(
                onTap: images.isEmpty
                    ? null
                    : () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          fullscreenDialog: true,
                          builder: (_) =>
                              FullscreenGallery(images: images, initialPage: i),
                        ),
                      ),
                child: image,
              );
            },
          ),
          if (count > 1)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: SmoothPageIndicator(
                controller: controller,
                count: count,
                effect: ExpandingDotsEffect(
                  dotHeight: 6,
                  dotWidth: 6,
                  activeDotColor: context.colors.primary,
                  dotColor: context.colors.outlineVariant,
                ),
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
