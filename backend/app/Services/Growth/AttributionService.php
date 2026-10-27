<?php

namespace App\Services\Growth;

use Illuminate\Http\Request;

/**
 * First-touch attribution (docs/CALCULATIONS.md "Growth system definitions"). Captured once per browser session from utm_*, ref, gclid/fbclid presence
 * or an external referrer host, then copied to orders.attribution / orders.channel at placement. Stores no IP, no user agent, no click ids (presence only).
 */
class AttributionService
{
    public const SESSION_KEY = 'growth_attr';

    public const CHANNELS = ['direct', 'affiliate', 'paid', 'social', 'search', 'email', 'referral', 'other'];

    private const SOCIAL = ['facebook', 'fb', 'instagram', 'ig', 'tiktok', 'snapchat', 'twitter', 'x', 'youtube', 'whatsapp', 'pinterest', 'linkedin', 'telegram'];

    private const SEARCH = ['google', 'bing', 'yahoo', 'duckduckgo'];

    private const BOTS = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|curl|wget|python-requests|lighthouse/i';

    public static function isBot(?string $ua): bool
    {
        return $ua === null || $ua === '' || (bool) preg_match(self::BOTS, $ua);
    }

    /** Touch data present on this request, or null when the request carries none. */
    public static function fromRequest(Request $r): ?array
    {
        $t = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'ref'] as $k) {
            $v = trim((string) $r->query($k, ''));
            if ($v !== '') {
                $t[$k] = mb_substr(preg_replace('/[^\w\-\.\s]/u', '', $v), 0, 80);
            }
        }
        foreach (['gclid', 'fbclid'] as $k) {
            if ($r->query($k) !== null && $r->query($k) !== '') {
                $t[$k] = true; // presence only
            }
        }
        if (! $t) {
            $host = parse_url((string) $r->headers->get('referer'), PHP_URL_HOST);
            if ($host && ! str_contains($host, preg_replace('/^www\./', '', $r->getHost()))) {
                $t['referrer_host'] = mb_substr($host, 0, 80);
            }
        }

        return $t ?: null;
    }

    /**
     * Mobile `X-Attribution`: base64url JSON {utm_source,utm_medium,utm_campaign,utm_content,ref,link,gift_session,ab}. Whitelisted keys, size-capped,
     * values sanitised like web UTMs; anything malformed is ignored (never blocks the request).
     *
     * @return array{attr:array,gift_session?:int,bundle_ab?:string}|null
     */
    public static function fromHeader(string $h): ?array
    {
        if ($h === '' || strlen($h) > 1024) {
            return null;
        }
        $j = base64_decode(strtr($h, '-_', '+/'), true);
        $d = $j === false ? null : json_decode($j, true);
        if (! is_array($d)) {
            return null;
        }
        $t = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'ref', 'link'] as $k) {
            $v = is_scalar($d[$k] ?? null) ? trim((string) $d[$k]) : '';
            $v = mb_substr(preg_replace('/[^\w\-\.\s]/u', '', $v), 0, 80);
            if ($v !== '') {
                $t[$k] = $v;
            }
        }
        $out = [];
        if (isset($d['gift_session']) && is_numeric($d['gift_session']) && (int) $d['gift_session'] > 0) {
            $out['gift_session'] = (int) $d['gift_session'];
        }
        if (isset($d['ab']) && is_string($d['ab']) && preg_match('/^[\w\-:\.]{1,40}$/', $d['ab'])) {
            $out['bundle_ab'] = $d['ab'];
        }
        if (! $t && ! $out) {
            return null;
        }

        return ['attr' => $t ? $t + ['channel' => self::normalise($t), 'at' => now()->toDateString()] : [], ...$out];
    }

    public static function capture(Request $r): void
    {
        if (! $r->hasSession() || $r->session()->has(self::SESSION_KEY)) {
            return; // first touch wins
        }
        $t = self::fromRequest($r);
        if ($t) {
            $r->session()->put(self::SESSION_KEY, $t + ['channel' => self::normalise($t), 'at' => now()->toDateString()]);
        }
    }

    /** @return array{channel:string,attribution:?array} for a new order; an affiliate always wins the channel. */
    public static function forOrder(?int $affiliateId): array
    {
        $a = null;
        $extra = [];
        if (app()->bound('request')) {
            $sess = request()->hasSession() ? request()->session() : session(); // the mobile API keeps its per-call session in the session store only
            $a = $sess->get(self::SESSION_KEY);
            // Growth carry-over ids (gift finder session, bundle A/B variant): ids only, no personal data.
            if ($g = $sess->get('gift_session_id')) {
                $extra['gift_session'] = (int) $g;
            }
            if ($ab = $sess->get('bundle_ab')) {
                $extra['bundle_ab'] = $ab;
            }
        }
        $attr = $extra ? (($a ?? []) + $extra) : $a;
        if ($affiliateId) {
            return ['channel' => 'affiliate', 'attribution' => $attr];
        }

        return ['channel' => $a['channel'] ?? 'direct', 'attribution' => $attr];
    }

    public static function currentChannel(Request $r): string
    {
        return $r->hasSession() ? ($r->session()->get(self::SESSION_KEY)['channel'] ?? 'direct') : 'direct';
    }

    public static function normalise(array $t): string
    {
        $src = strtolower((string) ($t['utm_source'] ?? ''));
        $med = strtolower((string) ($t['utm_medium'] ?? ''));
        if ($src === 'affiliate' || isset($t['ref']) && ! $src) {
            return $src === 'affiliate' ? 'affiliate' : 'referral';
        }
        if (isset($t['gclid']) || isset($t['fbclid']) || in_array($med, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'display', 'ads'], true)) {
            return 'paid';
        }
        if (in_array($med, ['email', 'newsletter'], true) || in_array($src, ['email', 'newsletter', 'mailchimp'], true)) {
            return 'email';
        }
        if (in_array($src, self::SOCIAL, true) || in_array($med, ['social', 'social-network', 'social_media'], true)) {
            return 'social';
        }
        if (in_array($src, self::SEARCH, true) || $med === 'organic') {
            return 'search';
        }
        if ($src !== '' || $med !== '') {
            return 'other';
        }
        $host = strtolower((string) ($t['referrer_host'] ?? ''));
        if ($host !== '') {
            foreach (self::SEARCH as $s) {
                if (str_contains($host, $s)) {
                    return 'search';
                }
            }
            foreach (self::SOCIAL as $s) {
                if (strlen($s) > 2 && str_contains($host, $s)) {
                    return 'social';
                }
            }

            return 'referral';
        }

        return 'direct';
    }
}
