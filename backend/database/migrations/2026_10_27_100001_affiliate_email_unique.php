<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate e-mail is unique per tenant (the same person may promote on two stores), case-insensitively.
 * The unique index (tenant_id, email) on a utf8mb4_unicode_ci column already exists; this migration normalises the stored values
 * (trim + lowercase) and resolves any collision that normalisation uncovers deterministically: the OLDEST account keeps the address,
 * newer duplicates get a tombstoned address "local+dup{id}@domain" (they can still be found and removed by an admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('affiliates')->orderBy('id')->get(['id', 'tenant_id', 'email']);
        $seen = [];
        foreach ($rows as $r) {
            $norm = mb_strtolower(trim($r->email));
            $key = $r->tenant_id.'|'.$norm;
            if (isset($seen[$key])) {
                [$local, $domain] = array_pad(explode('@', $norm, 2), 2, 'invalid');
                $norm = $local.'+dup'.$r->id.'@'.$domain;
            } else {
                $seen[$key] = true;
            }
            if ($norm !== $r->email) {
                DB::table('affiliates')->where('id', $r->id)->update(['email' => $norm]);
            }
        }

        if (! collect(Schema::getIndexes('affiliates'))->contains(fn ($i) => $i['unique'] && $i['columns'] === ['tenant_id', 'email'])) {
            Schema::table('affiliates', fn ($t) => $t->unique(['tenant_id', 'email']));
        }
    }

    public function down(): void {}
};
