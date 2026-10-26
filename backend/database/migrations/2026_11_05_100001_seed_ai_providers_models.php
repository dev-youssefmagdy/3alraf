<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalog of supported AI vendors + their models. Idempotent (keyed by driver / provider+model_id).
 * Providers are seeded INACTIVE with no key: a central admin pastes the key, tests, then activates.
 * Costs are USD per 1M tokens and only filled where the price is known; edit in Central > AI > Models.
 */
return new class extends Migration
{
    private function catalog(): array
    {
        $text = ['tools' => true, 'vision' => true, 'json_mode' => true];

        return [
            ['driver' => 'anthropic', 'name' => 'Anthropic (Claude)', 'base_url' => null, 'models' => [
                ['claude-sonnet-5-5', 'Claude Sonnet 5.5', 'text', $text + ['web_search' => true], null, null, 64000, true],
                ['claude-opus-5-5', 'Claude Opus 5.5', 'text', $text + ['web_search' => true], null, null, 64000, false],
                ['claude-haiku-5-5', 'Claude Haiku 5.5', 'text', $text, null, null, 64000, false],
                ['claude-fable-5-1', 'Claude Fable 5.1', 'text', $text + ['web_search' => true], null, null, 64000, false],
                ['claude-sonnet-4-5', 'Claude Sonnet 4.5', 'text', $text + ['web_search' => true], 3, 15, 64000, false],
                ['claude-haiku-4-5', 'Claude Haiku 4.5', 'text', $text, 1, 5, 64000, false],
                ['claude-opus-4-5', 'Claude Opus 4.5', 'text', $text + ['web_search' => true], 5, 25, 64000, false],
                ['claude-opus-4-1', 'Claude Opus 4.1', 'text', $text + ['web_search' => true], 15, 75, 32000, false],
                ['claude-opus-4-0', 'Claude Opus 4', 'text', $text + ['web_search' => true], 15, 75, 32000, false],
                ['claude-sonnet-4-0', 'Claude Sonnet 4', 'text', $text + ['web_search' => true], 3, 15, 64000, false],
                ['claude-3-5-haiku-latest', 'Claude Haiku 3.5', 'text', ['tools' => true, 'json_mode' => true], 0.8, 4, 8192, false],
            ]],
            ['driver' => 'openai', 'name' => 'OpenAI', 'base_url' => null, 'models' => [
                ['gpt-4.1', 'GPT-4.1', 'text', $text, 2, 8, 32768, false],
                ['gpt-4.1-mini', 'GPT-4.1 mini', 'text', $text, 0.4, 1.6, 32768, false],
                ['gpt-4o', 'GPT-4o', 'text', $text, 2.5, 10, 16384, false],
                ['gpt-4o-mini', 'GPT-4o mini', 'text', $text, 0.15, 0.6, 16384, false],
                ['gpt-4.1-nano', 'GPT-4.1 nano', 'text', $text, 0.1, 0.4, 32768, false],
                ['gpt-5', 'GPT-5', 'text', $text, 1.25, 10, 128000, false],
                ['gpt-5-mini', 'GPT-5 mini', 'text', $text, 0.25, 2, 128000, false],
                ['gpt-5-nano', 'GPT-5 nano', 'text', $text, 0.05, 0.4, 128000, false],
                ['o3', 'OpenAI o3', 'text', ['tools' => true, 'vision' => true, 'json_mode' => true], 2, 8, 100000, false],
                ['o4-mini', 'OpenAI o4-mini', 'text', ['tools' => true, 'vision' => true, 'json_mode' => true], 1.1, 4.4, 100000, false],
                ['gpt-image-1', 'GPT Image 1', 'image', ['edit' => true], null, null, null, true],
                ['gpt-image-1-mini', 'GPT Image 1 mini', 'image', ['edit' => true], null, null, null, false],
                ['dall-e-3', 'DALL·E 3', 'image', [], null, null, null, false],
                ['sora-2', 'Sora 2', 'video', [], null, null, null, false],
                ['text-embedding-3-small', 'Text Embedding 3 small', 'embedding', [], 0.02, null, null, false],
                ['text-embedding-3-large', 'Text Embedding 3 large', 'embedding', [], 0.13, null, null, false],
                ['gpt-4o-transcribe', 'GPT-4o Transcribe', 'audio', [], 2.5, 10, null, false],
                ['gpt-4o-mini-tts', 'GPT-4o mini TTS', 'audio', [], 0.6, null, null, false],
                ['whisper-1', 'Whisper', 'audio', [], null, null, null, false],
            ]],
            ['driver' => 'moonshot', 'name' => 'Moonshot (Kimi)', 'base_url' => 'https://api.moonshot.ai/v1', 'models' => [
                ['kimi-k2-0905-preview', 'Kimi K2', 'text', ['tools' => true, 'json_mode' => true, 'web_search' => true], 0.6, 2.5, 32768, false],
                ['kimi-latest', 'Kimi Latest', 'text', ['tools' => true, 'vision' => true, 'json_mode' => true], null, null, 32768, false],
                ['moonshot-v1-32k', 'Moonshot v1 32K', 'text', ['tools' => true, 'json_mode' => true], null, null, 8192, false],
            ]],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->catalog() as $p) {
            $provider = DB::table('ai_providers')->where('driver', $p['driver'])->whereNull('deleted_at')->first();
            $pid = $provider->id ?? DB::table('ai_providers')->insertGetId([
                'driver' => $p['driver'], 'name' => $p['name'], 'base_url' => $p['base_url'],
                'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($p['models'] as [$id, $label, $kind, $caps, $in, $out, $max, $default]) {
                if (DB::table('ai_models')->where('ai_provider_id', $pid)->where('model_id', $id)->exists()) {
                    continue;
                }
                // one default per kind: only claim it when nobody has
                $claim = $default && ! DB::table('ai_models')->where('kind', $kind)->where('is_default', true)->whereNull('deleted_at')->exists();
                DB::table('ai_models')->insert([
                    'ai_provider_id' => $pid, 'model_id' => $id, 'label' => $label, 'kind' => $kind,
                    'capabilities' => json_encode($caps), 'input_cost' => $in, 'output_cost' => $out, 'max_output' => $max,
                    'is_active' => true, 'is_default' => $claim, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // data seed: intentionally non-destructive (admins may have added keys / usage logs)
    }
};
