<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Illuminate\Support\Facades\Schedule::command('affiliates:approve-commissions')->dailyAt('03:00');

// Ops: nightly backup (database + private files), retention clean-up, health monitor, housekeeping.
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('backup:monitor')->dailyAt('03:30');
Schedule::command('activitylog:clean')->dailyAt('04:00');
Schedule::command('queue:prune-failed --hours=336')->dailyAt('04:15');
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('04:30');
