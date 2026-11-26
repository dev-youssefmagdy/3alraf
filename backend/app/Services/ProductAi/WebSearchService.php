<?php

namespace App\Services\ProductAi;

use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use Illuminate\Support\Facades\Process;

/**
 * Web (text) search for Product AI: the free `ddgs` library through tools/image-picker (`image_picker.py text`, same venv as Find images,
 * backends from config product_ai.search.backends), plus FreeLLMAPI POST /v1/search when product_ai.search.freellm is on.
 * Fails soft: a provider that does not answer is listed in `errors`; results are normalised (http/https only, no HTML) and de-duplicated.
 */
class WebSearchService
{
    public function __construct(private FreeLlmClient $llm) {}

    /**
     * @return array{results: list<array{title:string,url:string,snippet:string,engine:string,domain:string}>, errors: array<string,string>}
     */
    public function search(string $query, ?int $limit = null): array
    {
        $limit ??= (int) config('product_ai.search.results', 15);
        $rows = [];
        $errors = [];

        [$ddgs, $ddgsErrors] = $this->ddgs($query, $limit);
        array_push($rows, ...$ddgs);
        $errors += $ddgsErrors;

        if (config('product_ai.search.freellm') && $this->llm->configured()) {
            try {
                foreach ($this->llm->search($query, $limit) as $r) {
                    $rows[] = $r + ['engine' => 'freellmapi'];
                }
            } catch (LlmUnavailableException $e) {
                $errors['freellmapi'] = $e->getMessage();
            }
        }

        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $url = self::url($r['url'] ?? null);
            if (! $url || isset($seen[$key = self::urlKey($url)])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['title' => self::clean($r['title'] ?? '', 300), 'url' => $url, 'snippet' => self::clean($r['snippet'] ?? '', 1000),
                'engine' => mb_substr((string) ($r['engine'] ?? ''), 0, 30), 'domain' => self::domain($url)];
        }

        return ['results' => array_slice($out, 0, max(1, $limit * 2)), 'errors' => $errors];
    }

    /** @return array{0: list<array<string,mixed>>, 1: array<string,string>} */
    private function ddgs(string $query, int $limit): array
    {
        $result = Process::timeout((int) config('product_ai.search.timeout', 30))->run([
            (string) config('image_picker.python'), (string) config('image_picker.script'), 'text', $query,
            '-n', (string) $limit, '--backend', implode(',', (array) config('product_ai.search.backends')), '--region', (string) config('product_ai.market.region', 'eg-ar'),
            '--no-cache', // Laravel caches the whole price result
        ]);
        $json = json_decode($result->output(), true);
        if (! is_array($json) || ! is_array($json['results'] ?? null)) {
            logger()->warning('product_ai: text search failed', ['exit' => $result->exitCode(), 'error' => mb_substr($result->errorOutput() ?: $result->output(), 0, 500)]);

            return [[], ['ddgs' => 'unavailable']];
        }
        $errors = [];
        foreach ((array) ($json['errors'] ?? []) as $engine => $e) {
            $errors[mb_substr((string) $engine, 0, 30)] = mb_substr((string) $e, 0, 200);
        }

        return [array_values(array_filter($json['results'], 'is_array')), $errors];
    }

    public static function url(mixed $u): ?string
    {
        return is_string($u) && strlen($u) <= 2048 && preg_match('#^https?://[^\s<>"]+$#i', $u) ? $u : null;
    }

    /** host without www + path, for de-duplication */
    public static function urlKey(string $url): string
    {
        $p = parse_url($url);

        return self::domain($url).rtrim((string) ($p['path'] ?? ''), '/').(isset($p['query']) ? '?'.$p['query'] : '');
    }

    public static function domain(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
    }

    public static function clean(mixed $s, int $max): string
    {
        $t = html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $t) ?? ''), 0, $max);
    }
}
