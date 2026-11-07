<?php

namespace App\Services\Whatsapp;

use App\Events\Whatsapp\SessionUpdated;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp session lifecycle + messaging, independent of authentication. The shop has exactly one session
 * (WhatsappSession::SESSION_ID); callers never pick a session id themselves.
 */
class WhatsappService
{
    public function __construct(private NodeClient $node) {}

    /** The single session. */
    public function session(): WhatsappSession
    {
        return WhatsappSession::current();
    }

    public function connect(): WhatsappSession
    {
        return $this->command('connect');
    }

    public function reconnect(): WhatsappSession
    {
        return $this->command('reconnect');
    }

    public function disconnect(): WhatsappSession
    {
        return $this->command('disconnect');
    }

    /** Pull the live state from Node (used on page load; Node also pushes events). */
    public function refresh(): WhatsappSession
    {
        $s = $this->session();
        try {
            $this->apply($s, $this->node->status($s->session_id), broadcast: false);
        } catch (WhatsappUnavailable $e) {
            $this->fail($s, $e->getMessage());
        }

        return $s->refresh();
    }

    public function qr(WhatsappSession $s): ?string
    {
        return $s->status === 'qr' ? Cache::get("wa-qr:{$s->session_id}") : null;
    }

    private function command(string $action): WhatsappSession
    {
        $s = $this->session();
        if ($action !== 'disconnect' && ! AuthSettings::for()->get('whatsapp.enabled')) {
            throw new WhatsappUnavailable('WhatsApp integration is disabled');
        }
        // one connect/reconnect at a time: a double click must not open two sockets
        $lock = Cache::lock("wa-cmd:{$s->session_id}", 15);
        abort_unless($lock->get(), 429, 'Another WhatsApp operation is in progress');
        try {
            $snap = $this->node->{$action}($s->session_id);
            $this->apply($s, $snap);
        } catch (WhatsappUnavailable $e) {
            $this->fail($s, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }

        return $s->refresh();
    }

    /**
     * Send via the shop's account. Throws WhatsappUnavailable; the caller decides how to surface it.
     * $throttled = notification traffic: hourly/daily limits are enforced and the gateway paces it with typing + random gaps.
     * Login codes stay instant and unlimited so an admin can never be locked out by the quota.
     */
    public function sendText(string $phoneDigits, string $text, bool $throttled = false): void
    {
        $s = $this->session();
        if (! AuthSettings::for()->get('whatsapp.enabled') || ! $s->is_enabled) {
            throw new WhatsappUnavailable('WhatsApp integration is disabled');
        }
        if (! $s->isConnected()) {
            throw new WhatsappUnavailable('WhatsApp is not connected');
        }
        $set = AuthSettings::for();
        if ($throttled) {
            foreach (['daily' => $this->sent($s, 'daily'), 'hourly' => $this->sent($s, 'hourly')] as $window => $sent) {
                $limit = (int) $set->get("whatsapp.{$window}_limit");
                if ($limit > 0 && $sent >= $limit) {
                    throw new WhatsappUnavailable("WhatsApp $window sending limit reached");
                }
            }
        }
        $min = max(0, (int) $set->get('whatsapp.min_gap'));
        $this->node->sendText($s->session_id, $phoneDigits, $text, $throttled
            ? ['humanize' => true, 'min_gap_ms' => $min * 1000, 'max_gap_ms' => max($min, (int) $set->get('whatsapp.max_gap')) * 1000]
            : []);
        foreach (['daily', 'hourly'] as $w) {
            $key = $this->counterKey($s, $w);
            Cache::add($key, 0, $w === 'daily' ? 86400 : 3600);
            Cache::increment($key);
        }
    }

    /** Messages sent in the current calendar day / clock hour (cache counters; no message data is stored). */
    public function sent(WhatsappSession $s, string $window): int
    {
        return (int) Cache::get($this->counterKey($s, $window), 0);
    }

    private function counterKey(WhatsappSession $s, string $window): string
    {
        return "wa-sent:{$s->session_id}:".now()->format($window === 'daily' ? 'Ymd' : 'YmdH');
    }

    /** Whether a Node session id is the shop's session. Never read from payload fields; unknown/forged ids are ignored. */
    public function isOwnSession(string $sessionId): bool
    {
        return $sessionId === WhatsappSession::id();
    }

    /** Status/qr event pushed by Node (already HMAC-verified). Events of other session ids are ignored. */
    public function applyEvent(string $sessionId, array $data): ?WhatsappSession
    {
        if (! $this->isOwnSession($sessionId)) {
            return null;
        }
        $this->apply($this->session(), $data);

        return $this->session();
    }

    private function apply(WhatsappSession $s, array $d, bool $broadcast = true): void
    {
        $status = in_array($d['status'] ?? '', ['disconnected', 'connecting', 'qr', 'connected', 'logged_out', 'error'], true) ? $d['status'] : 'error';
        $was = $s->status;
        $s->fill([
            'status' => $status, 'phone' => $status === 'connected' ? ($d['phone'] ?? $s->phone) : null,
            'last_error' => isset($d['error']) ? mb_substr((string) $d['error'], 0, 500) : null, 'last_event_at' => now(),
        ]);
        if ($status === 'connected') {
            $s->last_connected_at = $was === 'connected' ? $s->last_connected_at : now();
        }
        $s->save();
        // The QR is a short-lived pairing secret: cached briefly (never in the DB) so the polling endpoint works without a websocket.
        $status === 'qr' && ! empty($d['qr']) ? Cache::put("wa-qr:{$s->session_id}", $d['qr'], 120) : Cache::forget("wa-qr:{$s->session_id}");
        if ($broadcast) {
            SessionUpdated::dispatch($s, $d['qr'] ?? null);
        }
    }

    private function fail(WhatsappSession $s, string $message): void
    {
        $s->update(['status' => 'error', 'last_error' => mb_substr($message, 0, 500), 'last_event_at' => now()]);
    }
}
