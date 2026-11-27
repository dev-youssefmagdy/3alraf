<?php

namespace App\Services\ProductAi;

use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use App\Support\Http\SafePageFetcher;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * A2 "Marketplace prices": search text → web search → rank by the Egypt marketplace allow-list → fetch up to 8 listing pages (SSRF-safe)
 * → price from structured data, else from page text / snippet through the LLM with quote verification → rows + EGP stats.
 * Cached 24 h per search text (cached_at is set when the answer comes from the cache). Fails soft: listings are shown even without the LLM.
 */
class MarketPriceService
{
    /** The fields of a price row, in the API order. */
    private const FIELDS = ['marketplace', 'domain', 'allowed', 'title', 'url', 'price', 'currency', 'in_stock', 'price_source', 'quote', 'snippet', 'engine'];

    public function __construct(private WebSearchService $search, private PriceExtractor $extractor, private SafePageFetcher $pages, private FreeLlmClient $llm) {}

    /**
     * @return array{query:string, searched:string, currency:string, rows:list<array<string,mixed>>, stats:array<string,mixed>, errors:array<string,string>,
     *     pages_failed:int, model:?string, computed_at:string, cached_at:?string}
     */
    public function search(string $query, bool $fresh = false): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $key = 'product_ai:prices:'.hash('sha256', mb_strtolower($query).'|'.config('product_ai.market.currency').'|'.implode(',', (array) config('product_ai.search.backends')));
        if (! $fresh && is_array($hit = Cache::get($key))) {
            return ['cached_at' => $hit['computed_at']] + $hit;
        }

        $started = microtime(true);
        $searched = trim($query.' '.config('product_ai.search.suffix'));
        $found = $this->search->search($searched);
        $errors = $found['errors'];

        $rows = $this->rank($found['results']);
        [$texts, $failed] = $this->readPages($rows);

        $model = null;
        $items = $this->llmItems($rows, $texts);
        if ($items !== []) {
            try {
                $res = $this->extractor->fromText($items, max(5, 55 - (int) (microtime(true) - $started)));
                $model = $res['model'];
                foreach ($rows as $i => &$row) {
                    $hit = $res['prices']["p$i"] ?? $res['prices']["s$i"] ?? null;
                    if ($hit) {
                        $row = ['price' => $hit['price'], 'currency' => $hit['currency'], 'in_stock' => $hit['in_stock'] ?? $row['in_stock'],
                            'price_source' => $hit['source'], 'quote' => $hit['quote']] + $row;
                    }
                }
                unset($row);
            } catch (LlmUnavailableException $e) {
                $errors['ai'] = $e->errorCode;
            }
        }

        $currency = (string) config('product_ai.market.currency', 'EGP');
        $rows = array_map(fn (array $r) => array_intersect_key($r, array_flip(self::FIELDS)), $rows);
        $out = ['query' => $query, 'searched' => $searched, 'currency' => $currency, 'rows' => $rows, 'stats' => self::stats($rows, $currency),
            'errors' => $errors, 'pages_failed' => $failed, 'model' => $model, 'computed_at' => now()->toIso8601String(), 'cached_at' => null];
        if ($rows !== [] && ! isset($errors['ai'])) { // never cache a run the LLM missed: the next try may price more rows
            Cache::put($key, $out, now()->addHours((int) config('product_ai.cache_hours', 24)));
        }

