<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Auth\SuperAdminProvisioner;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/** Minimal base (general settings, admins, demo operations; no catalog): what the test suite seeds. DatabaseSeeder extends it with the full demo. */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LanguageSeeder::class, PermissionSeeder::class]);

        Setting::putMany($this->generalSettings());

        // demo phones (fake +20 numbers); real super admins are created with `php artisan admin:provision-super`
        $admin = app(SuperAdminProvisioner::class)->provision('Super Admin', '201000000001', 'password');
        // demo super admin = the former platform super admin (otp_exempt is set by the provisioner, like the conversion does)
        $admin->update(['email' => 'admin@3alraf.test']);

        $owner = Admin::query()->firstOrNew(['phone' => '201000000101']);
        $owner->fill(['name' => 'Owner', 'email' => 'owner@egy.test', 'password' => 'password', 'is_active' => true])->save();
        $owner->assignRole(Role::findByName('owner', PermissionSeeder::GUARD));

        $this->call([OperationsSeeder::class, OrdersSeeder::class, GrowthSeeder::class]);
        $this->demo();
    }

    /** @return array<string,mixed> Settings → General */
    protected function generalSettings(): array
    {
        return [
            'name' => 'Egypt Store', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'EGP',
            'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Africa/Cairo',
        ];
    }

    protected function demo(): void {}
}
