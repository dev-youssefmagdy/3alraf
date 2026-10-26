<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Current OpenAI catalog (general, image, audio, embedding, moderation, open-weight, specialized).
 * Idempotent. Prices are NOT published in the source list: input/output are ESTIMATED upper bounds
 * (USD per 1M tokens, deliberately high so budgets are never under-counted). Replace with real prices in Central > AI > Models.
 */
return new class extends Migration
{
    private function catalog(): array
    {
        $text = ['tools' => true, 'vision' => true, 'json_mode' => true, 'reasoning' => true];
        $none = [];

        // [model_id, label, kind, capabilities, input_cost(max est.), output_cost(max est.), max_output]
        return [
            ['gpt-6-astra', 'GPT-6 Astra', 'text', $text, 30, 120, 128000],
            ['gpt-6.1-sol', 'GPT-6.1 Sol', 'text', $text, 15, 60, 128000],
            ['gpt-6.1-terra', 'GPT-6.1 Terra', 'text', $text, 8, 32, 128000],
            ['gpt-6-luna', 'GPT-6 Luna', 'text', $text, 2, 8, 128000],
            ['gpt-6-sol', 'GPT-6 Sol', 'text', $text, 15, 60, 128000],
            ['gpt-5.6-sol', 'GPT-5.6 Sol', 'text', $text, 15, 60, 128000],
            ['gpt-5.6-terra', 'GPT-5.6 Terra', 'text', $text, 8, 32, 128000],
            ['gpt-5.6-luna', 'GPT-5.6 Luna', 'text', $text, 2, 8, 128000],
            ['gpt-5.5', 'GPT-5.5', 'text', $text, 10, 40, 128000],
            ['gpt-5.5-pro', 'GPT-5.5 Pro', 'text', $text, 30, 120, 128000],
            ['gpt-5.4', 'GPT-5.4', 'text', $text, 10, 40, 128000],
            ['gpt-5.4-pro', 'GPT-5.4 Pro', 'text', $text, 30, 120, 128000],
            ['gpt-5.4-mini', 'GPT-5.4 mini', 'text', $text, 2, 8, 128000],
            ['gpt-5.2', 'GPT-5.2', 'text', $text, 10, 40, 128000],
            ['gpt-5.2-pro', 'GPT-5.2 Pro', 'text', $text, 30, 120, 128000],
            ['gpt-5.6-cyber', 'GPT-5.6 Cyber (restricted)', 'text', $text, 15, 60, 128000],
            ['gpt-rosalind-research', 'GPT Rosalind Research (restricted)', 'text', $text, 15, 60, 128000],
            ['gpt-rosalind-discovery', 'GPT Rosalind Discovery (restricted)', 'text', $text, 15, 60, 128000],
            ['gpt-oss-120b', 'GPT-OSS 120B (open-weight)', 'text', ['tools' => true, 'json_mode' => true, 'reasoning' => true], 1, 4, 128000],
            ['gpt-oss-20b', 'GPT-OSS 20B (open-weight)', 'text', ['tools' => true, 'json_mode' => true, 'reasoning' => true], 0.5, 2, 128000],

            ['gpt-image-2.5-sunburst', 'GPT Image 2.5 Sunburst', 'image', ['edit' => true], 10, 40, null],
            ['gpt-image-2.5-flare', 'GPT Image 2.5 Flare', 'image', ['edit' => true], 5, 20, null],
            ['gpt-image-2', 'GPT Image 2', 'image', ['edit' => true], 10, 40, null],

            ['gpt-live-1', 'GPT Live 1', 'audio', $none, 40, 80, null],
            ['gpt-realtime-2.1', 'GPT Realtime 2.1', 'audio', ['tools' => true], 40, 80, null],
            ['gpt-realtime-2.1-mini', 'GPT Realtime 2.1 mini', 'audio', ['tools' => true], 10, 20, null],
            ['gpt-realtime-2', 'GPT Realtime 2', 'audio', ['tools' => true], 40, 80, null],
            ['gpt-realtime-1.5', 'GPT Realtime 1.5', 'audio', ['tools' => true], 40, 80, null],
            ['gpt-realtime-translate', 'GPT Realtime Translate', 'audio', $none, 40, 80, null],
            ['gpt-realtime-whisper', 'GPT Realtime Whisper', 'audio', $none, 10, null, null],
            ['gpt-live-transcribe', 'GPT Live Transcribe', 'audio', $none, 10, null, null],
            ['gpt-transcribe', 'GPT Transcribe', 'audio', $none, 10, null, null],
            ['gpt-audio-1.5', 'GPT Audio 1.5', 'audio', ['tools' => true], 40, 80, null],

            ['text-embedding-ada-002', 'Text Embedding Ada 002', 'embedding', $none, 0.2, null, null],
            ['omni-moderation', 'Omni Moderation', 'other', ['vision' => true], 0, 0, null],
        ];
    }

    public function up(): void
    {
        $now = now();
        $provider = DB::table('ai_providers')->where('driver', 'openai')->whereNull('deleted_at')->first();
        $pid = $provider->id ?? DB::table('ai_providers')->insertGetId([
            'driver' => 'openai', 'name' => 'OpenAI', 'base_url' => null,
            'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ($this->catalog() as [$id, $label, $kind, $caps, $in, $out, $max]) {
            if (DB::table('ai_models')->where('ai_provider_id', $pid)->where('model_id', $id)->exists()) {
                continue;
            }
            DB::table('ai_models')->insert([
                'ai_provider_id' => $pid, 'model_id' => $id, 'label' => $label, 'kind' => $kind,
                'capabilities' => json_encode($caps), 'input_cost' => $in, 'output_cost' => $out, 'max_output' => $max,
                'is_active' => true, 'is_default' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // data seed: intentionally non-destructive
    }
};
