<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | DB-level guard for payment-proof reuse (the application check alone is a check-then-insert race). `proof_guard` is a VIRTUAL generated
 | column: "{tenant}:{sha256}" while the order is a LIVE holder of its proof (not rejected, not cancelled, not soft-deleted), NULL otherwise.
 | A unique index over it lets MySQL/MariaDB refuse a second live order with the same proof (NULLs never collide, so rejected / cancelled
 | orders free the hash again - the column re-evaluates on every status change). Other drivers (sqlite) keep the application check only.
 */
return new class extends Migration
{
    private function supported(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }
        DB::statement("ALTER TABLE orders ADD COLUMN proof_guard VARCHAR(100) GENERATED ALWAYS AS (
            IF(payment_proof_hash IS NOT NULL AND status <> 'cancelled' AND deleted_at IS NULL AND (proof_status IS NULL OR proof_status <> 'rejected'),
               CONCAT(tenant_id, ':', payment_proof_hash), NULL)) VIRTUAL");
        DB::statement('ALTER TABLE orders ADD UNIQUE INDEX orders_proof_guard_unique (proof_guard)');
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }
        DB::statement('ALTER TABLE orders DROP INDEX orders_proof_guard_unique');
        DB::statement('ALTER TABLE orders DROP COLUMN proof_guard');
    }
};
