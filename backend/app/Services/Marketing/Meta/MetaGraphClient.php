<?php

namespace App\Services\Marketing\Meta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The ONLY HTTP client of the Meta Graph / Marketing API (P6, MCP.md §7; docs/marketing/META_INTEGRATION.md).
 *
 * Safety by construction — this class deliberately has exactly two ways to talk to Meta:
 *  - get(): read-only GET of a Graph path;
 *  - create(): POST to one of the ad-account CREATE edges (campaigns, adsets, adcreatives, ads, adimages) of the configured ad account.
 *    Campaigns, ad sets and ads MUST carry status=PAUSED (anything else throws before any request). No object id can be targeted, so no
 *    existing object can be updated (activate, budget change, ...) and there is no DELETE at all.
 * The access token is sent as an `Authorization: Bearer` header (never in a URL), never logged, never returned; error messages are scrubbed.
 * Every call is audited by the caller-provided MetaAudit (endpoint path only).
 */
class MetaGraphClient
{
    /** Edges (under act_{id}) that may receive a POST — creation only. */
    public const CREATE_EDGES = ['campaigns', 'adsets', 'adcreatives', 'ads', 'adimages'];

    /** Edges whose created objects must be PAUSED. */
    public const PAUSED_EDGES = ['campaigns', 'adsets', 'ads'];

    /** Parameters never accepted on create (they would bypass the PAUSED policy or touch billing / spend limits). */
    public const FORBIDDEN_PARAMS = ['effective_status', 'configured_status', 'spend_cap', 'execution_options', 'account_id', 'access_token', 'funding_source', 'adlabels'];

    public function __construct(private MetaAudit $audit) {}

    public function configured(): bool
    {
        return (string) config('meta.access_token') !== '' && $this->accountDigits() !== '';
    }

    public function pageConfigured(): bool
    {
        return (string) config('meta.page_id') !== '';
    }

    public function accountDigits(): string
    {
        return preg_replace('/\D/', '', (string) config('meta.ad_account_id')) ?? '';
    }

    public function accountId(): string
    {
        return 'act_'.$this->accountDigits();
    }

    /** GET a Graph path (e.g. "me/adaccounts", "act_1/campaigns", "123/insights"). */
    public function get(string $path, array $query = [], ?int $draftId = null): array
    {
        $this->ensureConfigured();
        $path = $this->path($path);

        return $this->send('GET', $path, $query, $draftId, 'graph_get');
    }

    /**
     * POST a NEW object under the configured ad account (act_{id}/{edge}). Throws MetaException('unsafe_request') — before any HTTP call —
     * for an unknown edge, a forbidden parameter, or a campaign / ad set / ad whose status is not exactly PAUSED.
     */
    public function create(string $edge, array $params, ?int $draftId = null): array
    {
        $this->ensureConfigured();
        if (! in_array($edge, self::CREATE_EDGES, true)) {
            throw new MetaException('unsafe_request', 'Edge not allowed: '.$edge);
        }
        if (array_intersect(array_keys($params), self::FORBIDDEN_PARAMS)) {
            throw new MetaException('unsafe_request', 'Forbidden parameter on create.');
        }
        if (in_array($edge, self::PAUSED_EDGES, true) && ($params['status'] ?? null) !== 'PAUSED') {
            throw new MetaException('unsafe_request', 'Objects must be created with status PAUSED.');
        }
        if (! in_array($edge, self::PAUSED_EDGES, true) && array_key_exists('status', $params)) {
            throw new MetaException('unsafe_request', 'Status is not accepted on this edge.');
        }
        $form = array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v, $params);

        return $this->send('POST', $this->accountId().'/'.$edge, $form, $draftId, 'graph_create');
    }

    // ------------------------------------------------------------------ internals

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new MetaException('not_configured', 'Meta API is not configured.', 503);
        }
    }

    /** Graph path: letters, digits, _, / only (no query string, no "..", no absolute URL). */
    private function path(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '' || ! preg_match('#^[A-Za-z0-9_]+(/[A-Za-z0-9_]+)*$#', $path) || strlen($path) > 150) {
            throw new MetaException('unsafe_request', 'Invalid Graph path.');
        }

        return $path;
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('meta.access_token'))->acceptJson()->timeout(max(3, (int) config('meta.timeout', 20)))
            ->baseUrl(rtrim((string) config('meta.graph_url'), '/').'/'.trim((string) config('meta.graph_version'), '/'));
    }

    private function proof(): array
    {
        $secret = (string) config('meta.app_secret');

        return $secret !== '' ? ['appsecret_proof' => hash_hmac('sha256', (string) config('meta.access_token'), $secret)] : [];
    }

    private function send(string $method, string $path, array $data, ?int $draftId, string $action): array
    {
        $data += $this->proof();
        try {
            /** @var Response $res */
            $res = $method === 'GET' ? $this->http()->get($path, $data) : $this->http()->asForm()->post($path, $data);
        } catch (ConnectionException $e) {
            $this->audit->log($draftId, $action.'_failed', null, ['error' => 'connection'], $method.' '.$path, null);
            throw new MetaException('connection_failed', 'Could not reach the Meta API.', 502);
        }
        $json = (array) $res->json();
        if ($res->failed() || isset($json['error'])) {
            $err = (array) ($json['error'] ?? []);
            $msg = $this->scrub((string) ($err['error_user_msg'] ?? $err['message'] ?? 'Meta API error'));
            $this->audit->log($draftId, $action.'_failed', null, array_filter(['code' => $err['code'] ?? null, 'subcode' => $err['error_subcode'] ?? null,
                'type' => $err['type'] ?? null, 'message' => mb_substr($msg, 0, 500), 'fbtrace_id' => $err['fbtrace_id'] ?? null]), $method.' '.$path, $res->status());
            throw new MetaException('meta_error', $msg, $res->status() >= 500 ? 502 : 422, array_filter(['meta_code' => $err['code'] ?? null, 'meta_subcode' => $err['error_subcode'] ?? null,
                'fbtrace_id' => $err['fbtrace_id'] ?? null]));
        }
        $this->audit->log($draftId, $action, null, $method === 'POST' ? ['id' => $json['id'] ?? null] : [], $method.' '.$path, $res->status());

        return $json;
    }

    /** Never let a secret travel back in an error message. */
    private function scrub(string $m): string
    {
        foreach (array_filter([(string) config('meta.access_token'), (string) config('meta.app_secret')]) as $secret) {
            $m = str_replace($secret, '[redacted]', $m);
        }

        return preg_replace('/access_token=[^&\s]+/i', 'access_token=[redacted]', $m) ?? $m;
    }
}
