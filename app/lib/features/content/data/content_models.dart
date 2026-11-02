import '../../../core/network/json.dart';

/// Entry of `GET /pages` or `GET /custom-pages`.
class PageLink {
  const PageLink({required this.slug, required this.title});

  factory PageLink.fromJson(Map<String, Object?> j) =>
      PageLink(slug: str(j['slug']), title: str(j['title']));

  final String slug;
  final String title;
}

/// `GET /pages/{slug}`.
class StaticPage {
  const StaticPage({
    required this.slug,
    required this.title,
    required this.content,
  });

  factory StaticPage.fromJson(Map<String, Object?> j) => StaticPage(
    slug: str(j['slug']),
    title: str(j['title']),
    content: str(j['content']),
  );

  final String slug;
  final String title;

  /// Plain text or light HTML.
  final String content;
}

/// `GET /blog` item / `GET /blog/{slug}` (with [content]).
class BlogPost {
  const BlogPost({
    required this.slug,
    required this.title,
    this.excerpt,
    this.publishedAt,
    this.url,
    this.content,
  });

  factory BlogPost.fromJson(Map<String, Object?> j) => BlogPost(
    slug: str(j['slug']),
    title: str(j['title']),
    excerpt: strOrNull(j['excerpt']),
    publishedAt: dateOrNull(j['published_at']),
    url: strOrNull(j['url']),
    content: strOrNull(j['content']),
  );

  final String slug;
  final String title;
  final String? excerpt;
  final DateTime? publishedAt;
  final String? url;
  final String? content;
}

class BlogPage {
  const BlogPage({
    required this.posts,
    required this.currentPage,
    required this.lastPage,
  });

  final List<BlogPost> posts;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

class LaunchProduct {
  const LaunchProduct({required this.slug, required this.name, this.image});

  factory LaunchProduct.fromJson(Map<String, Object?> j) => LaunchProduct(
    slug: str(j['slug']),
    name: str(j['name']),
    image: strOrNull(j['image']),
  );

  final String slug;
  final String name;
  final String? image;
}

/// `GET /launch/{code}` (small-batch launch page).
class Launch {
  const Launch({
    required this.code,
    required this.name,
    required this.mode,
    required this.isOpen,
    this.description,
    this.surveyQuestion,
    this.preorder = false,
    this.preorderTerms,
    this.endsOn,
    this.product,
  });

  factory Launch.fromJson(Map<String, Object?> j) {
    final p = asMap(j['product']);
    return Launch(
      code: str(j['code']),
      name: str(j['name']),
      mode: str(j['mode']),
      isOpen: boolOf(j['is_open']),
      description: strOrNull(j['description']),
      surveyQuestion: strOrNull(j['survey_question']),
      preorder: boolOf(j['preorder']),
      preorderTerms: strOrNull(j['preorder_terms']),
      endsOn: strOrNull(j['ends_on']),
      product: p.isEmpty ? null : LaunchProduct.fromJson(p),
    );
  }

  final String code;
  final String name;
  final String mode;
  final bool isOpen;
  final String? description;
  final String? surveyQuestion;
  final bool preorder;
  final String? preorderTerms;
  final String? endsOn;
  final LaunchProduct? product;

  bool get isSurvey => mode == 'survey' && surveyQuestion != null;

  /// Modes that let the visitor pick a quantity.
  bool get asksQuantity => mode == 'limited_stock' || preorder;
}
