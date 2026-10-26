<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Fuzzy SKU / barcode / name matcher between an external candidate and our listed products (shared by both trend pages). */
class ProductMatcher
{
    /** @param array{name:string,sku?:?string,barcode?:?string} $it */
    public function match(array $it, Collection $listed): ?object
    {
        $n = self::norm($it['name']);
        foreach ($listed as $p) {
            if (($it['sku'] ?? '') !== '' && strcasecmp($it['sku'], $p->sku) === 0) {
                return $p;
            }
            if (($it['barcode'] ?? '') !== '' && $it['barcode'] === $p->barcode) {
                return $p;
            }
            foreach ($p->names as $name) {
                $m = self::norm($name);
                if ($m === '' || $n === '') {
                    continue;
                }
                similar_text($n, $m, $pct);
                if ($pct >= 82 || (min(mb_strlen($n), mb_strlen($m)) >= 6 && (str_contains($n, $m) || str_contains($m, $n)))) {
                    return $p;
                }
            }
        }

        return null;
    }

    public static function norm(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', Str::lower($s))));
    }
}
