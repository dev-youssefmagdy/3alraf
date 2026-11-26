<?php

namespace App\Support\Http;

use Closure;
use RuntimeException;

/**
 * Fetches a web page (a marketplace listing) from an untrusted URL with SafeImageFetcher's SSRF rules: http/https on default ports only,
 * public IPs only (pinned against DNS rebinding), redirects re-checked, body capped (config product_ai.pages: 2 MB, 8 s).
 * Returns the HTML; html → text helpers live here too.
 */
class SafePageFetcher
{
    private SafeImageFetcher $fetcher;

    /** @param  (Closure(string): list<string>)|null  $resolver  host => IPs (tests); default = system DNS */
    public function __construct(?Closure $resolver = null, ?int $timeout = null)
    {
        $this->fetcher = new SafeImageFetcher($resolver, $timeout ?? (int) config('product_ai.pages.timeout', 8));
    }

    /** @throws RuntimeException with a short reason (bad_url, blocked_address, http_404, too_large, not_html, …) */
    public function fetch(string $url): string
    {
        $body = $this->fetcher->fetch($url, (int) config('product_ai.pages.max_bytes', 2 * 1024 * 1024), [
            'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
            'Accept-Language' => 'ar-EG,ar;q=0.9,en;q=0.8',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
        ]);
        // a binary file (image, PDF, archive) is not a listing page
        if (str_contains(substr($body, 0, 2048), "\0") || str_starts_with($body, '%PDF')) {
            throw new RuntimeException('not_html');
        }

        return $body;
    }

    /** Visible text of an HTML page: scripts, styles and tags removed, entities decoded, whitespace collapsed, capped at $max characters. */
    public static function text(string $html, int $max = 20000): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|template|iframe)\b[^>]*>.*?</\1>#is', ' ', $html) ?? '';
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? '';
        $html = preg_replace('#<(br|p|div|li|tr|td|th|h[1-6]|span)\b#i', ' <$1', $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $max);
    }

    /** The page <title> (or og:title), as plain text. */
    public static function title(string $html): ?string
    {
        if (preg_match('#<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)#i', $html, $m)
            || preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

            return $t !== '' ? mb_substr($t, 0, 300) : null;
        }

        return null;
    }
}
