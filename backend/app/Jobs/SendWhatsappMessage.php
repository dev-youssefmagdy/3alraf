<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\Whatsapp\Chat\ChatSendService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Sends one pending chat message. Serialized per owner so a burst keeps its order and Node's per-session chain is not flooded.
 * Not retried on Node errors (a timeout may mean it was delivered): the row turns `failed` and the UI offers resend.
 */
class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 0; // lock contention releases the job; the deadline below bounds it

    public int $maxExceptions = 1;

    public function __construct(public ?int $tenantId, public int $messageId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('wa-chat-send:'.($this->tenantId ?? 'central')))->releaseAfter(3)->expireAfter(300)];
    }

    public function handle(ChatSendService $svc): void
    {
        $svc->deliver($this->tenantId, $this->messageId);
    }

    public function failed(\Throwable $e): void
    {
        if ($msg = WhatsappMessage::ownedBy($this->tenantId)->find($this->messageId)) {
            app(ChatSendService::class)->fail($this->tenantId, $msg, 'send_failed');
        }
    }
}
