<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => phone column */
    public array $cols = ['customers' => 'phone', 'customer_addresses' => 'phone', 'orders' => 'customer_phone', 'affiliates' => 'phone'];

    public function up(): void
    {
        foreach ($this->cols as $table => $col) {
            if (! Schema::hasColumn($table, 'phone_country_code')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('phone_country_code', 6)->nullable()->after($col));
            }
        }
        $this->backfill();
    }

    /** Idempotent: only rows without a code; unparsable rows keep their value (listed by phone:audit). */
    public function backfill(): void
    {
        $tenants = DB::table('tenants')->pluck('country', 'id');
        foreach ($this->cols as $table => $col) {
            DB::table($table)->whereNull('phone_country_code')->whereNotNull($col)->where($col, '!=', '')->orderBy('id')->chunkById(500, function ($rows) use ($table, $col, $tenants) {
                foreach ($rows as $r) {
                    $iso = $tenants[$r->tenant_id] ?? null;
                    $code = Phone::config($iso)['dial'] ?? null;
                    if (! $code) {
                        continue;
                    }
                    [$n, $err] = Phone::normalize((string) $r->{$col}, $iso);
                    if ($n !== null && ! $err) {
                        DB::table($table)->where('id', $r->id)->update([$col => $n, 'phone_country_code' => $code]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->cols) as $table) {
            if (Schema::hasColumn($table, 'phone_country_code')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('phone_country_code'));
            }
        }
    }
};
