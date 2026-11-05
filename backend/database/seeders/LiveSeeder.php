<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Live data only (no demo accounts, no demo operations/orders/growth rows, no stock): what the platform needs to run (languages, roles & permissions),
 * the countries, the cities of every existing store and the whole gift catalog listed (active) without stock. Idempotent; stores come from `tenants:create`
 * (cities and listings are per store, so run it again after creating one). Run through `php artisan seed:live`, which also removes earlier demo data.
 */
class LiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LanguageSeeder::class, PermissionSeeder::class, CountrySeeder::class, CitySeeder::class, GiftCatalogNoStockSeeder::class]);
    }
}
