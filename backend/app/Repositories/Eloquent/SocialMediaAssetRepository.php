<?php

namespace App\Repositories\Eloquent;

use App\Models\SocialMediaAsset;
use App\Repositories\Contracts\SocialMediaAssetRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class SocialMediaAssetRepository extends BaseRepository implements SocialMediaAssetRepositoryInterface
{
    protected string $model = SocialMediaAsset::class;

    public function lockForUpdate(int $id): SocialMediaAsset
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function filtered(array $f): Builder
    {
        $q = $this->query()->with('product:id,name,slug,sku');
        if (! empty($f['product_id'])) {
            $q->where('product_id', (int) $f['product_id']);
        }
        if (! empty($f['kind'])) {
            $q->where('kind', $f['kind']);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        } else {
            $q->where('status', '!=', 'archived');
        }
        if (! empty($f['platform'])) {
            $q->whereJsonContains('platforms', $f['platform']);
        }
        if (! empty($f['campaign'])) {
            $q->where('campaign', $f['campaign']);
        }
        if (! empty($f['tag'])) {
            $q->whereJsonContains('tags', mb_strtolower(trim((string) $f['tag'])));
        }
        if (! empty($f['search'])) {
            $s = '%'.addcslashes(trim((string) $f['search']), '%_\\').'%';
            $q->where(fn ($w) => $w->where('title', 'like', $s)->orWhere('campaign', 'like', $s)->orWhere('original_name', 'like', $s)->orWhere('tags', 'like', $s));
        }
        if (! empty($f['suggestion_id'])) {
            $q->where('suggestion_id', (int) $f['suggestion_id']);
        }

        return ($f['sort'] ?? 'newest') === 'oldest' ? $q->orderBy('id') : $q->orderByDesc('id');
    }

    public function campaigns(?int $productId = null): array
    {
        return $this->query()->when($productId, fn ($q) => $q->where('product_id', $productId))->whereNotNull('campaign')->where('campaign', '!=', '')
            ->distinct()->orderBy('campaign')->limit(200)->pluck('campaign')->all();
    }

    public function counts(?int $productId = null): array
    {
        return $this->query()->when($productId, fn ($q) => $q->where('product_id', $productId))->selectRaw('status, COUNT(*) c')->groupBy('status')
            ->pluck('c', 'status')->map(fn ($c) => (int) $c)->all();
    }
}
