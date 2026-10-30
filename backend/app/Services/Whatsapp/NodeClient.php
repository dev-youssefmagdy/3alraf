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
    private function call(string $method, string $path, array $body = []): array
    {
        try {
            $r = $this->http()->send($method, $path, $body ? ['json' => $body] : []);
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

    public function sendText(string $id, string $to, string $text): array
    {
        return $this->call('POST', "/sessions/$id/messages", ['to' => $to, 'text' => $text]);
    }
}