        return $out;
    }

    /**
     * min / median / max over the rows priced in the market currency only.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{currency:string,min:?float,median:?float,max:?float,priced:int,counted:int,total:int}
     */
    public static function stats(array $rows, string $currency): array
    {
        $prices = array_values(array_map(fn ($r) => (float) $r['price'], array_filter($rows, fn ($r) => $r['price'] !== null && $r['currency'] === $currency)));
        sort($prices);
        $n = count($prices);
        $median = $n === 0 ? null : ($n % 2 ? $prices[intdiv($n, 2)] : round(($prices[$n / 2 - 1] + $prices[$n / 2]) / 2, 2));

        return ['currency' => $currency, 'min' => $prices[0] ?? null, 'median' => $median, 'max' => $n ? $prices[$n - 1] : null,
            'priced' => count(array_filter($rows, fn ($r) => $r['price'] !== null)), 'counted' => $n, 'total' => count($rows)];
    }

    /** The allow-listed store a domain belongs to (host suffix match), or null. */
    public static function marketplace(string $domain): ?string
    {
        $names = (array) config('product_ai.marketplace_names');
        foreach ((array) config('product_ai.marketplaces') as $key => $value) {
            [$suffix, $name] = is_int($key) ? [(string) $value, null] : [(string) $key, (string) $value]; // list of domains, or domain => name
            $suffix = preg_replace('/^www\./', '', strtolower(trim($suffix))) ?? '';
            if ($suffix !== '' && ($domain === $suffix || str_ends_with($domain, '.'.$suffix))) {
                return $name ?? (string) ($names[$suffix] ?? $suffix);
            }
        }

        return null;
    }

    /**
     * Allow-listed marketplaces first (search order kept inside each group), at most max_rows.
     *
     * @param  list<array{title:string,url:string,snippet:string,engine:string,domain:string}>  $results
     * @return list<array<string,mixed>>
     */
    private function rank(array $results): array
    {
        $allowed = [];
        $other = [];
        $seen = [];
        foreach ($results as $r) {
            $key = WebSearchService::urlKey($r['url']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $store = self::marketplace($r['domain']);
            $row = ['marketplace' => $store ?? $r['domain'], 'domain' => $r['domain'], 'allowed' => $store !== null, 'title' => $r['title'], 'url' => $r['url'],
                'price' => null, 'currency' => null, 'in_stock' => null, 'price_source' => 'none', 'quote' => null, 'snippet' => $r['snippet'], 'engine' => $r['engine']];
            if ($store !== null) {
                $allowed[] = $row;
            } else {
                $other[] = $row;
            }
        }

        return array_slice([...$allowed, ...$other], 0, (int) config('product_ai.max_rows', 20));
    }

    /**
     * Fetch the first pages (at most pages.max_per_run, within pages.budget_seconds); structured prices are written into the rows,
     * the page text of the others is returned for the LLM.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{0: array<int,string>, 1: int} row index => page text, pages that could not be read
     */
    private function readPages(array &$rows): array
    {
        $texts = [];
        $failed = 0;
        $started = microtime(true);
        $max = (int) config('product_ai.pages.max_per_run', 8);
        foreach ($rows as $i => &$row) {
            if ($i >= $max || microtime(true) - $started > (int) config('product_ai.pages.budget_seconds', 15)) {
                break;
            }
            try {
                $html = $this->pages->fetch($row['url']);
            } catch (RuntimeException $e) {
                $failed++;
                logger()->info('product_ai: page not read', ['url' => $row['url'], 'reason' => $e->getMessage()]);

                continue;
            }
            if ($s = PriceExtractor::structured($html)) {
                $row = ['price' => $s['price'], 'currency' => $s['currency'], 'in_stock' => $s['in_stock'], 'price_source' => 'structured_data',
                    'title' => $s['title'] ?: (SafePageFetcher::title($html) ?? $row['title'])] + $row;

                continue;
            }
            $texts[$i] = SafePageFetcher::text($html);
        }
        unset($row);

        return [$texts, $failed];
    }

    /**
     * What the LLM reads: the page text of each fetched row without a structured price (p{i}), and the snippet of every unpriced row (s{i}).
     * Texts without a digit cannot hold a price and are not sent.
     *
     * @return array<string, array{text:string, source:'page_text'|'snippet'}>
     */
    private function llmItems(array $rows, array $texts): array
    {
        if (! $this->llm->configured()) {
            return [];
        }
        $items = [];
        foreach ($rows as $i => $row) {
            if ($row['price'] !== null) {
                continue;
            }
            if (isset($texts[$i]) && preg_match('/[\d٠-٩]/u', $texts[$i])) {
                $items["p$i"] = ['text' => $texts[$i], 'source' => 'page_text'];
            }
            if ($row['snippet'] !== '' && preg_match('/[\d٠-٩]/u', $row['snippet'])) {
                $items["s$i"] = ['text' => $row['snippet'], 'source' => 'snippet'];
            }
        }

        return $items;
    }
}
