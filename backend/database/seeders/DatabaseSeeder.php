<?php

namespace Database\Seeders;

/** Full seed: general settings (EG / EGP, ar+en), countries/cities and the gift catalog (~1,140 bilingual products with photos, EGP prices + opening stock). No demo orders. CatalogSeeder/DemoSeeder (generic demo data) stay available for tests. */
class DatabaseSeeder extends FoundationSeeder
{
    protected function demo(): void
    {
        $this->call([CountrySeeder::class, CitySeeder::class, GiftCatalogSeeder::class]);
    }
}
