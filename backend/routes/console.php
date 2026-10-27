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

// Media hygiene: weekly report of unreferenced files (dry run; delete manually with `media:prune-orphans`).
Schedule::command('media:prune-orphans --dry-run')->weeklyOn(0, '05:00');

// Retention: purge / anonymise soft-deleted records older than SOFT_DELETE_RETENTION_DAYS (default 60), off-peak.
Schedule::command('retention:purge')->dailyAt('04:45')->withoutOverlapping()->onOneServer();

Schedule::command('ai:expire-drafts')->dailyAt('02:30');

// n8n growth system: outbox retries every minute, housekeeping nightly.
Schedule::command('growth:dispatch-pending')->everyMinute()->withoutOverlapping();
Schedule::command('growth:maintain')->dailyAt('02:45');
