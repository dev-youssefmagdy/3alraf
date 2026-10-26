<?php

namespace App\Services\Retention;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Repositories\Contracts\RetentionRepositoryInterface;
use App\Services\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Housekeeping for soft-deleted rows older than `retention.soft_delete_days`.
 * HARD RULE: purging must never change a historical report number, so a row that any retained business record
 * (order, order item, stock movement, purchase, return, commission, ...) still points at is KEPT (or, for personal data, anonymised).
 *
 * Per model (see also docs/PROGRESS.md):
 *  order           purge (items/history/usages cascade + private payment proof) unless it has returns or an affiliate commission
 *                  (the returns / commission reports count them) -> then kept and its customer snapshot is anonymised
 *  customer        has orders or reviews -> anonymised (name/e-mail/phone/password, addresses/carts/wishlist/tokens removed), else purged
 *  variant         referenced by order items / stock / purchase / adjustment / transfer rows -> kept, else purged (+images, QR codes, listing rows)
 *  product         same references (any of its variants too) -> kept, else purged (+images, variants, QR codes, tenant listings)
 *  packaging_option referenced by an order (packaging_option_id) -> kept, else purged (+image, cart refs cleared)
 *  bundle          referenced by an order item (bundle_id) -> kept, else purged (+image, components, cart lines)
 *  brand           still has products (even soft-deleted) -> kept, else purged (+logo)
 *  category        still has products or sub-categories -> kept, else purged (+image)
 *  tag             always purged (pivot cascades; not used by any report)
 *  discount code   used by an order or has usage rows -> kept, else purged
 *  payment method  used by an order / payment event -> kept, else purged
 *  shipping co.    used by an order -> kept, else purged (rates cascade)
 *  supplier        has purchase orders -> kept, else purged
 *  branch          has orders / purchases / adjustments / transfers / returns / expenses / stock movements -> kept, else purged (stock levels cascade)
 *  city            used by an order / address / branch -> kept, else purged (shipping rates cascade)
 *  tenant          NEVER automatic (explicit manual operation only)
 */
class SoftDeletePurger
{
    public const PURGED = 'purged';
    public const ANONYMIZED = 'anonymized';
    public const RETAINED = 'retained';

    private bool $dry = false;

    /** Files to delete after the row transaction committed. @var array<int,string> */
    private array $mediaFiles = [];

    /** @var array<int,string> */
    private array $privateFiles = [];

    public function __construct(private RetentionRepositoryInterface $repo) {}

