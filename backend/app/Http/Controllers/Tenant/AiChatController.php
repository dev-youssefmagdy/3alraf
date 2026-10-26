<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Ai\ChatMessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiChatMessageRequest;
use App\Http\Requests\Tenant\AiConversationRequest;
use App\Services\Ai\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller implements HasMiddleware
{
    public function __construct(private ChatService $chat) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_chat.use')];
    }

    private function userId(): int
    {
        return (int) auth('tenant')->id();
    }

    public function index()
    {
        return view('tenant.ai.chat', [
            'models' => $this->chat->models(), 'current' => $this->chat->resolveModel(null), 'quota' => $this->chat->quota(),
            'conversations' => $this->chat->conversations($this->userId())->map(fn ($c) => ['id' => $c->id, 'title' => $c->title ?: __('ai.chat.new_chat')])->all(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $c = $this->chat->find($this->userId(), $id) ?? abort(404);

        return response()->json(['success' => true, 'data' => [
            'id' => $c->id, 'title' => $c->title, 'ai_model_id' => $c->ai_model_id,
            'messages' => $c->messages()->orderBy('id')->get()->map(fn ($m) => ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'status' => $m->status, 'tools' => $m->tool_calls ?: []])->all(),
        ]]);
    }

    /** SSE: events meta | tool | delta | done | error, each `event: x\ndata: {json}`. */
    public function stream(AiChatMessageRequest $request): StreamedResponse
    {
        $conv = $request->filled('conversation_id') ? ($this->chat->find($this->userId(), (int) $request->input('conversation_id')) ?? abort(404)) : null;
        $data = ChatMessageData::fromRequest($request);
        $conv = $this->chat->start($this->userId(), $conv, $data);
        session()->save();

        return response()->stream(function () use ($conv, $data) {
            set_time_limit(150);
            $emit = function (string $event, array $payload) {
                echo "event: {$event}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            $this->chat->respond($conv, $data, $emit);
        }, 200, ['Content-Type' => 'text/event-stream; charset=utf-8', 'Cache-Control' => 'no-cache, no-transform', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    public function rename(AiConversationRequest $request, int $id): JsonResponse
    {
        $this->chat->rename($this->chat->find($this->userId(), $id) ?? abort(404), $request->validated('title'));

        return $this->ok(__('admin.saved'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->chat->delete($this->chat->find($this->userId(), $id) ?? abort(404));

        return $this->ok(__('admin.deleted'));
    }
}
