<?php

namespace App\Services;

use App\DTOs\ActivateProductsData;
use App\DTOs\CatalogItemData;
use App\Models\Product;
use App\Services\Qr\QrCodeManager;
use Illuminate\Support\Facades\DB;

/** Catalog page (selling side of products): price / sale price / cost / status (+ per-variant overrides), storefront wording, activation. */
class CatalogService
{
    public function __construct(private QrCodeManager $qr) {}

    /** Products that have no price yet (never activated): the "activate products" picker. */
    public function inactiveQuery()
    {
        return Product::query()->with('images')->whereNull('price');
    }

    /** Give products a price and put them on sale. Products that already have a price are skipped. @return int number activated */
    public function enableBulk(ActivateProductsData $dto): int
    {
        return DB::transaction(function () use ($dto) {
            $n = 0;
            foreach ($dto->rows as $row) {
                $p = Product::query()->find($row['product_id']);
                if (! $p || $p->price !== null) {
                    continue; // unknown / already activated
                }
                $p->update(['price' => $row['price'], 'sale_price' => $row['sale_price'], 'cost' => $row['cost'], 'is_active' => $row['is_active']]);
                $this->qr->listingSaved($p);
                $n++;
            }

            return $n;
        });
    }

    public function update(Product $p, CatalogItemData $dto): Product
    {
        return DB::transaction(function () use ($p, $dto) {
            $p->update([
                'price' => $dto->price, 'sale_price' => $dto->salePrice, 'cost' => $dto->cost, 'is_active' => $dto->isActive, 'min_stock_alert_qty' => $dto->minStockAlert,
            ]);
            foreach ($dto->variants as $row) {
                $p->variants()->whereKey($row['id'])->update([
                    'price' => $row['price'], 'sale_price' => $row['sale_price'], 'cost' => $row['cost'], 'min_stock_alert_qty' => $row['min_stock_alert_qty'], 'is_active' => $row['is_active'],
                ]);
            }
            $this->qr->listingSaved($p);

            return $p;
        });
    }

    /** Replace the storefront wording overlay (audited by Product, so the previous value is recoverable from the activity log). */
    public function updateContent(Product $p, ?array $content): Product
    {
        $p->update(['content' => $content ?: null]);

        return $p;
    }

    /** Take the product off sale (former "delete listing"): inactive, price cleared, QR codes removed. The catalog product stays. */
    public function delete(Product $p): void
    {
        DB::transaction(function () use ($p) {
            $this->qr->deleteFor($p);
            $p->update(['is_active' => false, 'price' => null, 'sale_price' => null]);
        });
    }
}
