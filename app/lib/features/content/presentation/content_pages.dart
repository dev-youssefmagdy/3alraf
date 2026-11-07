import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/routing/app_routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/context_ext.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/widgets/widgets.dart';
import '../../catalog/presentation/catalog_widgets.dart';
import '../../orders/presentation/order_widgets.dart';
import '../../store/application/store_controller.dart';
import '../application/content_providers.dart';
import '../data/content_models.dart';

/// Server HTML rendered with the safe core renderer (no JS / WebView).
/// Links open inside the app when they point at the store.
class SafeHtml extends StatelessWidget {
  const SafeHtml(this.html, {super.key});

  final String html;

  @override
  Widget build(BuildContext context) => HtmlWidget(
    html,
    textStyle: context.text.bodyMedium,
    onTapUrl: (url) async {
      await openLink(context, url);
      return true;
    },
  );
}

/// `GET /pages/{slug}` (terms, privacy, about...).
class StaticPageView extends ConsumerWidget {
  const StaticPageView({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final page = ref.watch(staticPageProvider(slug));
    return Scaffold(
      appBar: AppBar(title: Text(page.asData?.value.title ?? '')),
      body: page.when(
        loading: () => const _TextSkeleton(),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(staticPageProvider(slug)),
        ),
        data: (p) => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Text(p.title, style: context.text.headlineSmall),
            const SizedBox(height: AppSpacing.md),
            SafeHtml(p.content),
          ],
        ),
      ),
    );
  }
}

class _TextSkeleton extends StatelessWidget {
  const _TextSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(AppSpacing.md),
    children: [
      for (var i = 0; i < 6; i++) ...[
        const AppShimmer(height: 16, radius: AppRadius.sm),
        const SizedBox(height: AppSpacing.sm),
      ],
    ],
  );
}

/// Blog list with infinite scroll and pull-to-refresh.
class BlogListPage extends ConsumerStatefulWidget {
  const BlogListPage({super.key});

  @override
  ConsumerState<BlogListPage> createState() => _BlogListPageState();
}

class _BlogListPageState extends ConsumerState<BlogListPage> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 400) {
        ref.read(blogControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final s = ref.watch(blogControllerProvider);
    final c = ref.read(blogControllerProvider.notifier);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.blogTitle)),
      body: Builder(
        builder: (context) {
          if (s.posts.isEmpty && s.loading) {
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                for (var i = 0; i < 4; i++) ...[
                  const AppShimmer(height: 88, radius: AppRadius.md),
                  const SizedBox(height: AppSpacing.sm),
                ],
              ],
            );
          }
          if (s.posts.isEmpty && s.error != null) {
            return ErrorState(error: s.error, onRetry: c.refresh);
          }
          if (s.posts.isEmpty) {
            return EmptyState(
              icon: Icons.article_outlined,
              title: l10n.blogEmpty,
            );
          }
          return RefreshIndicator(
            onRefresh: c.refresh,
            child: ListView.builder(
              controller: _scroll,
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: s.posts.length + (s.hasMore ? 1 : 0),
              itemBuilder: (context, i) {
                if (i >= s.posts.length) {
                  return Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Center(
                      child: s.loadMoreFailed
                          ? TextButton(
                              onPressed: c.loadMore,
                              child: Text(l10n.retry),
                            )
                          : const CircularProgressIndicator(),
                    ),
                  );
                }
                final post = s.posts[i];
                return Card(
                  key: ValueKey('post:${post.slug}'),
                  margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(AppSpacing.md),
                    title: Text(post.title, style: context.text.titleMedium),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        [
                          formatDate(post.publishedAt).split(' ').first,
                          if (post.excerpt != null) post.excerpt!,
                        ].where((e) => e.isNotEmpty).join('\n'),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    onTap: () => context.push(AppRoutes.blogPost(post.slug)),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class BlogPostPage extends ConsumerWidget {
  const BlogPostPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final post = ref.watch(blogPostProvider(slug));
    return Scaffold(
      appBar: AppBar(
        title: Text(post.asData?.value.title ?? l10n.blogTitle),
        actions: [
          if (post.asData?.value.url != null)
            IconButton(
              tooltip: l10n.share,
              icon: const Icon(Icons.share_outlined),
              onPressed: () {
                final p = post.asData!.value;
                SharePlus.instance.share(
                  ShareParams(text: '${p.title}\n${p.url}'),
                );
              },
            ),
        ],
      ),
      body: post.when(
        loading: () => const _TextSkeleton(),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(blogPostProvider(slug)),
        ),
        data: (p) => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Text(p.title, style: context.text.headlineSmall),
            if (p.publishedAt != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  formatDate(p.publishedAt).split(' ').first,
                  style: context.text.bodySmall,
                ),
              ),
            const SizedBox(height: AppSpacing.md),
            SafeHtml(p.content ?? p.excerpt ?? ''),
          ],
        ),
      ),
    );
  }
}

