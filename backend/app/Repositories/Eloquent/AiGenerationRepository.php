<?php

namespace App\Repositories\Eloquent;

use App\Models\AiGeneration;
use App\Repositories\Contracts\AiGenerationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Tenant scoped through the BelongsToTenant global scope. */
class AiGenerationRepository implements AiGenerationRepositoryInterface
{
    public function create(array $data): AiGeneration
    {
        return AiGeneration::create($data);
    }

    public function find(int $id): ?AiGeneration
    {
        return AiGeneration::find($id);
    }

    public function history(array $types, int $perPage = 20): LengthAwarePaginator
    {
        return AiGeneration::whereIn('type', $types)->latest('id')->paginate($perPage);
    }

    public function delete(AiGeneration $g): void
    {
        $g->delete();
    }

    public function lock(int $id, array $types): ?AiGeneration
    {
        return AiGeneration::whereIn('type', $types)->lockForUpdate()->find($id);
    }

    public function available(int $productId, int $variantId): int
    {
        return (int) \App\Models\StockLevel::where('product_id', $productId)->where('product_variant_id', $variantId)->selectRaw('COALESCE(SUM(on_hand - reserved), 0) as av')->value('av');
    }

    public function expireDrafts(array $types, \DateTimeInterface $before): int
    {
        return AiGeneration::withoutGlobalScopes()->whereIn('type', $types)->where('status', \App\Enums\AiGenerationStatus::DRAFT->value)->where('created_at', '<', $before)
            ->update(['status' => \App\Enums\AiGenerationStatus::REJECTED->value, 'reject_reason' => 'expired', 'reviewed_at' => now()]);
    }
}
