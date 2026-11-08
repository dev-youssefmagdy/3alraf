<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('affiliates:approve-commissions')->dailyAt('03:00');

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

// n8n integration API: call-log retention.
Schedule::command('integration:prune')->dailyAt('03:10')->onOneServer();
// Marketing internal API (MCP): request-log retention (config marketing.request_log_days).
Schedule::command('marketing:prune-requests')->dailyAt('03:15')->onOneServer();
Schedule::command('marketing:expire-suggestions')->dailyAt('03:25')->onOneServer();

// WhatsApp chat retention (config whatsapp_chat.retention_days, 0 = keep forever).
Schedule::command('whatsapp:chat-prune')->dailyAt('04:20')->withoutOverlapping()->onOneServer();

// ERP: monthly fixed-asset depreciation for the previous month (idempotent per asset + month).
Schedule::command('erp:depreciate')->monthlyOn(1, '03:20')->withoutOverlapping()->onOneServer();

// ERP: weekly ledger integrity audit (non-zero exit is reported by the scheduler; wire an alert on failure).
Schedule::command('erp:verify')->weeklyOn(1, '04:10')->withoutOverlapping()->onOneServer();