    /**
     * @param  array<int,string>|null  $only  model keys (config retention.models); null = all
     * @return array<string,array{purged:int,anonymized:int,retained:int}>
     */
    public function run(?int $days = null, bool $dryRun = false, ?array $only = null): array
    {
        $this->dry = $dryRun;
        $cutoff = now()->subDays($days ?? (int) config('retention.soft_delete_days'));
        $summary = [];
        foreach (config('retention.models') as $key => $class) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }
            $summary[$key] = [self::PURGED => 0, self::ANONYMIZED => 0, self::RETAINED => 0];
            $this->repo->eachExpired($class, $cutoff, (int) config('retention.chunk', 100), function (Model $row) use ($key, &$summary) {
                $outcome = $this->handle($key, $row);
                $summary[$key][$outcome]++;
            });
        }

        return $summary;
    }

    private function handle(string $key, Model $row): string
    {
        $this->mediaFiles = [];
        $this->privateFiles = [];
        $outcome = $this->dry
            ? $this->decide($key, $row)
            : DB::transaction(fn () => $this->{'purge'.Str::studly($key)}($row));
        if (! $this->dry) {
            if ($this->mediaFiles) {
                ImageUploadService::disk()->delete(array_values(array_unique($this->mediaFiles)));
            }
            if ($this->privateFiles) {
                Storage::disk(config('media.private_disk'))->delete(array_values(array_unique($this->privateFiles)));
            }
        }

        return $outcome;
    }

    /** Dry run: run the real rule inside a rolled-back transaction, so the outcome is exactly what a real run would do. */
    private function decide(string $key, Model $row): string
    {
        DB::beginTransaction();
        try {
            return $this->{'purge'.Str::studly($key)}($row);
        } finally {
            DB::rollBack();
        }
    }

    // ------------------------------------------------------------------ rules

    private function purgeOrder(Order $o): string
    {
        if ($this->repo->referencedAny([['order_returns', 'order_id'], ['affiliate_commissions', 'order_id']], $o->id)) {
            if ($o->customer_name === 'Anonymized') {
                return self::RETAINED;
            }
            if ($o->payment_proof) {
                $this->privateFiles[] = $o->payment_proof;
            }
            DB::table('orders')->where('id', $o->id)->update([
                'customer_name' => 'Anonymized', 'customer_phone' => '', 'customer_email' => null,
                'address' => '-', 'notes' => null, 'payment_proof' => null,
            ]);

            return self::ANONYMIZED;
        }
        if ($o->payment_proof) {
            $this->privateFiles[] = $o->payment_proof;
        }
        $o->forceDelete();

        return self::PURGED;
    }

    private function purgeCustomer(Customer $c): string
    {
        if (str_ends_with((string) $c->getRawOriginal('email'), '@anonymized.invalid')) {
            return self::RETAINED;
        }
        $kept = $this->repo->referencedAny([['orders', 'customer_id'], ['reviews', 'customer_id']], $c->id);
        $rawEmail = (string) $c->getRawOriginal('email'); // may carry the #del tombstone
        $this->repo->deleteWhere('customer_auth_codes', ['tenant_id' => $c->tenant_id, 'email' => preg_replace('/#del\d+$/', '', $rawEmail)]);
        DB::table('personal_access_tokens')->where('tokenable_type', $c->getMorphClass())->where('tokenable_id', $c->id)->delete();
        DB::table('model_has_roles')->where('model_type', $c->getMorphClass())->where('model_id', $c->id)->delete();
        DB::table('model_has_permissions')->where('model_type', $c->getMorphClass())->where('model_id', $c->id)->delete();

        if (! $kept) {
            $c->forceDelete(); // addresses, carts, wishlist cascade

            return self::PURGED;
        }
        foreach ($this->repo->ids('carts', ['customer_id' => $c->id]) as $cartId) {
            $this->repo->deleteWhere('cart_items', ['cart_id' => $cartId]);
        }
        foreach (['carts', 'customer_addresses', 'wishlist_items'] as $t) {
            $this->repo->deleteWhere($t, ['customer_id' => $c->id]);
        }
        DB::table('customers')->where('id', $c->id)->update([
            'name' => 'Anonymized customer', 'email' => 'c'.$c->id.'@anonymized.invalid', 'phone' => null,
            'password' => Str::random(60), 'remember_token' => null, 'google_id' => null, 'avatar' => null, 'is_active' => false,
        ]);

        return self::ANONYMIZED;
    }

    private function purgeVariant(ProductVariant $v): string
    {
        if ($this->variantReferenced($v->id)) {
            return self::RETAINED;
        }
        $this->dropVariant($v);

        return self::PURGED;
    }

    private function purgeProduct(Product $p): string
    {
        $variantIds = ProductVariant::withoutGlobalScopes()->where('product_id', $p->id)->pluck('id')->all();
        $refs = [['order_items', 'product_id'], ['stock_movements', 'product_id'], ['purchase_order_items', 'product_id'], ['stock_adjustment_items', 'product_id'], ['stock_transfer_items', 'product_id']];
        if ($this->repo->referencedAny($refs, $p->id)) {
            return self::RETAINED;
        }
        foreach ($variantIds as $vid) {
            if ($this->variantReferenced($vid)) {
                return self::RETAINED;
            }
        }
        foreach (ProductVariant::withoutGlobalScopes()->where('product_id', $p->id)->get() as $v) {
            $this->dropVariant($v);
        }
        $this->mediaFiles = array_merge($this->mediaFiles, $this->repo->detachImages($p));
        $this->dropListings('product_id', $p->id);
        $p->forceDelete(); // categories/tags/reviews/flash items/lists/stock levels cascade

        return self::PURGED;
    }

    private function purgePackagingOption(Model $o): string
    {
        if ($this->repo->referenced('orders', 'packaging_option_id', $o->id)) {
            return self::RETAINED;
        }
        if ($o->image) {
            $this->mediaFiles[] = $o->image;
        }
        $this->repo->nullWhere('carts', 'packaging_option_id', $o->id);
        $o->forceDelete();

        return self::PURGED;
    }

    private function purgeBundle(Model $b): string
    {
        if ($this->repo->referenced('order_items', 'bundle_id', $b->id)) {
            return self::RETAINED;
        }
        if ($b->image) {
            $this->mediaFiles[] = $b->image;
        }
        $this->mediaFiles = array_merge($this->mediaFiles, $this->repo->detachQrCodes(\App\Models\Bundle::class, [$b->id]));
        $this->repo->deleteWhere('cart_items', ['bundle_id' => $b->id]);
        $b->forceDelete(); // bundle_items cascade

        return self::PURGED;
    }

    private function purgeBrand(Model $b): string
    {
        if ($this->repo->referenced('products', 'brand_id', $b->id)) {
            return self::RETAINED;
        }
        if ($b->logo) {
            $this->mediaFiles[] = $b->logo;
        }
        $b->forceDelete();

        return self::PURGED;
    }

    private function purgeCategory(Model $c): string
    {
        if ($this->repo->referencedAny([['category_product', 'category_id'], ['categories', 'parent_id']], $c->id)) {
            return self::RETAINED;
        }
        if ($c->image) {
            $this->mediaFiles[] = $c->image;
        }
        $c->forceDelete();

        return self::PURGED;
    }

    private function purgeTag(Model $t): string
    {
        $t->forceDelete();

        return self::PURGED;
    }

    private function purgeDiscountCode(Model $d): string
    {
        return $this->purgeIfUnreferenced($d, [['orders', 'discount_code_id'], ['discount_code_usages', 'discount_code_id']]);
    }

    private function purgePaymentMethod(Model $m): string
    {
        return $this->purgeIfUnreferenced($m, [['orders', 'payment_method_id'], ['orders', 'prepay_method_id'], ['payment_events', 'payment_method_id']]);
    }

    private function purgeShippingCompany(Model $s): string
    {
        return $this->purgeIfUnreferenced($s, [['orders', 'shipping_company_id']]);
    }

    private function purgeSupplier(Model $s): string
    {
        return $this->purgeIfUnreferenced($s, [['purchase_orders', 'supplier_id']]);
    }

    private function purgeBranch(Branch $b): string
    {
        return $this->purgeIfUnreferenced($b, [
            ['orders', 'branch_id'], ['purchase_orders', 'branch_id'], ['stock_adjustments', 'branch_id'], ['stock_transfers', 'from_branch_id'],
            ['stock_transfers', 'to_branch_id'], ['order_returns', 'branch_id'], ['expenses', 'branch_id'], ['stock_movements', 'branch_id'],
        ]);
    }

    private function purgeCity(Model $c): string
    {
        return $this->purgeIfUnreferenced($c, [['orders', 'city_id'], ['customer_addresses', 'city_id'], ['branches', 'city_id']]);
    }

    // ------------------------------------------------------------------ helpers

    /** @param  array<int,array{0:string,1:string}>  $refs */
    private function purgeIfUnreferenced(Model $m, array $refs): string
    {
        if ($this->repo->referencedAny($refs, $m->getKey())) {
            return self::RETAINED;
        }
        $m->forceDelete();

        return self::PURGED;
    }

    private function variantReferenced(int $variantId): bool
    {
        return $this->repo->referencedAny([
            ['order_items', 'product_variant_id'], ['stock_movements', 'product_variant_id'], ['purchase_order_items', 'product_variant_id'],
            ['stock_adjustment_items', 'product_variant_id'], ['stock_transfer_items', 'product_variant_id'],
        ], $variantId);
    }

    private function dropVariant(ProductVariant $v): void
    {
        $this->mediaFiles = array_merge($this->mediaFiles, $this->repo->detachImages($v));
        $this->dropListings('product_variant_id', $v->id);
        $v->forceDelete();
    }

    /** QR codes (+ their files) of every tenant listing / listing variant row that points at the catalogue row. */
    private function dropListings(string $column, int $id): void
    {
        if ($column === 'product_id') {
            $tpIds = $this->repo->ids('tenant_products', ['product_id' => $id]);
            $this->mediaFiles = array_merge($this->mediaFiles, $this->repo->detachQrCodes(TenantProduct::class, $tpIds));
            $tvIds = TenantProductVariant::withoutGlobalScopes()->whereIn('tenant_product_id', $tpIds)->pluck('id')->all();
        } else {
            $tvIds = $this->repo->ids('tenant_product_variants', ['product_variant_id' => $id]);
        }
        $this->mediaFiles = array_merge($this->mediaFiles, $this->repo->detachQrCodes(TenantProductVariant::class, $tvIds));
    }
}
