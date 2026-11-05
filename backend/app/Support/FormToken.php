<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * One-time form tokens for ERP forms whose result is not itself keyed by an idempotency key (QA-013): the hidden `ref` of a form is claimed
 * atomically per tenant; a second submit of the same rendered form finds it taken. Posting forms key their journal by the ref instead.
 */
final class FormToken
{
    private static function key(string $scope, string $ref): string
    {
        return 'erp-form:'.(current_tenant()?->id ?? 0).':'.$scope.':'.sha1($ref);
    }

    public static function claim(string $scope, string $ref): bool
    {
        return Cache::add(self::key($scope, $ref), 1, now()->addDay());
    }

    public static function release(string $scope, ?string $ref): void
    {
        if ($ref) {
            Cache::forget(self::key($scope, $ref));
        }
    }
}
