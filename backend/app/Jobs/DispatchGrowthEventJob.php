<?php

namespace App\Jobs;

use App\Models\GrowthEvent;
use App\Models\IntegrationClient;
use App\Services\Growth\SignatureService;
use App\Services\Growth\UrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Signed POST of one outbox event to every webhook client. Own retry schedule (exponential) -> `dead` after max attempts; replay from admin. */
class DispatchGrowthEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $eventId) {}

    public function handle(SignatureService $sig): void
    {
        // claim: only a pending, due event; bump attempts under lock so concurrent workers cannot double-send
        $e = DB::transaction(function () {
            $e = GrowthEvent::withoutGlobalScopes()->lockForUpdate()->find($this->eventId);
            if (! $e || $e->status !== 'pending' || ($e->next_attempt_at && $e->next_attempt_at->isFuture())) {
                return null;
            }
            $e->attempts++;
            $e->next_attempt_at = now()->addMinutes(10); // lease: a crashed worker is retried by the scheduler later
            $e->save();

            return $e;
        });
        if (! $e) {
            return;
        }

        $clients = IntegrationClient::withoutGlobalScopes()->where('tenant_id', $e->tenant_id)->whereNull('revoked_at')->whereNotNull('webhook_url')->get();
        $done = (array) ($e->delivered_to ?? []);
        $body = json_encode($e->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $errors = [];
        foreach ($clients->reject(fn ($c) => in_array($c->id, $done, true)) as $c) {
            if (! UrlGuard::isSafe($c->webhook_url)) {
                $errors[] = "client {$c->id}: unsafe url";

                continue;
            }
            $ts = time();
            try {
                $res = Http::withBody($body, 'application/json')->withHeaders([
                    'X-Event-Id' => $e->event_id, 'X-Event-Type' => $e->type, 'X-Timestamp' => (string) $ts, 'X-Signature' => $sig->sign($c->hmac_secret, $ts, $body),
                ])->timeout((int) config('growth.events.timeout_seconds'))->connectTimeout(5)->post($c->webhook_url);
                $res->successful() ? $done[] = $c->id : $errors[] = "client {$c->id}: HTTP ".$res->status();
            } catch (\Throwable $ex) {
                $errors[] = "client {$c->id}: ".class_basename($ex);
            }
        }

        if (! $errors) {
            $e->update(['status' => 'delivered', 'delivered_to' => $done, 'delivered_at' => now(), 'last_error' => $clients->isEmpty() ? 'no_targets' : null, 'next_attempt_at' => null]);

            return;
        }
        $max = (int) config('growth.events.max_attempts');
        $back = (array) config('growth.events.backoff_seconds');
        $dead = $e->attempts >= $max;
        $e->update(['delivered_to' => $done, 'last_error' => mb_substr(implode('; ', $errors), 0, 300), 'status' => $dead ? 'dead' : 'pending',
            'next_attempt_at' => $dead ? null : now()->addSeconds((int) ($back[min($e->attempts - 1, count($back) - 1)] ?? 3600))]);
    }
}
