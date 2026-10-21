<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('activitylog.table_name', 'activity_log'), function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->nullable()->after('batch_uuid');
            $t->index(['tenant_id', 'created_at'], 'activity_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table(config('activitylog.table_name', 'activity_log'), function (Blueprint $t) {
            $t->dropIndex('activity_tenant_created');
            $t->dropColumn('tenant_id');
        });
    }
};
