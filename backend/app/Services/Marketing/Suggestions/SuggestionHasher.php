<?php

namespace App\Services\Marketing\Suggestions;

/** Canonical JSON (assoc keys sorted recursively, list order kept) + sha256 — content hash, snapshot hash, dedup key. */
final class SuggestionHasher
{
    public static function canonical(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map([self::class, 'canonical'], $v);
    }

    public static function json(mixed $v): string
    {
        return json_encode(self::canonical($v), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function hash(mixed $v): string
    {
        return hash('sha256', self::json($v));
    }

    /** What an approval binds to and what duplicates are compared on: type + target + normalised payload. */
    public static function content(string $type, ?string $targetType, ?int $targetId, array $payload): string
    {
        return self::hash(['type' => $type, 'target' => [$targetType, $targetId], 'payload' => $payload]);
    }
}
