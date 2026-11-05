<?php

namespace Database\Seeders;

/** Live flavour of the gift catalog: every product, listed and active, for every EGP store with NO stock, no opening movements. Stock arrives later through real purchases / adjustments. */
class GiftCatalogNoStockSeeder extends GiftCatalogSeeder
{
    protected function withStock(): bool
    {
        return false;
    }
}
