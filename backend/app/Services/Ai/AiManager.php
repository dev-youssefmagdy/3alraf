<?php

namespace App\Services\Ai;

use App\Enums\AiDriverType;
use App\Exceptions\AiException;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\MoonshotDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\OpenRouterDriver;

class AiManager
{
    public function forProvider(AiProvider $p): AiDriver
    {
        return match ($p->driver) {
            AiDriverType::ANTHROPIC => new AnthropicDriver($p),
            AiDriverType::OPENAI, AiDriverType::CUSTOM_OPENAI => new OpenAiDriver($p),
            AiDriverType::OPENROUTER => new OpenRouterDriver($p),
            AiDriverType::MOONSHOT => new MoonshotDriver($p),
        };
    }

    public function for(AiModel $m): AiDriver
    {
        $p = $m->provider;
        if (! $p || ! $p->is_active || ! $m->is_active) {
            throw new AiException(__('ai.errors.model_unavailable'), 'model_unavailable');
        }

        return $this->forProvider($p);
    }
}
