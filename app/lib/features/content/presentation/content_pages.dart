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
  Widget build(BuildContext context) {
    final x = context.extra;
    String css(Color c) =>
        '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0')}';
    return HtmlWidget(
      html,
      textStyle: context.text.bodyLarge?.copyWith(
        color: context.colors.onSurfaceVariant,
      ),
      customStylesBuilder: (e) => switch (e.localName) {
        'h1' || 'h2' || 'h3' => {
          'color': css(context.colors.onSurface),
          'font-weight': '700',
          'margin': '16px 0 8px 0',
        },
        'blockquote' => {
          'background-color': css(x.sunSoft),
          'border-right': '4px solid ${css(x.sun)}',
          'border-left': '4px solid ${css(x.sun)}',
          'padding': '12px 16px',
          'margin': '12px 0',
          'color': css(context.colors.onSurface),
        },
        'a' => {'color': css(context.colors.primary), 'font-weight': '600'},
        _ => null,
      },
      onTapUrl: (url) async {
        await openLink(context, url);
        return true;
      },
    );
  }
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
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: [
            SectionCard(
              padding: const EdgeInsets.all(AppSpacing.mdl),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const ToneBlob(
                    icon: Icons.inventory_2_outlined,
                    tone: StateTone.sun,
                    size: 48,
                    iconSize: 24,
                  ),
                  const SizedBox(height: AppSpacing.smd),
                  Semantics(
                    header: true,
                    child: Text(p.title, style: context.text.headlineMedium),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  SafeHtml(p.content),
                ],
              ),
            ),
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
      const AppShimmer(height: 28, width: 220, radius: AppRadius.xs),
      const SizedBox(height: AppSpacing.md),
      for (var i = 0; i < 6; i++) ...[
        const AppShimmer(height: 16, radius: AppRadius.xs),
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
          final header = ColorHeaderBlock(
            title: l10n.blogTitle,
            subtitle: l10n.blogSubtitle,
            tone: HeaderTone.mint,
            margin: EdgeInsets.zero,
          );
          if (s.posts.isEmpty && s.loading) {
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.gutter),
              children: [
                header,
                const SizedBox(height: AppSpacing.md),
                for (var i = 0; i < 3; i++) ...[
                  const AppShimmer(height: 220, radius: AppRadius.md),
                  const SizedBox(height: AppSpacing.smd),
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
              padding: const EdgeInsets.all(AppSpacing.gutter),
              itemCount: s.posts.length + 1 + (s.hasMore ? 1 : 0),
              itemBuilder: (context, i) {
                if (i == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: header,
                  );
                }
                final index = i - 1;
                if (index >= s.posts.length) {
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
                final post = s.posts[index];
                return Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.smd),
                  child: BlogPostCard(
                    key: ValueKey('post:${post.slug}'),
                    post: post,
                    index: index,
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

/// Blog post card (`62:4466`): tinted cover band, date, H3 title, two-line
/// excerpt and a brand "read more" link. The whole card is one button.
class BlogPostCard extends StatelessWidget {
  const BlogPostCard({
    super.key,
    required this.post,
    required this.index,
    required this.onTap,
  });

  final BlogPost post;
  final int index;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final date = formatDate(post.publishedAt).split(' ').first;
    return Material(
      color: c.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        side: BorderSide(color: c.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _CoverBand(index: index, height: 120),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (date.isNotEmpty)
                    Text(
                      date,
                      style: context.text.bodySmall?.copyWith(
                        color: context.extra.textMuted,
                      ),
                    ),
                  const SizedBox(height: 2),
                  Text(post.title, style: context.text.titleLarge),
                  if (post.excerpt != null && post.excerpt!.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      post.excerpt!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: context.text.bodyMedium?.copyWith(
                        color: c.onSurfaceVariant,
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.sm),
                  ExcludeSemantics(
                    child: Row(
                      children: [
                        Text(
                          context.l10n.blogReadMore,
                          style: context.text.labelMedium?.copyWith(
                            color: c.primary,
                          ),
                        ),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 18,
                          color: c.primary,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Decorative cover band for posts without a photo: a soft tint (rotated
/// by [index]) with blobs and an icon.
class _CoverBand extends StatelessWidget {
  const _CoverBand({required this.index, required this.height});

  final int index;
  final double height;

  @override
  Widget build(BuildContext context) {
    final x = context.extra;
    final tint = x.cardTint(index);
    final ring = x.ring(index + 1);
    return ExcludeSemantics(
      child: Container(
        height: height,
        clipBehavior: Clip.hardEdge,
        decoration: BoxDecoration(color: tint),
        child: Stack(
          alignment: Alignment.center,
          children: [
            PositionedDirectional(
              top: -40,
              end: -30,
              child: DecorCircle(
                size: 130,
                color: ring.withValues(alpha: 0.35),
              ),
            ),
            PositionedDirectional(
              bottom: -30,
              start: 30,
              child: DecorCircle(size: 70, color: x.sun.withValues(alpha: 0.6)),
            ),
            Icon(Icons.auto_stories_outlined, size: 44, color: ring),
          ],
        ),
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
    final url = post.asData?.value.url;
    void share() {
      final p = post.asData!.value;
      SharePlus.instance.share(ShareParams(text: '${p.title}\n${p.url}'));
    }

    final back = AppButton(
      label: l10n.blogBack,
      variant: AppButtonVariant.outlined,
      onPressed: () =>
          context.canPop() ? context.pop() : context.go(AppRoutes.blog),
    );
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.blogTitle),
        actions: [
          if (url != null)
            IconButton(
              tooltip: l10n.share,
              icon: const Icon(Icons.share_outlined),
              onPressed: share,
            ),
        ],
      ),
      bottomNavigationBar: post.hasValue
          ? StickyActionBar(
              leading: url == null ? null : Expanded(child: back),
              action: url == null
                  ? back
                  : AppButton(
                      label: l10n.share,
                      icon: Icons.share_outlined,
                      variant: AppButtonVariant.pop,
                      onPressed: share,
                    ),
            )
          : null,
      body: post.when(
        loading: () => const _TextSkeleton(),
        error: (e, _) => ErrorState(
          error: e,
          onRetry: () => ref.invalidate(blogPostProvider(slug)),
        ),
        data: (p) => ListView(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          children: [
            _CoverBand(index: slug.length, height: 160),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutter,
                AppSpacing.mdl,
                AppSpacing.gutter,
                0,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(
                    header: true,
                    child: Text(p.title, style: context.text.headlineMedium),
                  ),
                  if (p.publishedAt != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        formatDate(p.publishedAt).split(' ').first,
                        style: context.text.bodySmall?.copyWith(
                          color: context.extra.textMuted,
                        ),
                      ),
                    ),
                  const SizedBox(height: AppSpacing.md),
                  SafeHtml(p.content ?? p.excerpt ?? ''),
                ],
              ),
            ),
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
          padding: const EdgeInsets.all(AppSpacing.gutter),
          children: [
            Container(
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(
                color: context.extra.violet,
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: Stack(
                children: [
                  PositionedDirectional(
                    top: -50,
                    end: -40,
                    child: DecorCircle(
                      size: 150,
                      color: Colors.white.withValues(alpha: 0.12),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(AppSpacing.mdl),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (l.product?.image != null) ...[
                          NetworkImageX(
                            url: l.product!.image,
                            width: double.infinity,
                            height: 180,
                            radius: AppRadius.sm,
                            semanticLabel: l.product!.name,
                          ),
                          const SizedBox(height: AppSpacing.md),
                        ] else ...[
                          const ToneBlob(
                            icon: Icons.rocket_launch_outlined,
                            tone: StateTone.sun,
                            size: 56,
                            iconSize: 28,
                          ),
                          const SizedBox(height: AppSpacing.smd),
                        ],
                        Semantics(
                          header: true,
                          child: Text(
                            l.name,
                            style: context.text.headlineMedium?.copyWith(
                              color: Colors.white,
                            ),
                          ),
                        ),
                        if (l.description != null) ...[
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            l.description!,
                            style: context.text.bodyMedium?.copyWith(
                              color: Colors.white.withValues(alpha: 0.9),
                            ),
                          ),
                        ],
                        if (l.product != null) ...[
                          const SizedBox(height: AppSpacing.smd),
                          HeaderChip(
                            label: l.product!.name,
                            icon: Icons.open_in_new_rounded,
                            onTap: () => context.push(
                              AppRoutes.product(l.product!.slug),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            if (_done)
              NoticeCard(
                icon: Icons.check_circle_rounded,
                tone: StateTone.mint,
                text: l10n.launchThanks,
              )
            else if (!l.isOpen)
              NoticeCard(
                icon: Icons.lock_clock_outlined,
                tone: StateTone.sun,
                text: l10n.launchClosed,
              )
            else
              SectionCard(
                padding: const EdgeInsets.all(AppSpacing.mdl),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    AppTextField(
                      label: l10n.launchContact,
                      controller: _contact,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      ltr: true,
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
                          Expanded(
                            child: Text(
                              l10n.launchQuantity,
                              style: context.text.titleSmall,
                            ),
                          ),
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
                      if (l.preorderTerms != null)
                        Text(
                          l.preorderTerms!,
                          style: context.text.bodySmall?.copyWith(
                            color: context.colors.onSurfaceVariant,
                          ),
                        ),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                        title: Text(
                          l10n.launchAcceptTerms,
                          style: context.text.bodyMedium,
                        ),
                        subtitle: _errors['preorder_accepted'] == null
                            ? null
                            : Text(
                                _errors['preorder_accepted']!,
                                style: TextStyle(color: context.colors.error),
                              ),
                        value: _preorder,
                        onChanged: (v) =>
                            setState(() => _preorder = v ?? false),
                      ),
                    ],
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: Text(
                        l10n.launchConsent,
                        style: context.text.bodyMedium,
                      ),
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
                      size: AppButtonSize.l,
                      loading: _busy,
                      onPressed: () => _submit(l),
                    ),
                  ],
                ),
              ),
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
              padding: const EdgeInsets.all(AppSpacing.gutter),
              children: [
                Container(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  decoration: BoxDecoration(
                    color: context.extra.violetSoft,
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                  ),
                  child: Column(
                    children: [
                      ExcludeSemantics(
                        child: Container(
                          width: 64,
                          height: 64,
                          decoration: BoxDecoration(
                            color: context.extra.violet,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.call_outlined,
                            color: Colors.white,
                            size: 30,
                          ),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.smd),
                      Semantics(
                        header: true,
                        child: Text(
                          l10n.contactHeroTitle,
                          textAlign: TextAlign.center,
                          style: context.text.headlineSmall,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        l10n.contactHeroBody,
                        textAlign: TextAlign.center,
                        style: context.text.bodyMedium?.copyWith(
                          color: context.colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                if (whatsapp != null && whatsapp.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  _MintPillButton(
                    label: l10n.contactWhatsapp,
                    onTap: () => _open('https://wa.me/$whatsapp'),
                  ),
                ],
                const SizedBox(height: AppSpacing.md),
                AppListGroup(
                  children: [
                    if (phone != null && phone.isNotEmpty)
                      AppListTile(
                        icon: Icons.call_outlined,
                        title: l10n.contactPhone,
                        value: '\u2066$phone\u2069',
                        onTap: () => _open(
                          'tel:${phone.replaceAll(RegExp(r'[^0-9+]'), '')}',
                        ),
                      ),
                    if (email != null && email.isNotEmpty)
                      AppListTile(
                        icon: Icons.mail_outline_rounded,
                        title: l10n.email,
                        value: '\u2066$email\u2069',
                        onTap: () => _open('mailto:$email'),
                      ),
                    if (whatsapp != null && whatsapp.isNotEmpty)
                      AppListTile(
                        icon: Icons.chat_outlined,
                        title: l10n.contactWhatsapp,
                        value: '\u2066+$whatsapp\u2069',
                        onTap: () => _open('https://wa.me/$whatsapp'),
                      ),
                    for (final e in social.entries)
                      if (e.value.isNotEmpty)
                        AppListTile(
                          icon: _socialIcon(e.key),
                          title: _capitalize(e.key),
                          onTap: () => _open(e.value),
                        ),
                  ],
                ),
              ],
            ),
    );
  }
}

/// Full-width mint pill (WhatsApp CTA): mint-400 with ink text.
class _MintPillButton extends StatelessWidget {
  const _MintPillButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: Material(
      color: context.extra.mint,
      shape: const StadiumBorder(),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 52),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.chat_rounded,
                size: 20,
                color: AppColors.slate950,
              ),
              const SizedBox(width: AppSpacing.sm),
              Flexible(
                child: Text(
                  label,
                  style: context.text.labelLarge?.copyWith(
                    color: AppColors.slate950,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

IconData _socialIcon(String key) => switch (key) {
  'youtube' => Icons.smart_display_outlined,
  'tiktok' || 'snapchat' => Icons.music_video_outlined,
  _ => Icons.public_rounded,
};

String _capitalize(String s) =>
    s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);
