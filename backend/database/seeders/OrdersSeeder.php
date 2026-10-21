<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/** Demo orders-phase data for the `egy` tenant (idempotent): discount code + customer. */
class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::whereIn('slug', ['egy', 'sa'])->get() as $t) {
            $this->seedTenant($t);
        }
    }

    private function seedTenant(Tenant $t): void
    {
        DiscountCode::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $t->id, 'code' => 'WELCOME10'],
            ['type' => 'percent', 'value' => 10, 'min_order' => 100, 'max_discount' => 50, 'scope' => 'all', 'is_active' => true]);
        Customer::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $t->id, 'email' => "customer@{$t->slug}.test"],
            ['name' => 'Demo Customer', 'phone' => '01000000001', 'password' => 'password', 'is_active' => true]);
    }
}
