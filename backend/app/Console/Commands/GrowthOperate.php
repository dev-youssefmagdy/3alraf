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
    protected $signature = 'growth:operate';

    protected $description = 'N6 jobs, only for features that are ON: stock-to-story, return fix tasks, weekly trend scan and CEO challenge (all idempotent per week) + ambassador reward ledger. Never purchases.';

    public function handle(GrowthFeatures $f, InventoryDecisionService $inv, ReturnIntelService $ret, TrendRadarService $trend, CeoChallengeService $ceo, AmbassadorService $amb, SeasonalCalendarService $season): int
    {
        $a = $f->active('inventory_engine') ? $inv->stockToStory() : 0;
        $b = $f->active('return_intel') ? $ret->generateTasks() : 0;
        $c = $f->active('trend_radar') ? $trend->scan() : 0;
        $d = $f->active('ceo_challenge') ? $ceo->generate()->id : 0;
        $e = $f->active('ambassadors') ? $amb->syncLedger() : 0;
        $g = $f->active('seasonal_calendar') ? $season->remindUnconfirmed() : 0;
        $this->info("stories $a, return tasks $b, trends $c, challenge $d, rewards $e, hijri reminders $g");

        return self::SUCCESS;
    }
}
