<?php

namespace Database\Seeders;

use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use Illuminate\Database\Seeder;

/** Minimal base (egy only, no catalog): what the test suite seeds. DatabaseSeeder extends it with the full demo. */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LanguageSeeder::class, PermissionSeeder::class]);

        $admin = CentralAdmin::updateOrCreate(['email' => 'admin@3alraf.test'], ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true]);
        $admin->syncRoles('super-admin');

        foreach ($this->tenants() as [$slug, $name, $country, $cur, $rate, $tz]) {
            $t = Tenant::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'country' => $country, 'currency_code' => $cur, 'currency_symbol' => $cur,
                'currency_rate' => $rate, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => $tz, 'is_active' => true,
            ]);
            $ta = TenantAdmin::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $t->id, 'email' => "owner@{$slug}.test"], ['name' => ucfirst($slug).' Owner', 'password' => 'password', 'is_active' => true]);
            $ta->syncRoles('tenant-owner');
        }

        $this->call([OperationsSeeder::class, OrdersSeeder::class, GrowthSeeder::class]);
        $this->demo();
    }

    /** @return array<int,array> slug, name, country, currency, rate-to-base, timezone */
    protected function tenants(): array
    {
        return [['egy', 'Egypt Store', 'EG', 'EGP', 1, 'Africa/Cairo']];
    }

    protected function demo(): void {}
}
