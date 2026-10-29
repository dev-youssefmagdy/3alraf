<?php

namespace App\Services\Growth\GiftFinder;

use App\Models\Category;
use App\Models\TenantProduct;

/** Rule-based first pass of the Gift Finder tags. It never overwrites a product a human already tagged; the owner reviews and edits in the admin. */
class GiftTagSuggester
{
    /** @return array{gift_suitable:bool,gift_recipients:array,gift_occasions:array} */
    public function suggest(TenantProduct $tp): array
    {
        $cfg = config('gift_finder.suggest');
        $p = $tp->product;
        $name = implode(' ', $p->getTranslations('name'));
        if (collect($p->getTranslations('name'))->contains(fn ($n) => preg_match($cfg['skip_name_pattern'], trim((string) $n)))) {   // each language on its own: the pattern is anchored
            return ['gift_suitable' => false, 'gift_recipients' => [], 'gift_occasions' => []];
        }
        $rule = $cfg['defaults'];
        foreach ($this->chain($p) as $en) {            // nearest category first
            if (isset($cfg['categories'][$en])) {
                $rule = $cfg['categories'][$en] + $cfg['defaults'];
                break;
            }
        }
        $recipients = $rule['recipients'];
        foreach ($cfg['name_rules'] as $nr) {
            if (preg_match($nr['pattern'], $name)) {
                $recipients = $recipients ? array_values(array_intersect($nr['recipients'], $recipients)) ?: $nr['recipients'] : $nr['recipients'];
                break;
            }
        }

        return ['gift_suitable' => (bool) $rule['suitable'], 'gift_recipients' => array_values($recipients), 'gift_occasions' => array_values($rule['occasions'])];
    }

    /** Tags every product that has never been tagged (not suitable, no recipients, no occasions). Returns how many were tagged. */
    public function applyToUntagged(): int
    {
        $n = 0;
        TenantProduct::with('product.categories')->where('gift_suitable', false)->get()->each(function (TenantProduct $tp) use (&$n) {
            if ($tp->gift_recipients || $tp->gift_occasions || ! $tp->product) {
                return;
            }
            $tags = $this->suggest($tp);
            if ($tags['gift_suitable']) {
                $tp->update($tags);
                $n++;
            }
        });

        return $n;
    }

    /** English names of the product's categories, deepest first, then their ancestors. */
    private function chain($product): array
    {
        $out = [];
        foreach ($product->categories->sortByDesc('depth') as $c) {
            for ($x = $c; $x; $x = $x->parent_id ? Category::find($x->parent_id) : null) {
                $out[] = $x->getTranslation('name', 'en');
            }
        }

        return $out;
    }
}
