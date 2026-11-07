<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Models\Product;
use App\Services\Qr\QrCodeManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Heavy QR fan-out (affiliates x products x variants) off the request. */
class SyncQrCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const LISTING = 'listing';        // one product: product + variants + every approved affiliate
    public const AFFILIATE = 'affiliate';    // one affiliate x every sellable listing

    public int $tries = 3;

    public function __construct(public string $kind, public int $id, public bool $force = false) {}

    public function handle(QrCodeManager $manager): void
    {
        if ($this->kind === self::LISTING && $tp = Product::query()->find($this->id)) {
            $manager->syncProduct($tp, true, $this->force);
        } elseif ($this->kind === self::AFFILIATE && $a = Affiliate::query()->find($this->id)) {
            $manager->syncAffiliate($a, $this->force);
        }
    }
}
