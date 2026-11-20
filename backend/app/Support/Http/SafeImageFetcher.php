<?php

namespace App\Support\Http;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads an image from a URL that came from untrusted input (an MCP suggestion), without letting it reach the internal network (SSRF):
 * - http / https only, default ports only, no credentials in the URL;
 * - every address the host resolves to must be public (private, loopback, link-local, CGNAT, reserved and IPv4-mapped ranges are refused);
 * - the connection is pinned to the checked address (CURLOPT_RESOLVE), so a second DNS answer cannot swap in an internal one (rebinding);
 * - redirects are followed by hand (max 3), each hop checked again;
 * - the body is capped at $maxBytes (Content-Length and while downloading).
 * The caller still validates the bytes as an image (ImageOptimizer::inspect).
 */
class SafeImageFetcher
{
    private const MAX_REDIRECTS = 3;

    /** Extra ranges FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE do not cover. */
    private const BLOCKED_CIDRS = ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];

    /** @param  (Closure(string): list<string>)|null  $resolver  host => IPs (tests); default = system DNS */
    public function __construct(private ?Closure $resolver = null, private int $timeout = 20) {}

    /**
     * @param  array<string,string>  $headers  extra / overriding request headers (e.g. a browser User-Agent and Referer for hotlink-protected shops)
     * @return string the downloaded bytes
     *
     * @throws RuntimeException with a short, safe reason (shown to the suggestion author)
     */
    public function fetch(string $url, int $maxBytes, array $headers = []): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->check($url);
            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["$host:$port:".(str_contains($ip, ':') ? "[$ip]" : $ip)]],
                    'progress' => function ($total, $downloaded) use ($maxBytes) {
                        if ($total > $maxBytes || $downloaded > $maxBytes) {
                            throw new RuntimeException('too_large');
                        }
                    },
                ])->withHeaders([...['Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8', 'User-Agent' => 'StoreImageFetcher/1.0'], ...$headers])
                    ->timeout($this->timeout)->connectTimeout(8)->get($url);
            } catch (ConnectionException $e) {
                throw new RuntimeException(str_contains($e->getMessage(), 'too_large') ? 'too_large' : 'unreachable');
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new RuntimeException(str_contains($e->getMessage(), 'too_large') ? 'too_large' : 'unreachable');
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                if ($location === '') {
                    throw new RuntimeException('bad_redirect');
                }
                $url = $this->absolute($location, $url);

                continue;
            }
            if (! $response->successful()) {
                throw new RuntimeException('http_'.$response->status());
            }
            if ((int) $response->header('Content-Length') > $maxBytes) {
                throw new RuntimeException('too_large');
            }
            $body = $response->body();
            if ($body === '') {
                throw new RuntimeException('empty');
            }
            if (strlen($body) > $maxBytes) {
                throw new RuntimeException('too_large');
            }

            return $body;
        }

        throw new RuntimeException('too_many_redirects');
    }

    /** @return array{0:string,1:int,2:string} host, port, the checked public IP to pin */
    private function check(string $url): array
    {
        $p = parse_url($url);
        $scheme = strtolower((string) ($p['scheme'] ?? ''));
        $host = strtolower(trim((string) ($p['host'] ?? ''), '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($p['user']) || isset($p['pass'])) {
            throw new RuntimeException('bad_url');
        }
        $default = $scheme === 'https' ? 443 : 80;
        $port = (int) ($p['port'] ?? $default);
        if ($port !== $default) {
            throw new RuntimeException('bad_port');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new RuntimeException('dns');
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new RuntimeException('blocked_address');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return array_values(($this->resolver)($host));
        }
        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach ((array) @dns_get_record($host, $type) as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }

    public static function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            $lower = strtolower($ip);
            // IPv4-mapped / -compatible / NAT64 / 6to4 / Teredo / ULA / link-local / documentation: never fetch through them
            foreach (['::ffff:', '::', '64:ff9b:', '2002:', '2001:0:', '2001::', 'fc', 'fd', 'fe8', 'fe9', 'fea', 'feb', '2001:db8:'] as $prefix) {
                if (str_starts_with($lower, $prefix)) {
                    return false;
                }
            }

            return true;
        }
        $long = ip2long($ip);
        foreach (self::BLOCKED_CIDRS as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }

        return true;
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';

        return $origin.$dir.$location;
    }
}
