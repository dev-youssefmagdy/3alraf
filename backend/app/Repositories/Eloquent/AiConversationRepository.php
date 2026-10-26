<?php

namespace App\Repositories\Eloquent;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Repositories\Contracts\AiConversationRepositoryInterface;
use Illuminate\Support\Collection;

class AiConversationRepository implements AiConversationRepositoryInterface
{
    public function listFor(int $userId, int $limit = 50): Collection
    {
        return AiConversation::query()->where('user_id', $userId)->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function findFor(int $userId, int $id): ?AiConversation
    {
        return AiConversation::query()->where('user_id', $userId)->find($id);
    }

    public function create(int $userId, ?int $modelId): AiConversation
    {
        return AiConversation::create(['user_id' => $userId, 'ai_model_id' => $modelId]);
    }

    public function rename(AiConversation $c, string $title): void
    {
        $c->update(['title' => $title]);
    }

    public function delete(AiConversation $c): void
    {
        $c->messages()->delete();
        $c->delete();
    }

    public function addMessage(AiConversation $c, string $role, string $content, array $extra = []): AiMessage
    {
        $m = $c->messages()->create(['role' => $role, 'content' => $content] + $extra);
        $c->touch();

        return $m;
    }

    public function messagesAfter(AiConversation $c, int $afterId): Collection
    {
        return $c->messages()->where('id', '>', $afterId)->whereIn('role', ['user', 'assistant'])->orderBy('id')->get();
    }

    public function messageCount(AiConversation $c): int
    {
        return $c->messages()->count();
    }
}
