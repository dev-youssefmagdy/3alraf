<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\City;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/** Demo operations data (idempotent): cities, branch, shipping company + rates, payment methods, expense categories, supplier. */
class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $sa = strtoupper((string) setting('country')) === 'SA';
        $mk = fn (string $model, array $keys, array $data) => $model::query()->firstOrCreate($keys, $data);

        $cities = [];
        foreach ($sa ? [['الرياض', 'Riyadh'], ['جدة', 'Jeddah'], ['الدمام', 'Dammam']] : [['القاهرة', 'Cairo'], ['الجيزة', 'Giza'], ['الإسكندرية', 'Alexandria']] as $i => [$ar, $en]) {
            $c = City::query()->get()->first(fn ($x) => $x->getTranslations('name')['en'] === $en)
                ?? City::query()->create(['name' => ['ar' => $ar, 'en' => $en], 'sort_order' => $i, 'is_active' => true]);
            $cities[$en] = $c;
        }

        if (! Branch::query()->exists()) {
            Branch::query()->create(['name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'], 'city_id' => array_values($cities)[0]->id, 'is_default' => true, 'is_active' => true]);
        }

        if (! ShippingCompany::query()->exists()) {
            $co = ShippingCompany::query()->create(['name' => ['ar' => 'شركة الشحن السريع', 'en' => 'Fast Shipping'], 'is_active' => true]);
            foreach ([[0, 40, 30, 1000, 1, 2], [1, 45, 35, 1000, 1, 2], [2, 60, 50, 1500, 2, 4]] as [$ci, $price, $cost, $free, $min, $max]) {
                $city = array_keys($cities)[$ci];
                [$price, $cost, $free] = $sa ? [$price / 4, $cost / 4, $free / 4] : [$price, $cost, $free];
                $co->rates()->create(['city_id' => $cities[$city]->id, 'price' => $price, 'cost' => $cost, 'free_over' => $free, 'eta_min_days' => $min, 'eta_max_days' => $max, 'supports_cod' => true]);
            }
        }

        if (! PaymentMethod::query()->exists()) {
            $base = ['fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer', 'is_active' => true];
            PaymentMethod::query()->create($base + ['type' => 'cod', 'provider' => 'cod', 'name' => ['ar' => 'الدفع عند الاستلام', 'en' => 'Cash on delivery'], 'sort_order' => 1]);
            if ($sa) {
                PaymentMethod::query()->create($base + ['type' => 'wallet', 'provider' => 'instapay', 'name' => ['ar' => 'تحويل بنكي', 'en' => 'Bank transfer'], 'account_number' => 'SA00DEMO', 'requires_proof' => true, 'sort_order' => 2]);
                PaymentMethod::query()->create(['type' => 'online', 'provider' => 'stripe', 'name' => ['ar' => 'بطاقة ائتمان', 'en' => 'Card (Stripe)'], 'fee_percent' => 2.5, 'fee_fixed' => 1, 'fee_bearer' => 'customer',
                    'credentials' => ['api_key' => 'sandbox', 'hmac_secret' => 'sandbox'], 'is_sandbox' => true, 'is_active' => false, 'sort_order' => 3]);

            } else {
                PaymentMethod::query()->create($base + ['type' => 'wallet', 'provider' => 'vodafone_cash', 'name' => ['ar' => 'فودافون كاش', 'en' => 'Vodafone Cash'], 'account_number' => '01000000000', 'requires_proof' => true, 'sort_order' => 2]);
            PaymentMethod::query()->create($base + ['type' => 'wallet', 'provider' => 'instapay', 'name' => ['ar' => 'إنستا باي', 'en' => 'InstaPay'], 'account_number' => 'demo@instapay', 'requires_proof' => true, 'sort_order' => 3]);
            PaymentMethod::query()->create(['type' => 'online', 'provider' => 'paymob', 'name' => ['ar' => 'بطاقة ائتمان (باي موب)', 'en' => 'Card (Paymob)'], 'fee_percent' => 2.5, 'fee_fixed' => 1, 'fee_bearer' => 'customer',
                'credentials' => ['api_key' => 'sandbox', 'integration_id' => '0', 'iframe_id' => '0', 'hmac_secret' => 'sandbox'], 'is_sandbox' => true, 'is_active' => false, 'sort_order' => 4]);
            }
        }

        foreach ([['إيجار', 'Rent'], ['رواتب', 'Salaries'], ['تسويق', 'Marketing'], ['مرافق', 'Utilities']] as [$ar, $en]) {
            if (! ExpenseCategory::query()->get()->contains(fn ($x) => $x->getTranslations('name')['en'] === $en)) {
                ExpenseCategory::query()->create(['name' => ['ar' => $ar, 'en' => $en], 'is_active' => true]);
            }
        }

        $mk(Supplier::class, ['name' => 'Demo Supplier'], ['phone' => $sa ? '0500000000' : '01111111111', 'is_active' => true]);
    }
}
