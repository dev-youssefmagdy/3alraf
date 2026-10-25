<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantProduct;
use Illuminate\Database\Seeder;

/**
 * OPTIONAL, idempotent, additive-only demo data for bundles / special packaging / offline payment proof.
 *   php artisan db:seed --class=BundlePackagingDemoSeeder
 * Creates (only if missing, per tenant): 1 bundle "demo-bundle" (first 2 active products), 1 packaging option "Gift wrap",
 * and one offline (wallet) payment method if the tenant has none. Never updates or deletes existing rows.
 */
class BundlePackagingDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::whereIn('slug', ['egy', 'sa'])->get() as $t) {
            app()->instance('currentTenant', $t);
            $this->bundle();
            $this->packaging();
            $this->offline($t);
            app()->forgetInstance('currentTenant');
        }
    }

    private function bundle(): void
    {
        if (Bundle::withTrashed()->where('slug', 'demo-bundle')->exists()) {
            return;
        }
        $tps = TenantProduct::with('product.variants')->where('is_active', true)->orderBy('id')->get()
            ->filter(fn ($tp) => $tp->product && ! $tp->product->isVariable())->take(2);
        if ($tps->count() < 2) {
            return;
        }
        $regular = $tps->sum(fn ($tp) => (float) ($tp->sale_price ?: $tp->price));
        $b = Bundle::create(['name' => ['ar' => 'باقة التوفير', 'en' => 'Saver bundle'], 'description' => ['ar' => 'منتجان بسعر مخفض.', 'en' => 'Two products at a special price.'],
            'slug' => 'demo-bundle', 'price' => round($regular * 0.85, 2), 'is_active' => true, 'sort_order' => 1]);
        foreach ($tps as $tp) {
            BundleItem::create(['bundle_id' => $b->id, 'product_id' => $tp->product_id, 'product_variant_id' => 0, 'quantity' => 1]);
        }
    }

    private function packaging(): void
    {
        if (PackagingOption::withTrashed()->exists()) {
            return;
        }
        PackagingOption::create(['name' => ['ar' => 'تغليف هدايا', 'en' => 'Gift wrap'], 'description' => ['ar' => 'تغليف فاخر مع بطاقة.', 'en' => 'Premium wrap with a card.'],
            'price' => 25, 'cost' => 8, 'is_active' => true, 'sort_order' => 1]);
    }

    private function offline(Tenant $t): void
    {
        if (PaymentMethod::withoutGlobalScopes()->where('tenant_id', $t->id)->where('type', 'wallet')->exists()) {
            return;
        }
        PaymentMethod::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'type' => 'wallet', 'provider' => 'instapay', 'name' => ['ar' => 'تحويل يدوي', 'en' => 'Manual transfer'],
            'account_number' => 'demo@instapay', 'requires_proof' => true, 'is_active' => true, 'sort_order' => 9]);
    }
}
