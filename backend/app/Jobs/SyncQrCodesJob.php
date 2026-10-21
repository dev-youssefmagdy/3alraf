<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Qr\QrCodeManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Heavy QR fan-out (affiliates x products x variants) off the request. Runs inside the tenant's context. */
class SyncQrCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const LISTING = 'listing';        // one tenant product: product + variants + every approved affiliate
    public const AFFILIATE = 'affiliate';    // one affiliate x every sellable listing

    public int $tries = 3;

    public function __construct(public string $kind, public int $tenantId, public int $id, public bool $force = false) {}

    public function handle(QrCodeManager $manager): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        TenantContext::run($tenant, function () use ($manager) {
            if ($this->kind === self::LISTING && $tp = TenantProduct::query()->find($this->id)) {
                $manager->syncProduct($tp, true, $this->force);
            } elseif ($this->kind === self::AFFILIATE && $a = Affiliate::query()->find($this->id)) {
                $manager->syncAffiliate($a, $this->force);
            }
        });
    }
}