/// Small-batch launch page (`launch/{code}`): interest / survey / limited
/// stock / pre-order sign-up.
class LaunchPage extends ConsumerStatefulWidget {
  const LaunchPage({super.key, required this.code});

  final String code;

  @override
  ConsumerState<LaunchPage> createState() => _LaunchPageState();
}

class _LaunchPageState extends ConsumerState<LaunchPage> {
  final _contact = TextEditingController();
  final _answer = TextEditingController();
  int _quantity = 1;
  bool _preorder = false;
  bool _consent = false;
  bool _busy = false;
  bool _done = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _contact.dispose();
    _answer.dispose();
    super.dispose();
  }

  Future<void> _submit(Launch l) async {
    final l10n = context.l10n;
    final errors = <String, String>{
      if (_contact.text.trim().isEmpty) 'contact': l10n.fieldRequired,
      if (!_consent) 'consent': l10n.launchConsentRequired,
      if (l.preorder && !_preorder)
        'preorder_accepted': l10n.launchTermsRequired,
    };
    if (errors.isNotEmpty) {
      setState(() => _errors = errors);
      return;
    }
    setState(() {
      _busy = true;
      _errors = const {};
    });
    try {
      await ref
          .read(contentRepositoryProvider)
          .joinLaunch(
            l.code,
            contact: _contact.text.trim(),
            answer: l.isSurvey ? _answer.text.trim() : null,
            quantity: l.asksQuantity ? _quantity : null,
            preorderAccepted: _preorder,
          );
      if (mounted) setState(() => _done = true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _errors = {
          for (final f in e.errors.entries)
            if (f.value.isNotEmpty) f.key: f.value.first,
        };
      });
      if (_errors.isEmpty) context.showSnack(errorMessage(l10n, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final launch = ref.watch(launchProvider(widget.code));
    return Scaffold(
      appBar: AppBar(
        title: Text(launch.asData?.value.name ?? l10n.launchTitle),
      ),
      body: launch.when(
        loading: () => const _TextSkeleton(),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(launchProvider(widget.code)),
        ),
        data: (l) => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            if (l.product?.image != null)
              NetworkImageX(
                url: l.product!.image,
                height: 200,
                radius: AppRadius.md,
                semanticLabel: l.product!.name,
              ),
            const SizedBox(height: AppSpacing.md),
            Text(l.name, style: context.text.headlineSmall),
            if (l.description != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(l.description!),
            ],
            if (l.product != null)
              TextButton.icon(
                onPressed: () =>
                    context.push(AppRoutes.product(l.product!.slug)),
                icon: const Icon(Icons.open_in_new_rounded),
                label: Text(l.product!.name),
              ),
            const SizedBox(height: AppSpacing.md),
            if (_done)
              Card(
                color: context.extra.tint,
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle_rounded,
                        color: context.extra.success,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(child: Text(l10n.launchThanks)),
                    ],
                  ),
                ),
              )
            else if (!l.isOpen)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Text(l10n.launchClosed),
                ),
              )
            else ...[
              AppTextField(
                label: l10n.launchContact,
                controller: _contact,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                prefixIcon: Icons.alternate_email_rounded,
                errorText: _errors['contact'],
              ),
              if (l.isSurvey) ...[
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  label: l.surveyQuestion!,
                  controller: _answer,
                  maxLines: 3,
                  errorText: _errors['answer'],
                ),
              ],
              if (l.asksQuantity) ...[
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    Expanded(child: Text(l10n.launchQuantity)),
                    QuantityStepper(
                      value: _quantity,
                      min: 1,
                      max: 20,
                      onChanged: (v) => setState(() => _quantity = v),
                    ),
                  ],
                ),
              ],
              if (l.preorder) ...[
                const SizedBox(height: AppSpacing.sm),
                if (l.preorderTerms != null) Text(l.preorderTerms!),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: Text(l10n.launchAcceptTerms),
                  subtitle: _errors['preorder_accepted'] == null
                      ? null
                      : Text(
                          _errors['preorder_accepted']!,
                          style: TextStyle(color: context.colors.error),
                        ),
                  value: _preorder,
                  onChanged: (v) => setState(() => _preorder = v ?? false),
                ),
              ],
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(l10n.launchConsent),
                subtitle: _errors['consent'] == null
                    ? null
                    : Text(
                        _errors['consent']!,
                        style: TextStyle(color: context.colors.error),
                      ),
                value: _consent,
                onChanged: (v) => setState(() => _consent = v ?? false),
              ),
              const SizedBox(height: AppSpacing.sm),
              AppButton(
                label: l10n.launchJoin,
                loading: _busy,
                onPressed: () => _submit(l),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

Future<void> _open(String url) =>
    launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);

