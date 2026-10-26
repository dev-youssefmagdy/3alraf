<?php

use App\Models\AiModel;
use Illuminate\Database\Migrations\Migration;

/** web_search is only wired in the Anthropic and OpenRouter (web plugin) drivers; align the capability flags with that. */
return new class extends Migration
{
    public function up(): void
    {
        AiModel::with('provider')->where('kind', 'text')->get()->each(function (AiModel $m) {
            $driver = $m->provider->driver->value;
            $caps = $m->capabilities ?? [];
            $caps['web_search'] = in_array($driver, ['anthropic', 'openrouter'], true);
            $m->update(['capabilities' => array_filter($caps)]);
        });
    }

    public function down(): void {}
};
