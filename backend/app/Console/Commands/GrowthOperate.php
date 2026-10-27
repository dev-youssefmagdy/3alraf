<?php

namespace App\Console\Commands;

use App\Services\Growth\AmbassadorService;
use App\Services\Growth\CeoChallengeService;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\ReturnIntelService;
use App\Services\Growth\SeasonalCalendarService;
use App\Services\Growth\TrendRadarService;
use Illuminate\Console\Command;

class GrowthOperate extends Command
{
    protected $signature = 'growth:operate {--tenant= : Tenant slug}';

    protected $description = 'N6 jobs per tenant, only for features that are ON: stock-to-story, return fix tasks, weekly trend scan and CEO challenge (all idempotent per week) + ambassador reward ledger. Never purchases.';

    public function handle(GrowthFeatures $f, InventoryDecisionService $inv, ReturnIntelService $ret, TrendRadarService $trend, CeoChallengeService $ceo, AmbassadorService $amb, SeasonalCalendarService $season): int
    {
        $failed = false;
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        foreach ($f->tenantsWith('inventory_engine', 'return_intel', 'trend_radar', 'ceo_challenge', 'ambassadors', 'seasonal_calendar') as $t) {
            if ($this->option('tenant') && $t->slug !== $this->option('tenant')) {
                continue;
            }
            app()->instance('currentTenant', $t);
            try {
                $a = $f->active('inventory_engine', $t) ? $inv->stockToStory() : 0;
                $b = $f->active('return_intel', $t) ? $ret->generateTasks() : 0;
                $c = $f->active('trend_radar', $t) ? $trend->scan() : 0;
                $d = $f->active('ceo_challenge', $t) ? $ceo->generate()->id : 0;
                $e = $f->active('ambassadors', $t) ? $amb->syncLedger() : 0;
                $g = $f->active('seasonal_calendar', $t) ? $season->remindUnconfirmed() : 0;
                $this->info("{$t->slug}: stories $a, return tasks $b, trends $c, challenge $d, rewards $e, hijri reminders $g");
            } catch (\Throwable $e) {
                $failed = true;
                report($e); // one tenant failing must not stop the others
                $this->error("{$t->slug}: failed (".class_basename($e).')');
            }
        }
        $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
