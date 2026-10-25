<?php

namespace App\Services\Storefront;

use App\Models\Bundle;
use App\Services\Orders\BundleResolver;
use App\Support\Money;

/** Customer-facing bundle data shared by the storefront pages and the mobile API (all prices from PriceResolver / bundle.price). */
class BundleCatalog
{
    public function __construct(private BundleResolver $resolver) {}

    /** Live bundles whose components are all purchasable (sold-out ones included, flagged). @return array<int,array> */
    public function listing(?int $limit = null): array
    {
        $q = Bundle::query()->with('items')->live()->orderBy('sort_order')->orderByDesc('id');
        if ($limit) {
            $q->limit($limit);
        }

        return $q->get()->map(fn (Bundle $b) => $this->present($b))->filter()->values()->all();
    }

    public function find(string $slug): ?array
    {
        $b = Bundle::query()->with('items')->live()->where('slug', $slug)->first();

        return $b ? $this->present($b) : null;
    }

    public function findById(int $id): ?array
    {
        $b = Bundle::query()->with('items')->live()->find($id);

        return $b ? $this->present($b) : null;
    }

    /** @return array|null null when a component is not purchasable (the bundle is hidden) */
    public function present(Bundle $b): ?array
    {
        $components = $this->resolver->components($b);
        if (! $components) {
            return null;
        }
        $regular = $this->resolver->regularTotal($components);
        $save = Money::sub($regular, (string) $b->price);
        $avail = $this->resolver->available($b, $components);

        return [
            'id' => $b->id, 'slug' => $b->slug, 'name' => (string) $b->name, 'description' => (string) $b->description, 'image' => $b->imageUrl(),
            'price' => Money::round((string) $b->price), 'regular_total' => $regular, 'savings' => bccomp($save, '0', 2) > 0 ? $save : '0.00',
            'available' => $avail, 'in_stock' => $avail === null || $avail > 0,
            'starts_at' => $b->starts_at?->toIso8601String(), 'ends_at' => $b->ends_at?->toIso8601String(),
            'components' => array_map(fn ($c) => ['product_id' => $c['item']['product']->id, 'variant_id' => $c['item']['variant']?->id ?? 0, 'name' => $c['item']['name'],
                'slug' => $c['item']['product']->slug, 'quantity' => $c['per'], 'unit_price' => $c['item']['price'],
                'image' => $c['item']['variant']?->images->first()?->thumb_url ?? $c['item']['product']->primaryImageUrl()], $components),
        ];
    }
}
