<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Thin REST client for the Node WhatsApp service. Knows session ids only; carries no business or tenant logic. */
class NodeClient
{
    private function http(): PendingRequest
    {
        $cfg = config('services.whatsapp');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            throw new WhatsappUnavailable('WhatsApp service is not configured (WHATSAPP_SERVICE_URL / WHATSAPP_SERVICE_TOKEN)');
        }

        return Http::baseUrl(rtrim($cfg['url'], '/'))->withToken($cfg['token'])->acceptJson()->timeout(15)->connectTimeout(3);
    }

    /** @return array<string,mixed> */
    private function call(string $method, string $path, array $body = [], int $timeout = 15): array
    {
        try {
            $r = $this->http()->timeout($timeout)->send($method, $path, $body ? ['json' => $body] : []);
        } catch (ConnectionException) {
            throw new WhatsappUnavailable('WhatsApp service is unreachable');
        }
        if ($r->failed()) {
            throw new WhatsappUnavailable($r->json('error') ?: 'WhatsApp service error ('.$r->status().')');
        }

        return $r->json() ?? [];
    }

    public function status(string $id): array
    {
        return $this->call('GET', "/sessions/$id");
    }

    public function connect(string $id): array
    {
        return $this->call('POST', "/sessions/$id/connect");
    }

    public function reconnect(string $id): array
    {
        return $this->call('POST', "/sessions/$id/reconnect");
    }

    public function disconnect(string $id): array
    {
        return $this->call('POST', "/sessions/$id/disconnect");
    }

    /** @param array{humanize?:bool,min_gap_ms?:int,max_gap_ms?:int} $opts */
    public function sendText(string $id, string $to, string $text, array $opts = []): array
    {
        return $this->call('POST', "/sessions/$id/messages", ['to' => $to, 'text' => $text] + $opts, timeout: 90);
    }

    /** Inbound media hand-off: Laravel pulls the bytes with the one-time token, streamed to a temp file (never held in memory; caller must unlink `path`). Null = token unknown/expired. @return array{path:string,size:int,mime:?string,sha256:?string}|null */
    public function pullMedia(string $id, string $token): ?array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wamedia');
        try {
            $r = $this->http()->timeout(120)->sink($tmp)->get("/sessions/$id/media/$token");
        } catch (ConnectionException) {
            @unlink($tmp);
            throw new WhatsappUnavailable('WhatsApp service is unreachable');
        }
        if ($r->status() === 404 || $r->failed()) {
            @unlink($tmp);
            if ($r->status() === 404) {
                return null;
            }
            throw new WhatsappUnavailable('WhatsApp service error ('.$r->status().')');
        }

        return ['path' => $tmp, 'size' => (int) filesize($tmp), 'mime' => $r->header('Content-Type') ?: null, 'sha256' => $r->header('x-content-sha256') ?: null];
    }

    /** Free the Node spool entry after a successful pull (best effort: the token also expires by TTL). */
    public function deleteMedia(string $id, string $token): void
    {
        try {
            $this->http()->timeout(10)->delete("/sessions/$id/media/$token");
        } catch (\Throwable) {
        }
    }

    /** Typed chat send (see Node schema). @return array{message_id:?string,to_jid:?string,timestamp:?int} */
    public function sendTyped(string $id, array $payload): array
    {
        return $this->call('POST', "/sessions/$id/messages", $payload, timeout: 120);
    }

    /** Push outbound media bytes; returns the upload id to reference in a typed send. @return array{upload_id:string,size:int,sha256:?string,mime:?string} */
    public function upload(string $id, string $bytes, string $mime, string $name): array
    {
        try {
            $r = $this->http()->timeout(180)->withBody($bytes, $mime)->post("/sessions/$id/uploads?name=".rawurlencode($name));
        } catch (ConnectionException) {
            throw new WhatsappUnavailable('WhatsApp service is unreachable');
        }
        if ($r->failed() || ! $r->json('upload_id')) {
            throw new WhatsappUnavailable($r->json('error') ?: 'WhatsApp service error ('.$r->status().')');
        }

        return $r->json();
    }

    /** @param array<int,array{id:string,participant?:string}> $keys */
    public function markRead(string $id, string $jid, array $keys): array
    {
        return $this->call('POST', "/sessions/$id/read", ['jid' => $jid, 'keys' => $keys], timeout: 15);
    }

    public function presence(string $id, string $jid, string $state): array
    {
        return $this->call('POST', "/sessions/$id/presence", ['jid' => $jid, 'state' => $state], timeout: 10);
    }
}
