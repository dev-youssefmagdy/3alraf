<?php

namespace Database\Seeders;

/** Full demo: tenants egy + sa, 3-level categories, ~40 products with variants/images, banners, stock, sample orders. */
class DatabaseSeeder extends FoundationSeeder
{
    protected function tenants(): array
    {
        return [
            ['egy', 'Egypt Store', 'EG', 'EGP', 1, 'Africa/Cairo'],
            ['sa', 'Saudi Store', 'SA', 'SAR', 12.5, 'Asia/Riyadh'],
        ];
    }

    protected function demo(): void
    {
        $this->call([CountrySeeder::class, CitySeeder::class, CatalogSeeder::class, TenantDemoSeeder::class]);
    }
}
