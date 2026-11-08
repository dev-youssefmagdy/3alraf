<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * web_search is only wired in the Anthropic and OpenRouter (web plugin) drivers; align the capability flags with that.
 * Query builder only: the AiModel class was removed with the AI module (MARKETER_PLAN.md P1); the tables are dropped by 2027_01_01_000000_drop_ai_module.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_models') || ! Schema::hasTable('ai_providers')) {
            return;
        }
        DB::table('ai_models as m')->join('ai_providers as p', 'p.id', '=', 'm.ai_provider_id')->where('m.kind', 'text')
            ->select('m.id', 'm.capabilities', 'p.driver')->get()->each(function ($m) {
                $caps = json_decode((string) $m->capabilities, true) ?: [];
                $caps['web_search'] = in_array($m->driver, ['anthropic', 'openrouter'], true);
                DB::table('ai_models')->where('id', $m->id)->update(['capabilities' => json_encode(array_filter($caps))]);
            });
    }

    public function down(): void {}
};
