<?php

namespace App\Services\Ai\Support;

/** @property array<int,array{id:string,name:string,arguments:array}> $toolCalls */
final class AiResponse
{
    public function __construct(
        public string $text = '',
        public array $toolCalls = [],
        public int $tokensIn = 0,
        public int $tokensOut = 0,
        public ?string $stopReason = null,
        public array $media = [],
        public int $images = 0,
        public int $videoSecs = 0,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
