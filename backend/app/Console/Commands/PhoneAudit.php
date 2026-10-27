<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PhoneAudit extends Command
{
    protected $signature = 'phone:audit {--limit=50}';

    protected $description = 'List stored phone numbers that have no country code yet (unparsable by the backfill; never guessed)';

    public function handle(): int
    {
        $total = 0;
        foreach (['customers' => 'phone', 'customer_addresses' => 'phone', 'orders' => 'customer_phone', 'affiliates' => 'phone'] as $table => $col) {
            $q = DB::table($table)->whereNull('phone_country_code')->whereNotNull($col)->where($col, '!=', '');
            $n = $q->count();
            $total += $n;
            $this->line("$table: $n without code");
            foreach ($q->limit((int) $this->option('limit'))->get(['id', 'tenant_id', $col]) as $r) {
                $this->line("  #{$r->id} tenant {$r->tenant_id}: ".substr((string) $r->{$col}, 0, 4).'***');
            }
        }

        return $total ? self::FAILURE : self::SUCCESS;
    }
}
