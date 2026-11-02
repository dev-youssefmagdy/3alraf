<?php

namespace Database\Seeders;

/** Full seed: tenants egy + sa, countries/cities and the gift store catalog (~1,140 bilingual products with photos, listed with EGP prices + opening stock in the EGP store). No demo orders. CatalogSeeder/TenantDemoSeeder (generic demo data) stay available for tests. */
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
        $this->call([CountrySeeder::class, CitySeeder::class, GiftCatalogSeeder::class]);
    }
}
