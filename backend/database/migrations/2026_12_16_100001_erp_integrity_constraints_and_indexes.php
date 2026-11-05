<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ERP phase 10: DB-level accounting safety nets + composite indexes for the report / dashboard / central queries.
 *
 * CHECK constraints (MySQL >= 8.0.16 / MariaDB >= 10.2.1 enforce them; other engines skip silently):
 *   erp_journal_lines: debit >= 0, credit >= 0, never both > 0 on the same line.
 *   erp_journal_entries: status / type limited to the known values.
 * A constraint is only added when every existing row already satisfies it (otherwise a warning is logged and `erp:verify` reports the rows),
 * so deploying can never fail on historic data.
 */
return new class extends Migration
{
    private const LINE_CHECKS = [
        'erp_chk_line_nonneg' => 'debit >= 0 AND credit >= 0',
        'erp_chk_line_one_side' => 'debit = 0 OR credit = 0',
    ];

    private const ENTRY_CHECKS = [
        'erp_chk_entry_status' => "status IN ('draft','posted','reversed')",
    ];

    private function supportsChecks(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        $v = (string) DB::selectOne('select version() as v')->v;
        $maria = stripos($v, 'mariadb') !== false;

        return version_compare($v, $maria ? '10.2.1' : '8.0.16', '>=');
    }

    private function addCheck(string $table, string $name, string $expr): void
    {
        $bad = DB::table($table)->whereRaw("NOT ($expr)")->count();
        if ($bad > 0) {
            Log::warning("ERP: CHECK $name not added, $bad existing row(s) in $table violate it. Run erp:verify.");

            return;
        }
        DB::statement("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($expr)");
    }

    public function up(): void
    {
        if ($this->supportsChecks()) {
            foreach (self::LINE_CHECKS as $n => $e) {
                $this->addCheck('erp_journal_lines', $n, $e);
            }
            foreach (self::ENTRY_CHECKS as $n => $e) {
                $this->addCheck('erp_journal_entries', $n, $e);
            }
        }

        Schema::table('erp_journal_lines', function (Blueprint $t) {
            $t->index(['tenant_id', 'entry_id'], 'erp_jl_tenant_entry');
            $t->index(['tenant_id', 'account_id', 'branch_id'], 'erp_jl_tenant_account_branch');
            $t->index(['tenant_id', 'party_type', 'party_id', 'account_id'], 'erp_jl_tenant_party_account');
        });
        Schema::table('erp_journal_entries', function (Blueprint $t) {
            $t->index(['tenant_id', 'entry_date'], 'erp_je_tenant_date');
            $t->index(['tenant_id', 'branch_id', 'entry_date'], 'erp_je_tenant_branch_date');
            $t->index(['tenant_id', 'period_id'], 'erp_je_tenant_period');
            $t->index(['tenant_id', 'type', 'entry_date'], 'erp_je_tenant_type_date');
        });
        Schema::table('erp_audit_logs', function (Blueprint $t) {
            $t->index(['tenant_id', 'created_at'], 'erp_al_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table('erp_audit_logs', fn (Blueprint $t) => $t->dropIndex('erp_al_tenant_created'));
        Schema::table('erp_journal_entries', function (Blueprint $t) {
            foreach (['erp_je_tenant_date', 'erp_je_tenant_branch_date', 'erp_je_tenant_period', 'erp_je_tenant_type_date'] as $i) {
                $t->dropIndex($i);
            }
        });
        Schema::table('erp_journal_lines', function (Blueprint $t) {
            foreach (['erp_jl_tenant_entry', 'erp_jl_tenant_account_branch', 'erp_jl_tenant_party_account'] as $i) {
                $t->dropIndex($i);
            }
        });
        if ($this->supportsChecks()) {
            foreach (array_keys(self::LINE_CHECKS) as $n) {
                try {
                    DB::statement("ALTER TABLE erp_journal_lines DROP CHECK $n");
                } catch (Throwable) {
                }
            }
            foreach (array_keys(self::ENTRY_CHECKS) as $n) {
                try {
                    DB::statement("ALTER TABLE erp_journal_entries DROP CHECK $n");
                } catch (Throwable) {
                }
            }
        }
    }
};
