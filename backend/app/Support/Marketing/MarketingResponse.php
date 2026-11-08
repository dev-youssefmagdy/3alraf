<?php

namespace App\Support\Marketing;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * The one JSON envelope of the Marketing internal API (/api/internal/marketing):
 * {success, data, meta:{generated_at, currency, timezone, page, per_page, total, ...}, warnings:[{code, message, ...}]}.
 * Errors: {success:false, error:<code>, message, data:null, meta, warnings:[]} (+ errors for 422).
 * Reuse it for every new marketing endpoint (P5 suggestions write endpoints included).
 */
final class MarketingResponse
{
    /** @param list<array{code:string,message:string}> $warnings */
    public static function ok(mixed $data, array $warnings = [], array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'meta' => self::meta($meta), 'warnings' => array_values($warnings)], $status);
    }

    /** Paginated list: data = items (mapped by $map), meta.page/per_page/total filled from the paginator. */
    public static function page(LengthAwarePaginator $p, callable $map, array $warnings = [], array $meta = []): JsonResponse
    {
        $items = array_values(array_map($map, $p->items()));

        return self::ok($items, $warnings, ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()] + $meta);
    }

    /** Already-materialised list paginated in memory (small, computed sets: rankings). */
    public static function slice(array $rows, int $page, int $perPage, array $warnings = [], array $meta = []): JsonResponse
    {
        $total = count($rows);

        return self::ok(array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)), $warnings,
            ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / max(1, $perPage)))] + $meta);
    }

    /** $data: optional machine-readable details (P5: e.g. {existing_id} on a 409 duplicate_suggestion). */
    public static function error(int $status, string $code, ?string $message = null, ?array $errors = null, ?array $data = null): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => $message ?? __('marketing.api.'.$code)]
            + ($errors !== null ? ['errors' => $errors] : []) + ['data' => $data ?: null, 'meta' => self::meta([]), 'warnings' => []], $status);
    }

    public static function meta(array $extra = []): array
    {
        return array_replace(['generated_at' => now()->toIso8601String(), 'currency' => (string) (setting('currency_code') ?: 'EGP'),
            'timezone' => (string) config('app.timezone'), 'page' => null, 'per_page' => null, 'total' => null], $extra);
    }

    /** Warning row. */
    public static function warning(string $code, string $message, array $extra = []): array
    {
        return ['code' => $code, 'message' => $message] + $extra;
    }
}
