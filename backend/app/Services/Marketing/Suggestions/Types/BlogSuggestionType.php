<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\BlogPostData;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\BlogPost;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\BlogPostService;
use App\Services\Marketing\SeoMetaService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\EntityDtos;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Marketing\InternalLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Blog articles (MCP.md §20). Apply = create the post as a DRAFT (is_active = false, no published_at) through BlogPostService — approving never
 * publishes. Then two separate, explicit actions on the applied suggestion: "publish now" (confirmation) and "schedule" (published_at in the
 * future: BlogPost::scopePublished shows it from then on). Duplicates refused by slug (blog_posts) and by identical title in any locale.
 * The post id is stored on the suggestion (result_entity). meta_title → seo_metas (blog posts have no meta_title column).
 * Content is sanitised (RichText allowlist; links only to this store). Blog categories do not exist: `category` is advisory text.
 */
class BlogSuggestionType extends AbstractSuggestionType
{
    public function __construct(private BlogPostService $posts, private SeoMetaService $metas) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::BLOG;
    }

    public function approvePermission(): ?string
    {
        return 'ai_blog.approve';
    }

    public function rules(?string $targetType = null): array
    {
        $r = $this->localeRules('title', 200, true) + $this->localeRules('excerpt', 500) + $this->localeRules('content', 100000) + $this->localeRules('meta_title', 120)
            + $this->localeRules('meta_description', 200) + [
                'payload' => ['required', 'array'],
                'payload.slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'payload.language' => ['nullable', Rule::in(['ar', 'en', 'both'])],
                'payload.keywords' => ['nullable', 'array', 'max:30'], 'payload.keywords.*' => ['string', 'max:80'],
                'payload.outline' => ['nullable', 'array', 'max:40'], 'payload.outline.*' => ['string', 'max:300'],
                'payload.internal_links' => ['nullable', 'array', 'max:20'],
                'payload.internal_links.*.url' => ['required', 'string', 'max:500', fn ($a, $v, $fail) => InternalLink::isInternal($v) ? null : $fail(__('marketing.suggestions.errors.internal_link'))],
                'payload.internal_links.*.anchor' => ['nullable', 'string', 'max:150'],
                'payload.suggested_publish_at' => ['nullable', 'date'],
            ];
        foreach (['category', 'topic', 'search_intent', 'target_audience', 'featured_image_concept', 'cta', 'occasion'] as $k) {
            $r['payload.'.$k] = ['nullable', 'string', 'max:1000'];
        }

        return $r;
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $links = [];
        foreach ((array) ($payload['internal_links'] ?? []) as $l) {
            if (is_array($l) && ($u = InternalLink::normalize(is_string($l['url'] ?? null) ? $l['url'] : null))) {
                $links[] = ['url' => $u, 'anchor' => $this->plain($l['anchor'] ?? null, 150)];
            }
        }
        $out = [
            'title' => $this->plainLocales($payload['title'] ?? null), 'slug' => is_string($payload['slug'] ?? null) ? strtolower(trim($payload['slug'])) : null,
            'excerpt' => $this->plainLocales($payload['excerpt'] ?? null), 'content' => $this->htmlLocales($payload['content'] ?? null),
            'meta_title' => $this->plainLocales($payload['meta_title'] ?? null), 'meta_description' => $this->plainLocales($payload['meta_description'] ?? null),
            'language' => in_array($payload['language'] ?? null, ['ar', 'en', 'both'], true) ? $payload['language'] : null,
            'keywords' => array_values(array_unique(array_filter(array_map(fn ($k) => $this->plain($k, 80), (array) ($payload['keywords'] ?? []))))),
            'outline' => array_values(array_filter(array_map(fn ($k) => $this->plain($k, 300), (array) ($payload['outline'] ?? [])))),
            'internal_links' => $links, 'suggested_publish_at' => $this->dt($payload['suggested_publish_at'] ?? null),
        ];
        foreach (['category', 'topic', 'search_intent', 'target_audience', 'featured_image_concept', 'cta', 'occasion'] as $k) {
            $out[$k] = $this->plain($payload[$k] ?? null, 1000);
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        if (! ($payload['title'] ?? [])) {
            $errors['payload.title'] = __('validation.required', ['attribute' => $this->label('title')]);
        }
        if (! ($payload['slug'] ?? null)) {
            $errors['payload.slug'] = __('validation.required', ['attribute' => $this->label('slug')]);
        } elseif (BlogPost::query()->where('slug', $payload['slug'])->when($s?->result_entity_id, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $errors['payload.slug'] = __('marketing.suggestions.errors.blog_exists');
        }
        foreach ((array) ($payload['title'] ?? []) as $l => $t) {
            $dup = BlogPost::query()->when($s?->result_entity_id, fn ($q, $id) => $q->where('id', '!=', $id))->get(['id', 'title'])
                ->first(fn ($p) => mb_strtolower(trim((string) $p->getTranslation('title', $l, false))) === mb_strtolower($t));
            if ($dup) {
                $errors['payload.title.'.$l] = __('marketing.suggestions.errors.blog_title_exists', ['id' => $dup->id]);
            }
        }
        if (! ($payload['content'] ?? [])) {
            $warnings[] = __('marketing.suggestions.warnings.blog_no_content');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $post = $s->result_entity_id ? BlogPost::query()->find($s->result_entity_id) : null;
        $rows = [];
        foreach (['title', 'excerpt', 'meta_title', 'meta_description'] as $f) {
            foreach ((array) ($s->payload[$f] ?? []) as $l => $v) {
                $rows[] = $this->row($f.'.'.$l, $post && $f !== 'meta_title' ? $post->getTranslation($f, $l, false) : null, $v, $this->label($f).' ('.$l.')');
            }
        }
        $rows[] = $this->row('slug', $post?->slug, $s->payload['slug'] ?? null);

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, null, $s));
        $post = $this->posts->create(new BlogPostData($p['slug'], $p['title'], $p['excerpt'] ?? [], $p['content'] ?? [], $p['meta_description'] ?? [], false, null));
        if ($p['meta_title'] ?? []) {
            $this->metas->put('blog_post', $post->id, $p['meta_title'], [], $s->id, $adminId);
        }

        return new ApplyResult(['created' => false], ['created' => true, 'blog_post_id' => $post->id, 'state' => 'draft', 'slug' => $post->slug], 'blog_post', $post->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    /** Only a post that is still a draft is removed; a published / scheduled post must be unpublished in Blog first. */
    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $post = BlogPost::query()->find($s->result_entity_id);
        if ($post && $post->is_active) {
            throw SuggestionException::make('blog_published', 409);
        }
        if ($post) {
            $this->metas->put('blog_post', $post->id, [], []);
            $this->posts->delete($post);
        }

        return ['deleted_blog_post_id' => $s->result_entity_id];
    }

    /** "Publish now" ($at = null) or "schedule" ($at in the future) of the draft created by apply(). @return array{before:array,after:array} */
    public function publish(MarketingSuggestion $s, ?\DateTimeInterface $at): array
    {
        $post = BlogPost::query()->find($s->result_entity_id) ?? throw SuggestionException::make('target_not_found', 409);
        $before = ['is_active' => (bool) $post->is_active, 'published_at' => $post->published_at?->format('Y-m-d H:i:s')];
        $when = $at ? \Carbon\CarbonImmutable::instance($at)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $this->posts->update($post, EntityDtos::blogPost($post, ['is_active' => true, 'published_at' => $when]));
        $post->refresh();

        return ['before' => $before, 'after' => ['is_active' => (bool) $post->is_active, 'published_at' => $post->published_at?->format('Y-m-d H:i:s'),
            'state' => $post->published_at && $post->published_at->isFuture() ? 'scheduled' : 'published']];
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.title', 'locales-text'),
            $this->field('payload.slug', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.excerpt', 'locales-textarea'),
            $this->field('payload.content', 'locales-html'),
            $this->field('payload.meta_title', 'locales-text'),
            $this->field('payload.meta_description', 'locales-textarea'),
            $this->field('payload.language', 'select', null, ['options' => ['ar', 'en', 'both'], 'nullable' => true]),
            $this->field('payload.category', 'text'),
            $this->field('payload.topic', 'text'),
            $this->field('payload.search_intent', 'text'),
            $this->field('payload.target_audience', 'text'),
            $this->field('payload.keywords', 'list'),
            $this->field('payload.outline', 'list'),
            $this->field('payload.internal_links', 'json', null, ['hint' => 'links_hint']),
            $this->field('payload.featured_image_concept', 'textarea'),
            $this->field('payload.cta', 'text'),
            $this->field('payload.occasion', 'text'),
            $this->field('payload.suggested_publish_at', 'datetime'),
        ];
    }
}
