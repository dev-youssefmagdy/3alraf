import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/interceptors.dart';
import '../../../core/network/json.dart';
import 'content_models.dart';

/// Static pages, custom pages, blog and small-batch launch pages.
class ContentRepository {
  const ContentRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<List<PageLink>> pages() => _guard(() async {
    final r = await _dio.get<Object?>('/pages');
    return asMapList(r.data).map(PageLink.fromJson).toList();
  });

  Future<StaticPage> page(String slug) => _guard(() async {
    final r = await _dio.get<Object?>('/pages/$slug');
    return StaticPage.fromJson(asMap(r.data));
  });

  Future<List<PageLink>> customPages() => _guard(() async {
    final r = await _dio.get<Object?>('/custom-pages');
    return asMapList(r.data).map(PageLink.fromJson).toList();
  });

  Future<BlogPage> blog({int page = 1, int perPage = 9}) => _guard(() async {
    final r = await _dio.get<Object?>(
      '/blog',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    final pg = asMap(r.envelopeMeta['pagination']);
    return BlogPage(
      posts: asMapList(r.data).map(BlogPost.fromJson).toList(),
      currentPage: intOf(pg['current_page'], page),
      lastPage: intOf(pg['last_page'], page),
    );
  });

  Future<BlogPost> post(String slug) => _guard(() async {
    final r = await _dio.get<Object?>('/blog/$slug');
    return BlogPost.fromJson(asMap(r.data));
  });

  Future<Launch> launch(String code) => _guard(() async {
    final r = await _dio.get<Object?>('/launch/$code');
    return Launch.fromJson(asMap(r.data));
  });

  /// Returns `true` when a new entry was created, `false` for a repeat.
  Future<bool> joinLaunch(
    String code, {
    required String contact,
    String? answer,
    int? quantity,
    bool preorderAccepted = false,
  }) => _guard(() async {
    final r = await _dio.post<Object?>(
      '/launch/$code',
      data: {
        'contact': contact,
        if (answer != null && answer.isNotEmpty) 'answer': answer,
        'quantity': ?quantity,
        if (preorderAccepted) 'preorder_accepted': true,
        'consent': true,
      },
    );
    return boolOf(asMap(r.data)['created']);
  });
}
