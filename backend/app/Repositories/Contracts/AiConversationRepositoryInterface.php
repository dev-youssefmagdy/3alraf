<?php

namespace App\Repositories\Contracts;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Collection;

/** Conversations are private to their user (and tenant-scoped by the model). */
interface AiConversationRepositoryInterface
{
    public function listFor(int $userId, int $limit = 50): Collection;

    public function findFor(int $userId, int $id): ?AiConversation;

    public function create(int $userId, ?int $modelId): AiConversation;

    public function rename(AiConversation $c, string $title): void;

    public function delete(AiConversation $c): void;

    public function addMessage(AiConversation $c, string $role, string $content, array $extra = []): AiMessage;

    /** Messages after the summarised prefix, oldest first. */
    public function messagesAfter(AiConversation $c, int $afterId): Collection;

    public function messageCount(AiConversation $c): int;
}
