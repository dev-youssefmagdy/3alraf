<?php

namespace App\Services\Whatsapp;

use App\Events\Whatsapp\SessionUpdated;
use App\Models\Tenant;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp session lifecycle + messaging, independent of authentication. The session is always derived from the owner
 * (tenant id, or null = central): callers can never pick another owner's session.
 */
class WhatsappService
{
    public function __construct(private NodeClient $node) {}

    public function session(?int $tenantId): WhatsappSession
    {
        return WhatsappSession::firstOrCreate(
            ['owner_key' => (int) $tenantId],
            ['tenant_id' => $tenantId, 'session_id' => WhatsappSession::idFor($tenantId), 'storage_ref' => 'sessions/'.WhatsappSession::idFor($tenantId)],
        );
    }

    public function connect(?int $tenantId): WhatsappSession
    {
        return $this->command($tenantId, 'connect');
    }

    public function reconnect(?int $tenantId): WhatsappSession
    {
        return $this->command($tenantId, 'reconnect');
    }

    public function disconnect(?int $tenantId): WhatsappSession
    {
        return $this->command($tenantId, 'disconnect');
    }

    /** Pull the live state from Node (used on page load; Node also pushes events). */
    public function refresh(?int $tenantId): WhatsappSession
    {
        $s = $this->session($tenantId);
        try {
            $this->apply($s, $this->node->status($s->session_id), broadcast: false);
        } catch (WhatsappUnavailable $e) {
            $this->fail($s, $e->getMessage());
        }

        return $s->refresh();
    }

    private function command(?int $tenantId, string $action): WhatsappSession
    {
        $s = $this->session($tenantId);
        if ($action !== 'disconnect' && ! AuthSettings::for($tenantId)->get('whatsapp.enabled')) {
            throw new WhatsappUnavailable('WhatsApp integration is disabled');
        }
        // one connect/reconnect at a time per owner: a double click must not open two sockets
        $lock = Cache::lock("wa-cmd:{$s->session_id}", 15);
        abort_unless($lock->get(), 429, 'Another WhatsApp operation is in progress');
        try {
            $snap = $this->node->{$action}($s->session_id);
            $this->apply($s, $snap);
            $this->log($s, 'info', $action);
        } catch (WhatsappUnavailable $e) {
            $this->fail($s, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }

        return $s->refresh();
    }

    /** Send via the owner's own account. Throws WhatsappUnavailable; the caller decides how to surface it. */
    public function sendText(?int $tenantId, string $phoneDigits, string $text): void
    {
        $s = $this->session($tenantId);
        if (! AuthSettings::for($tenantId)->get('whatsapp.enabled') || ! $s->is_enabled) {
            throw new WhatsappUnavailable('WhatsApp integration is disabled');
        }
        if (! $s->isConnected()) {
            throw new WhatsappUnavailable('WhatsApp is not connected');
        }
        try {
            $this->node->sendText($s->session_id, $phoneDigits, $text);
        } catch (WhatsappUnavailable $e) {
            $this->log($s, 'error', 'send_failed', $e->getMessage()); // never the message body
            throw $e;
        }
    }

    /** Event pushed by Node (already HMAC-verified). Unknown owners are ignored. */
    public function applyEvent(string $sessionId, array $data): ?WhatsappSession
    {
        if (! preg_match('/^(central|tenant-([1-9][0-9]{0,9}))$/', $sessionId, $m)) {
            return null;
        }
        $tenantId = isset($m[2]) ? (int) $m[2] : null;
        if ($tenantId && ! Tenant::whereKey($tenantId)->exists()) {
            return null;
        }
        $this->apply($this->session($tenantId), $data);

        return $this->session($tenantId);
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
        if ($status !== 'qr' && $was !== $status) {
            $this->log($s, in_array($status, ['error', 'logged_out'], true) ? 'error' : 'info', $status, $s->last_error);
        }
        if ($broadcast) {
            SessionUpdated::dispatch($s, $d['qr'] ?? null);
        }
    }

    private function fail(WhatsappSession $s, string $message): void
    {
        $s->update(['status' => 'error', 'last_error' => mb_substr($message, 0, 500), 'last_event_at' => now()]);
        $this->log($s, 'error', 'service_unreachable', $message);
    }

    private function log(WhatsappSession $s, string $level, string $event, ?string $message = null): void
    {
        $s->logs()->create(['level' => $level, 'event' => $event, 'message' => $message ? mb_substr($message, 0, 500) : null]);
    }
}
