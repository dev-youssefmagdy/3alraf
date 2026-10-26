<?php

namespace App\Services;

use App\DTOs\BulkEnableData;
use App\DTOs\TenantProductData;
use App\Models\TenantProduct;
use App\Repositories\Contracts\TenantProductRepositoryInterface;
use App\Services\Qr\QrCodeManager;
use Illuminate\Support\Facades\DB;

/** Tenant listing of central products: price / sale price / cost / status (+ per-variant overrides). */
class TenantCatalogService
{
    public function __construct(private TenantProductRepositoryInterface $listings, private QrCodeManager $qr) {}

    /** @return int number of products enabled */
    public function enableBulk(BulkEnableData $dto): int
    {
        return DB::transaction(function () use ($dto) {
            $n = 0;
            foreach ($dto->rows as $row) {
                if ($this->listings->findByProductId($row['product_id'])) {
                    continue; // already enabled
                }
                $tp = $this->listings->create([
                    'product_id' => $row['product_id'], 'price' => $row['price'], 'sale_price' => $row['sale_price'],
                    'cost' => $row['cost'], 'is_active' => $row['is_active'],
                ]);
                $this->syncVariantRows($tp);
                $this->qr->listingSaved($tp);
                $n++;
            }

            return $n;
        });
    }

    public function update(TenantProduct $tp, TenantProductData $dto): TenantProduct
    {
        return DB::transaction(function () use ($tp, $dto) {
            $tp = $this->listings->update($tp, [
                'price' => $dto->price, 'sale_price' => $dto->salePrice, 'cost' => $dto->cost, 'is_active' => $dto->isActive,
            ]);
            foreach ($dto->variants as $row) {
                $tp->variants()->whereKey($row['id'])->update([
                    'price' => $row['price'], 'sale_price' => $row['sale_price'], 'cost' => $row['cost'], 'is_active' => $row['is_active'],
                ]);
            }

            $this->qr->listingSaved($tp);

            return $tp;
        });
    }

    /** Replace the tenant's text overlay (audited by TenantProduct, so the previous value is recoverable from the activity log). */
    public function updateContent(TenantProduct $tp, ?array $content): TenantProduct
    {
        return $this->listings->update($tp, ['content' => $content ?: null]);
    }

    /** Disable the product for this tenant (removes the listing). */
    public function delete(TenantProduct $tp): void
    {
        $this->qr->deleteFor($tp);
        $this->listings->delete($tp);
    }

    /** Make sure a tenant_product_variants row exists for every live central variant. */
    public function syncVariantRows(TenantProduct $tp): void
    {
        $tp->ensureVariantRows();
    }
}