/// Contact: WhatsApp, phone, e-mail and social links from `GET /store`.
class ContactPage extends ConsumerWidget {
  const ContactPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final store = ref.watch(storeProfileProvider);
    final whatsapp = store?.whatsapp;
    final phone = store?.phone;
    final email = store?.email;
    final social = store?.social ?? const <String, String>{};
    final empty =
        (whatsapp == null || whatsapp.isEmpty) &&
        (phone == null || phone.isEmpty) &&
        (email == null || email.isEmpty) &&
        social.isEmpty;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.contactTitle)),
      body: empty
          ? EmptyState(
              icon: Icons.support_agent_rounded,
              title: l10n.contactEmpty,
            )
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                if (whatsapp != null && whatsapp.isNotEmpty)
                  ListTile(
                    leading: const Icon(Icons.chat_outlined),
                    title: Text(l10n.contactWhatsapp),
                    subtitle: Text('+$whatsapp'),
                    onTap: () => _open('https://wa.me/$whatsapp'),
                  ),
                if (phone != null && phone.isNotEmpty)
                  ListTile(
                    leading: const Icon(Icons.phone_outlined),
                    title: Text(l10n.contactPhone),
                    subtitle: Text(phone, textDirection: TextDirection.ltr),
                    onTap: () => _open(
                      'tel:${phone.replaceAll(RegExp(r'[^0-9+]'), '')}',
                    ),
                  ),
                if (email != null && email.isNotEmpty)
                  ListTile(
                    leading: const Icon(Icons.mail_outline_rounded),
                    title: Text(l10n.email),
                    subtitle: Text(email, textDirection: TextDirection.ltr),
                    onTap: () => _open('mailto:$email'),
                  ),
                for (final e in social.entries)
                  if (e.value.isNotEmpty)
                    ListTile(
                      leading: Icon(_socialIcon(e.key)),
                      title: Text(_capitalize(e.key)),
                      onTap: () => _open(e.value),
                    ),
              ],
            ),
    );
  }
}

IconData _socialIcon(String key) => switch (key) {
  'youtube' => Icons.smart_display_outlined,
  'tiktok' || 'snapchat' => Icons.music_video_outlined,
  _ => Icons.public_rounded,
};

String _capitalize(String s) =>
    s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);
