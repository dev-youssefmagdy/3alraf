import 'package:app/features/store/application/store_controller.dart';
import 'package:app/features/content/application/content_providers.dart';
import 'package:app/features/content/data/content_models.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/harness.dart';

Map<String, Object?> post(String slug) => {
  'slug': slug,
  'title': 'T $slug',
  'excerpt': 'ex',
  'published_at': '2026-10-31T21:51:44+02:00',
};

void main() {
  test('Launch model: survey / quantity / closed flags', () {
    final l = Launch.fromJson({
      'code': 'abcd1234',
      'name': 'Pilot',
      'mode': 'survey',
      'survey_question': 'Which size?',
      'preorder': true,
      'is_open': true,
      'product': {'slug': 'x', 'name': 'X', 'image': null},
    });
    expect(l.isSurvey, isTrue);
    expect(l.asksQuantity, isTrue);
    expect(l.product?.slug, 'x');
    expect(Launch.fromJson({'mode': 'interest'}).isOpen, isFalse);
  });

  test('blog pages, page detail, launch join body', () async {
    final bodies = <Object?>[];
    final env = TestEnv(
      extra: (o) {
        switch (o.path) {
          case '/blog':
            final page = (o.queryParameters['page'] as int?) ?? 1;
            return envelopeWithMeta(
              [post('p$page')],
              {
                'pagination': {
                  'current_page': page,
                  'last_page': 2,
                  'per_page': 9,
                  'total': 2,
                },
              },
            );
          case '/blog/p1':
            return envelope({...post('p1'), 'content': '<p>Body</p>'});
          case '/pages/terms':
            return envelope({
              'slug': 'terms',
              'title': 'Terms',
              'content': 'C',
            });
          case '/launch/abcd1234':
            if (o.method == 'POST') {
              bodies.add(o.data);
              return envelope({'created': true}, status: 201);
            }
        }
        return null;
      },
    );
    final c = await env.container();
    await bootStore(c);

    c.listen(blogControllerProvider, (_, _) {});
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(c.read(blogControllerProvider).posts.map((p) => p.slug), ['p1']);
    expect(c.read(blogControllerProvider).hasMore, isTrue);
    await c.read(blogControllerProvider.notifier).loadMore();
    expect(c.read(blogControllerProvider).posts.map((p) => p.slug), [
      'p1',
      'p2',
    ]);
    expect(c.read(blogControllerProvider).hasMore, isFalse);

    expect(
      (await c.read(blogPostProvider('p1').future)).content,
      '<p>Body</p>',
    );
    expect((await c.read(staticPageProvider('terms').future)).title, 'Terms');

    final created = await c
        .read(contentRepositoryProvider)
        .joinLaunch(
          'abcd1234',
          contact: '010',
          quantity: 2,
          preorderAccepted: true,
        );
    expect(created, isTrue);
    expect(bodies.single, {
      'contact': '010',
      'quantity': 2,
      'preorder_accepted': true,
      'consent': true,
    });
  });

  test('content providers refetch on language change', () async {
    final env = TestEnv(
      extra: (o) {
        if (o.path == '/pages') {
          return envelope([
            {'slug': 'terms-${o.headers['Accept-Language']}', 'title': 'T'},
          ]);
        }
        return null;
      },
    );
    final c = await env.container();
    await bootStore(c);
    expect((await c.read(pagesProvider.future)).single.slug, 'terms-ar');
    await c.read(storeControllerProvider.notifier).chooseLocale('en');
    expect((await c.read(pagesProvider.future)).single.slug, 'terms-en');
  });
}
