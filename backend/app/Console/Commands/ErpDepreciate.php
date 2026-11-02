<?php

namespace App\Console\Commands;

use App\Exceptions\AccountingException;
use App\Models\Erp\FixedAsset;
use App\Models\Tenant;
use App\Services\Erp\Assets\AssetService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ErpDepreciate extends Command
{
    protected $signature = 'erp:depreciate {--tenant= : Tenant slug} {--period= : YYYY-MM (default: previous month)}';

    protected $description = 'Post monthly fixed-asset depreciation for every active asset (idempotent per asset + month)';

    public function handle(AssetService $svc): int
    {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Invalid --period, expected YYYY-MM.');

            return self::FAILURE;
        }
        $failed = false;
        foreach (Tenant::query()->when($this->option('tenant'), fn ($q, $s) => $q->where('slug', $s))->get() as $t) {
            // every tenant runs inside its own TenantContext (scope, settings and account caches are per tenant)
            $posted = TenantContext::run($t, function () use ($svc, $period, $t, &$failed) {
                $posted = 0;
                foreach (FixedAsset::where('status', 'active')->get() as $asset) {
                    try {
                        $posted += $svc->depreciate($asset, $period);
                    } catch (AccountingException $e) {
                        $failed = true; // e.g. closed period: report and carry on with the other assets
                        $this->error("{$t->slug} #{$asset->id}: ".$e->getMessage());
                    } catch (\Throwable $e) {
                        $failed = true;
                        report($e);
                        $this->error("{$t->slug} #{$asset->id}: failed (".class_basename($e).')');
                    }
                }

                return $posted;
            });
            $this->info("{$t->slug}: $posted depreciation entries for $period");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
