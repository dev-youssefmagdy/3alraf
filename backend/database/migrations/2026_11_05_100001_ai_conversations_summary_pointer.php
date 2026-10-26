<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $t) {
            $t->unsignedBigInteger('summarized_until_id')->default(0)->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', fn (Blueprint $t) => $t->dropColumn('summarized_until_id'));
    }
};
