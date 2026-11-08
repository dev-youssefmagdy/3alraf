<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\ThemeService;
use App\Support\Marketing\Contrast;
use Illuminate\Database\Eloquent\Model;

/**
 * Seasonal theme (MCP.md §18). Payload = whitelisted colour tokens (ThemeService::TOKENS, #rrggbb) + name / occasion key + advisory notes
 * (design direction, decorations, typography: shown, not applied). WCAG contrast of the key text/background pairs is computed and returned as
 * warnings (never silently fixed). Apply = new MarketingTheme version, activated (runtime CSS-variable override, compiled CSS untouched).
 * Rollback = the theme that was active before (or the compiled defaults). Stale = another theme was activated since the suggestion was made.
 */
class SeasonalThemeSuggestionType extends AbstractSuggestionType
{
    public function __construct(private ThemeService $themes) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::SEASONAL_THEME;
    }

    public function approvePermission(): ?string
    {
        return 'ai_theme.approve';
    }

    public function rules(?string $targetType = null): array
    {
        $r = $this->localeRules('name', 120, true) + [
            'payload' => ['required', 'array'],
            'payload.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'],
            'payload.tokens' => ['required', 'array', 'min:1'],
        ];
        foreach (ThemeService::TOKENS as $t) {
            $r['payload.tokens.'.$t] = ['nullable', 'string', 'max:9', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];
        }
        foreach (['design_direction', 'decorations', 'typography', 'header_footer', 'product_cards', 'slider'] as $k) {
            $r['payload.'.$k] = ['nullable', 'string', 'max:2000'];
        }

        return $r;
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $tokens = [];
        foreach (ThemeService::TOKENS as $t) {
            if ($hex = Contrast::normalizeHex($payload['tokens'][$t] ?? null)) {
                $tokens[$t] = $hex;
            }
        }
        ksort($tokens);
        $out = ['key' => is_string($payload['key'] ?? null) ? strtolower(trim($payload['key'])) : null, 'name' => $this->plainLocales($payload['name'] ?? null), 'tokens' => $tokens];
        foreach (['design_direction', 'decorations', 'typography', 'header_footer', 'product_cards', 'slider'] as $k) {
            $out[$k] = $this->plain($payload[$k] ?? null, 2000);
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        if (! ($payload['tokens'] ?? [])) {
            $errors['payload.tokens'] = __('marketing.suggestions.errors.theme_no_tokens');
        }
        foreach (['key', 'name'] as $f) {
            if (! ($payload[$f] ?? null)) {
                $errors['payload.'.$f] = __('validation.required', ['attribute' => $this->label($f)]);
            }
        }
        $warnings = [];
        foreach ($this->themes->contrast((array) ($payload['tokens'] ?? [])) as $c) {
            if (! $c['ok']) {
                $warnings[] = __('marketing.suggestions.warnings.contrast', ['pair' => __('marketing.suggestions.contrast_pairs.'.$c['pair']), 'ratio' => $c['ratio'], 'min' => $c['min']]);
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return ['active_theme_id' => $this->themes->active()?->id];
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $current = $this->themes->palette((array) ($this->themes->active()?->tokens ?? []));
        $rows = [];
        foreach ((array) ($s->payload['tokens'] ?? []) as $t => $hex) {
            $rows[] = $this->row($t, $current[$t] ?? null, $hex, '--'.$t) + ['color' => true];
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, null, $s));
        $notes = collect(['design_direction', 'decorations', 'typography'])->map(fn ($k) => $p[$k] ?? null)->filter()->implode("\n");
        $r = $this->themes->activateNew($p['key'], $p['name'], $p['tokens'], $notes ?: null, $s->id, $adminId);

        return new ApplyResult(['active_theme_id' => $r['before']], ['active_theme_id' => $r['after']], 'marketing_theme', $r['after'], $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $r = $this->themes->activateVersion(($exec->before['active_theme_id'] ?? null) ?: null, $adminId);

        return ['active_theme_id' => $r['after']];
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.key', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.tokens', 'colors', null, ['options' => ThemeService::TOKENS]),
            $this->field('payload.design_direction', 'textarea'),
            $this->field('payload.decorations', 'textarea'),
            $this->field('payload.typography', 'textarea'),
            $this->field('payload.header_footer', 'textarea'),
            $this->field('payload.product_cards', 'textarea'),
            $this->field('payload.slider', 'textarea'),
        ];
    }
}
