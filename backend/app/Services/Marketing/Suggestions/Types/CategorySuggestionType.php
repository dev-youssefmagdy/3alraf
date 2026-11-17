<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\CategoryService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\EntityDtos;
use App\Services\Marketing\Suggestions\SuggestionImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Edit an existing category (target: category, required). Real columns only:
 * - name / description (per locale) → categories.name / categories.description through CategoryService::update (EntityDtos::category keeps every other value),
 * - image {url} → categories.image. The URL is downloaded ONCE at create / edit time (SuggestionImages, SSRF-safe, private disk, sha256 in the payload),
 *   so the approval covers the exact picture the reviewer saw; apply re-checks the hash and stores it through ImageUploadService.
 * The previous image FILE is kept on apply (CategoryService::replaceImage never deletes it) so rollback can restore it; rollback deletes the
 * image this suggestion added when it is still the current one.
 */
class CategorySuggestionType extends AbstractSuggestionType
{
    protected array $targetModels = ['category' => Category::class];

    public function __construct(private CategoryService $categories, private SuggestionImages $images) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::CATEGORY;
    }

    public function targetRequired(?array $payload = null): bool
    {
        return true;
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('name', 150) + $this->localeRules('description', 2000) + [
            'payload' => ['required', 'array'],
            'payload.image' => ['nullable', 'array'],
            'payload.image.url' => ['required_with:payload.image', 'string', 'max:2048', 'regex:#^https?://#i'],
            // filled by normalize() (staged download); accepted back unchanged when a reviewer edits the payload
            'payload.image.sha256' => ['nullable', 'string'], 'payload.image.mime' => ['nullable', 'string'], 'payload.image.width' => ['nullable', 'integer'],
            'payload.image.height' => ['nullable', 'integer'], 'payload.image.bytes' => ['nullable', 'integer'], 'payload.image.error' => ['nullable', 'string'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $out = array_filter(['name' => $this->plainLocales($payload['name'] ?? null), 'description' => $this->plainLocales($payload['description'] ?? null)], fn ($v) => $v !== []);
        $url = is_array($payload['image'] ?? null) ? trim((string) ($payload['image']['url'] ?? '')) : '';
        if ($url !== '') {
            $out['image'] = $this->images->stage($url);
        }
        ksort($out);

        return $out;
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        if ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        if (isset($payload['image'])) {
            $img = $payload['image'];
            if (isset($img['error'])) {
                $errors['payload.image.url'] = __('marketing.suggestions.errors.image_fetch', ['url' => Str::limit((string) $img['url'], 80), 'reason' => __('marketing.suggestions.image_errors.'.$img['error'])]);
            } elseif (! $this->images->exists($img)) {
                $errors['payload.image.url'] = __('marketing.suggestions.errors.image_missing');
            }
        }
        if (isset($payload['name']) && ! array_filter($payload['name'])) {
            $errors['payload.name'] = __('validation.required', ['attribute' => $this->label('name')]);
        }
        if ($target instanceof Category && ! $target->is_active) {
            $warnings[] = __('marketing.suggestions.warnings.category_inactive');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** Touched values only (unrelated edits of the category never make the suggestion stale). */
    public function snapshot(?Model $target, array $payload): ?array
    {
        if (! $target instanceof Category) {
            return null;
        }
        $snap = [];
        foreach (['name', 'description'] as $f) {
            foreach (array_keys((array) ($payload[$f] ?? [])) as $l) {
                $snap[$f][$l] = $target->getTranslation($f, $l, false) ?: null;
            }
        }
        if (isset($payload['image'])) {
            $snap['image'] = $target->image;
        }

        return $snap;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $p = (array) $s->payload;
        $cat = $target instanceof Category ? $target : null;
        $rows = [];
        foreach (['name', 'description'] as $f) {
            foreach ((array) ($p[$f] ?? []) as $l => $v) {
                $rows[] = $this->row($f.'.'.$l, $cat ? ($cat->getTranslation($f, $l, false) ?: null) : null, $v, $this->label($f).' ('.$l.')');
            }
        }
        if (isset($p['image'])) {
            $img = $p['image'];
            $current = $cat?->image_url ? [['src' => $cat->image_url, 'url' => $cat->image_url, 'size' => '']] : null;
            $proposed = [isset($img['sha256'])
                ? ['src' => route('marketer.suggestions.image', ['sha' => $img['sha256']]), 'url' => $img['url'], 'size' => ($img['width'] ?? '?').'×'.($img['height'] ?? '?')]
                : ['src' => null, 'url' => $img['url'] ?? '', 'size' => __('marketing.suggestions.image_errors.'.($img['error'] ?? 'missing'))]];
            $rows[] = $this->row('image', $current, $proposed) + ['images' => true];
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        /** @var Category $target */
        $p = (array) $s->payload;
        $this->ensure($this->check($p, $target, $s));
        $before = $this->state($target);

        if (isset($p['name']) || isset($p['description'])) {
            $o = [];
            foreach (['name', 'description'] as $f) {
                if (isset($p[$f])) {
                    $o[$f] = array_merge($this->translations($target, $f), $p[$f]);   // only the proposed locales change
                }
            }
            $target = $this->categories->update($target, EntityDtos::category($target, $o));
        }
        if (isset($p['image'])) {
            $file = $this->images->toUploadedFile($p['image'], 'category-'.$target->id);
            $this->categories->replaceImage($target, $file);
        }

        return new ApplyResult($before, $this->state($target->refresh()), 'category', $target->id);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $before = (array) $exec->before;
        $after = (array) $exec->after;
        $cat = Category::query()->findOrFail($s->target_entity_id);
        $p = (array) $s->payload;
        if (isset($p['name']) || isset($p['description'])) {
            $o = [];
            foreach (['name', 'description'] as $f) {
                if (isset($p[$f])) {
                    $o[$f] = (array) ($before[$f] ?? []);
                }
            }
            $cat = $this->categories->update($cat, EntityDtos::category($cat, $o));
        }
        if (isset($p['image'])) {
            $this->categories->restoreImage($cat, $before['image'] ?? null, $after['image'] ?? null);
        }

        return $this->state($cat->refresh());
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.description', 'locales-textarea'),
            $this->field('payload.image', 'json', null, ['hint' => 'category_image_hint']),
        ];
    }

    private function state(Category $c): array
    {
        return ['name' => $this->translations($c, 'name'), 'description' => $this->translations($c, 'description'), 'image' => $c->image];
    }
}
