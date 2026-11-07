<?php

namespace App\Console\Commands;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\QrCode;
use App\Services\Qr\QrCodeManager;
use Illuminate\Console\Command;

class RebuildQrCodes extends Command
{
    protected $signature = 'qr:rebuild {--force : regenerate even when the URL is unchanged}';

    protected $description = 'Backfill / repair the stored QR PNGs (product, variant, affiliate x product/variant, bundle) so every code points at the current storefront URL. Idempotent.';

    public function handle(QrCodeManager $manager): int
    {
        $force = (bool) $this->option('force');
        $n = 0;
        // listed products (with a store price); syncProduct removes the codes of products that are no longer sellable
        Product::query()->whereNotNull('price')->orderBy('id')->each(function (Product $p) use ($manager, $force, &$n) {
            $manager->syncProduct($p, true, $force);
            $n++;
        });
        Bundle::query()->orderBy('id')->each(fn ($b) => $manager->syncBundle($b, $force));
        $this->info("{$n} products synced, ".QrCode::query()->count().' QR codes stored.');

        return self::SUCCESS;
    }
}
