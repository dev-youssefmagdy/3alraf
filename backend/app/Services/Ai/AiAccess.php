<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiSetting;

/** Who may use AI: master switch + feature toggle + allowed models (one `ai_settings` row). Cached per request. */
class AiAccess
{
    private bool $loaded = false;

    private ?AiSetting $row = null;

    public function settings(): ?AiSetting
    {
        if (! $this->loaded) {
            $this->row = AiSetting::query()->first();
            $this->loaded = true;
        }

        return $this->row;
    }

    public function forget(): void
    {
        $this->loaded = false;
        $this->row = null;
    }

    public function enabled(): bool
    {
        return (bool) $this->settings()?->ai_enabled;
    }

    public function feature(string $feature): bool
    {
        return (bool) $this->settings()?->featureOn($feature);
    }

    /** Active models of a kind the shop may use (allowed list of the AI settings; null = all). */
    public function modelsFor(string $kind = 'text')
    {
        $s = $this->settings();
        $q = AiModel::active()->with('provider')->where('kind', $kind)->orderBy('ai_provider_id')->orderBy('label');
        if ($s && $s->allowed_model_ids !== null) {
            $q->whereIn('id', $s->allowed_model_ids);
        }

        return $q->get();
    }
}
