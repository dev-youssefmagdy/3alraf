<?php

namespace App\Services\Ai\Drivers;

use App\Exceptions\AiException;

/** Kimi / Moonshot: OpenAI-compatible chat + tool calling; no image/video. */
class MoonshotDriver extends OpenAiDriver
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.moonshot.ai/v1';
    }

    public function image(string $model, string $prompt, array $options = []): \App\Services\Ai\Support\AiResponse
    {
        throw AiException::unsupported();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['chat', 'tools'], true);
    }
}
