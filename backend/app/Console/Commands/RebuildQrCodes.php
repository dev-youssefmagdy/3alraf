<?php

namespace App\Console\Commands;

use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Qr\QrCodeManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

class RebuildQrCodes extends Command
{
    protected $signature = 'qr:rebuild {--tenant= : tenant id or slug (default: all active tenants)} {--force : regenerate even when the URL is unchanged}';

    protected $description = 'Backfill / repair the stored QR PNGs (tenant product, variant, affiliate x product/variant). Idempotent.';

    public function handle(QrCodeManager $manager): int
    {
        $tenants = Tenant::query()->where('is_active', true)
            ->when($this->option('tenant'), fn ($q, $t) => $q->where(fn ($w) => $w->where('id', is_numeric($t) ? (int) $t : 0)->orWhere('slug', $t)))->get();
        if ($tenants->isEmpty()) {
            $this->error('No matching active tenant.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $n = TenantContext::run($tenant, function () use ($manager) {
                $n = 0;
                TenantProduct::query()->orderBy('id')->each(function (TenantProduct $tp) use ($manager, &$n) {
                    $manager->syncProduct($tp, true, (bool) $this->option('force'));
                    $n++;
                });

                \App\Models\Bundle::query()->orderBy('id')->each(fn ($b) => $manager->syncBundle($b, (bool) $this->option('force')));

                return $n;
            });
            $codes = TenantContext::run($tenant, fn () => QrCode::query()->count());
            $this->info("{$tenant->slug}: {$n} listings synced, {$codes} QR codes stored.");
        }

        return self::SUCCESS;
    }
}
