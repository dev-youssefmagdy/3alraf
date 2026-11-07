<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\MissedOpportunityService;
use App\Services\Growth\ReputationService;
use App\Services\Growth\VocService;
use Illuminate\Console\Command;

class GrowthEngage extends Command
{
    protected $signature = 'growth:engage';

    protected $description = 'N4 jobs, only for features that are ON: reputation spike detection, VOC improvement tasks, missed-opportunity scan';

    public function handle(GrowthFeatures $f, ReputationService $rep, VocService $voc, MissedOpportunityService $missed): int
    {
        $a = $f->active('reputation_radar') ? $rep->detect() : 0;
        $b = $f->active('voc') ? $voc->generateTasks() : 0;
        $c = $f->active('missed_opportunities') ? $missed->scan() : 0;
        $this->info("alerts $a, tasks $b, missed $c");

        return self::SUCCESS;
    }
}
