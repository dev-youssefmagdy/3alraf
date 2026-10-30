<?php

namespace Database\Seeders;

use App\Models\CentralAdmin;
use App\Services\Auth\SuperAdminProvisioner;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use Illuminate\Database\Seeder;

/** Minimal base (egy only, no catalog): what the test suite seeds. DatabaseSeeder extends it with the full demo. */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LanguageSeeder::class, PermissionSeeder::class]);

        // demo phones (fake +20 numbers); real super admins are created with `php artisan admin:provision-super`
        $admin = app(SuperAdminProvisioner::class)->provision(null, 'Super Admin', '201000000001', 'password');
        $admin->update(['email' => 'admin@3alraf.test']);

        foreach ($this->tenants() as [$slug, $name, $country, $cur, $rate, $tz]) {
            $t = Tenant::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'country' => $country, 'currency_code' => $cur, 'currency_symbol' => $cur,
                'currency_rate' => $rate, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => $tz, 'is_active' => true,
            ]);
            $ta = app(SuperAdminProvisioner::class)->provision($t->id, ucfirst($slug).' Owner', '2010000001'.sprintf('%02d', $t->id % 100), 'password');
            $ta->update(['email' => "owner@{$slug}.test"]);
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
