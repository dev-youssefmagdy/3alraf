<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\MissedOpportunityService;
use App\Services\Growth\ReputationService;
use App\Services\Growth\VocService;
use Illuminate\Console\Command;

class GrowthEngage extends Command
{
    protected $signature = 'growth:engage {--tenant= : Tenant slug}';

    protected $description = 'N4 jobs per tenant, only for features that are ON: reputation spike detection, VOC improvement tasks, missed-opportunity scan';

    public function handle(GrowthFeatures $f, ReputationService $rep, VocService $voc, MissedOpportunityService $missed): int
    {
        $failed = false;
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        foreach ($f->tenantsWith('reputation_radar', 'voc', 'missed_opportunities') as $t) {
            if ($this->option('tenant') && $t->slug !== $this->option('tenant')) {
                continue;
            }
            app()->instance('currentTenant', $t);
            try {
                $a = $f->active('reputation_radar', $t) ? $rep->detect() : 0;
                $b = $f->active('voc', $t) ? $voc->generateTasks() : 0;
                $c = $f->active('missed_opportunities', $t) ? $missed->scan() : 0;
                $this->info("{$t->slug}: alerts $a, tasks $b, missed $c");
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
