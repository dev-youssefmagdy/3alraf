<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiChatMessageRequest;

final readonly class ChatMessageData
{
    public function __construct(public string $message, public ?int $modelId) {}

    public static function fromRequest(AiChatMessageRequest $r): self
    {
        $v = $r->validated();

        return new self(trim($v['message']), filled($v['ai_model_id'] ?? null) ? (int) $v['ai_model_id'] : null);
    }
}
