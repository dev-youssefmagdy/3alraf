<?php

namespace App\Console\Commands;

use App\Exceptions\AccountingException;
use App\Models\Erp\FixedAsset;
use App\Services\Erp\Assets\AssetService;
use Illuminate\Console\Command;

class ErpDepreciate extends Command
{
    protected $signature = 'erp:depreciate {--period= : YYYY-MM (default: previous month)}';

    protected $description = 'Post monthly fixed-asset depreciation for every active asset (idempotent per asset + month)';

    public function handle(AssetService $svc): int
    {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Invalid --period, expected YYYY-MM.');

            return self::FAILURE;
        }
        $failed = false;
        $posted = 0;
        foreach (FixedAsset::where('status', 'active')->get() as $asset) {
            try {
                $posted += $svc->depreciate($asset, $period);
            } catch (AccountingException $e) {
                $failed = true; // e.g. closed period: report and carry on with the other assets
                $this->error("#{$asset->id}: ".$e->getMessage());
            } catch (\Throwable $e) {
                $failed = true;
                report($e);
                $this->error("#{$asset->id}: failed (".class_basename($e).')');
            }
        }
        $this->info("$posted depreciation entries for $period");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
