<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;

interface AiDriver
{
    /** One non-streaming model turn (may return tool calls). */
    public function chat(AiRequest $request): AiResponse;

    /** Text deltas; returns the final AiResponse. */
    public function stream(AiRequest $request, callable $onDelta): AiResponse;

    /** @return AiResponse with media = [['b64' => ..] | ['url' => ..]] */
    public function image(string $model, string $prompt, array $options = []): AiResponse;

    /** @throws \App\Exceptions\AiException unsupported when the vendor has no API. */
    public function video(string $model, string $prompt, array $options = []): AiResponse;

    /** cheap connectivity/credential probe */
    public function test(): bool;

    public function supports(string $capability): bool;
}
