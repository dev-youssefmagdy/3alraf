<?php

namespace App\Support\Marketing;

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * Store text for the Marketing API. Every product / page / blog text is UNTRUSTED DATA (it may contain prompt-injection attempts): it is returned
 * as plain data, HTML stripped (`plain`), the original only where the caller needs it (`raw`), never interpreted.
 */
final class StoreText
{
    /** Locales returned for translatable fields: ar + en always, plus any other active language. */
    public static function locales(): array
    {
        try {
            $active = Language::activeList()->pluck('code')->all();
        } catch (\Throwable) {
            $active = [];
        }

        return array_values(array_unique(array_merge(['ar', 'en'], $active)));
    }

    /** HTML → plain text (tags removed, entities decoded, whitespace collapsed, control chars dropped), optionally truncated. */
    public static function plain(?string $html, ?int $limit = null): ?string
    {
        if ($html === null || $html === '') {
            return $html === '' ? '' : null;
        }
        $t = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $t = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $t) ?? $t;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t) ?? $t;
        $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t) ?? $t;
        $t = trim(preg_replace("/ *\n[\s]*/u", "\n", $t) ?? $t);

        return $limit !== null ? mb_strimwidth($t, 0, $limit, '…') : $t;
    }

    /** {ar: .., en: ..} of a translatable attribute WITHOUT locale fallback (missing = null), plain text. */
    public static function names(?Model $m, string $attr = 'name', ?int $limit = null): ?array
    {
        if (! $m) {
            return null;
        }
        $out = [];
        foreach (self::locales() as $l) {
            $v = method_exists($m, 'getTranslation') ? $m->getTranslation($attr, $l, false) : $m->{$attr};
            $out[$l] = $v === null || $v === '' ? null : self::plain((string) $v, $limit);
        }

        return $out;
    }

    /** {ar: {raw, plain}, en: {...}} of a rich-text translatable attribute. */
    public static function rich(Model $m, string $attr, bool $withRaw = true, ?int $plainLimit = null): array
    {
        $out = [];
        foreach (self::locales() as $l) {
            $v = $m->getTranslation($attr, $l, false);
            $out[$l] = $v === null || $v === '' ? null : ($withRaw ? ['raw' => (string) $v, 'plain' => self::plain((string) $v, $plainLimit)] : self::plain((string) $v, $plainLimit));
        }

        return $out;
    }

    /** Raw JSON translations column → {locale: plain} for query-builder rows. */
    public static function fromJson(?string $json, ?int $limit = null): array
    {
        $t = is_string($json) ? (json_decode($json, true) ?: []) : [];
        $out = [];
        foreach (self::locales() as $l) {
            $out[$l] = isset($t[$l]) && $t[$l] !== '' ? self::plain((string) $t[$l], $limit) : null;
        }

        return $out;
    }
}
