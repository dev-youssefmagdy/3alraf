<?php

namespace App\Services\Llm;

/** One FreeLLMAPI answer: the text, the model entry of our list that answered, and where the gateway routed it (X-Routed-Via). */
class LlmResponse
{
    /** @param  array<string,string>  $skipped  models tried before this one => why they failed */
    public function __construct(public readonly string $content, public readonly string $model, public readonly ?string $routedVia = null, public readonly array $skipped = []) {}

    /** "groq/llama-3.3-70b" when the gateway said so, else our model name. */
    public function answeredBy(): string
    {
        return $this->routedVia ?: $this->model;
    }
}
