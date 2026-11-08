<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Live data only (no demo accounts, no demo operations/orders/marketing rows, no stock): what the shop needs to run (languages, roles & permissions),
 * the countries, the cities of the site country and the whole gift catalog activated without stock. Idempotent. General settings are created by
 * the migrations (config/settings.php defaults) and edited on Settings → General. Run through `php artisan seed:live`, which also removes earlier demo data.
 */
class LiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LanguageSeeder::class, PermissionSeeder::class, CountrySeeder::class, CitySeeder::class, GiftCatalogNoStockSeeder::class]);
    }
}
