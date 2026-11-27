<?php

// Product AI prices (PRODUCT_AI_FREELLMAPI_PLAN.md §2, §5.4, §5.6): structured data first, LLM prices only with a verified quote,
// the SSRF guard of the page fetcher, and the full marketplace search (ranking, sources, EGP stats, cache). Everything is faked.

use App\Services\ProductAi\MarketPriceService;
use App\Services\ProductAi\PriceExtractor;
use App\Support\Http\SafePageFetcher;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function priceLlm(array $items): PromiseInterface
{
    return Http::response(['choices' => [['message' => ['content' => json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)]]]], 200, ['X-Routed-Via' => 'groq/llama']);
}

beforeEach(function () {
    Cache::flush();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test', 'product_ai.models.text' => ['auto']]);
    app()->instance(SafePageFetcher::class, new SafePageFetcher(fn (string $h) => $h === 'internal.test' ? ['10.0.0.8'] : ['93.184.216.34']));
});

it('reads the price from JSON-LD Product / Offer, also inside @graph, type arrays and offer lists', function () {
    $graph = '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage","name":"x"},'
        .'{"@type":["Product","Thing"],"name":"Philips Air Fryer HD9200","offers":[{"@type":"Offer","price":"1,299.00","priceCurrency":"EGP","availability":"https://schema.org/InStock"}]}]}</script>';
    expect(PriceExtractor::structured("<html><head>$graph</head></html>"))
        ->toBe(['price' => 1299.0, 'currency' => 'EGP', 'in_stock' => true, 'title' => 'Philips Air Fryer HD9200']);

    $list = '<script type="application/ld+json">[{"@type":"BreadcrumbList"},{"@type":"Product","name":"P","offers":{"@type":"AggregateOffer","lowPrice":999,"priceCurrency":"egp",'
        .'"availability":"OutOfStock"}}]</script>';
    expect(PriceExtractor::structured($list))->toMatchArray(['price' => 999.0, 'currency' => 'EGP', 'in_stock' => false]);

    expect(PriceExtractor::structured('<meta content="1450.50" property="product:price:amount"><meta property="product:price:currency" content="EGP">'))
        ->toMatchArray(['price' => 1450.5, 'currency' => 'EGP'])
        ->and(PriceExtractor::structured('<meta property="og:price:amount" content="٢٥٠">'))->toMatchArray(['price' => 250.0])
        ->and(PriceExtractor::structured('<html><body>Price 1,299 EGP</body></html>'))->toBeNull()
        ->and(PriceExtractor::structured('<script type="application/ld+json">{"@type":"Product","offers":{"price":"0"}}</script>'))->toBeNull();
});

it('keeps an LLM price only when its quote is in the source text and contains the number', function () {
    $text = 'Philips Essential Air Fryer HD9200 السعر: ١٬٢٩٩ جنيه Was EGP 1,599.00 Now EGP 1,349.00 Free delivery';
    expect(PriceExtractor::verify(1349.0, 'Now EGP 1,349.00', $text))->toBeTrue()
        ->and(PriceExtractor::verify(1299.0, 'السعر: ١٬٢٩٩ جنيه', $text))->toBeTrue()          // Arabic-Indic digits
        ->and(PriceExtractor::verify(1149.0, 'Now EGP 1,149.00', $text))->toBeFalse()         // quote not in the text
        ->and(PriceExtractor::verify(999.0, 'Now EGP 1,349.00', $text))->toBeFalse()          // number not in the quote
        ->and(PriceExtractor::verify(1349.0, '', $text))->toBeFalse();

    Http::fake(['llm.test/*' => priceLlm([
        ['id' => 'a', 'price' => 1349, 'currency' => 'EGP', 'in_stock' => true, 'quote' => 'Now EGP 1,349.00'],
        ['id' => 'b', 'price' => 1199, 'currency' => 'EGP', 'in_stock' => null, 'quote' => 'only EGP 1,199 today'],   // hallucinated: not in the snippet
    ])]);
    $res = app(PriceExtractor::class)->fromText([
        'a' => ['text' => $text, 'source' => 'page_text'],
        'b' => ['text' => 'Philips HD9200 air fryer, best price in Egypt, fast delivery 24h', 'source' => 'snippet'],
    ]);

    expect($res['prices'])->toHaveKey('a')->not->toHaveKey('b')->and($res['dropped'])->toBe(1)
        ->and($res['prices']['a'])->toMatchArray(['price' => 1349.0, 'currency' => 'EGP', 'source' => 'page_text', 'quote' => 'Now EGP 1,349.00'])
        ->and($res['model'])->toBe('groq/llama');
});

it('never fetches private, loopback or non-http addresses', function () {
    Http::fake();
    $fetcher = app(SafePageFetcher::class);
    foreach (['https://internal.test/p' => 'blocked_address', 'http://127.0.0.1/admin' => 'blocked_address', 'http://169.254.169.254/latest/meta-data' => 'blocked_address',
        'http://[::1]/' => 'blocked_address', 'file:///etc/passwd' => 'bad_url', 'gopher://shop.test/' => 'bad_url', 'https://shop.test:8443/' => 'bad_port'] as $url => $reason) {
        expect(fn () => $fetcher->fetch($url))->toThrow(RuntimeException::class, $reason);
    }
    Http::assertNothingSent();
});

it('searches marketplaces, ranks allow-listed stores first, prices rows by source, counts only EGP and caches the result', function () {
    Process::fake(['*' => Process::result(json_encode(['query' => 'q', 'results' => [
        ['title' => 'Air fryer HD9200 - shop x', 'url' => 'https://shop-x.com/p/1', 'snippet' => 'Philips HD9200 1,320 EGP', 'engine' => 'google'],
        ['title' => 'Philips HD9200 | Amazon.eg', 'url' => 'https://www.amazon.eg/dp/B08', 'snippet' => 'Buy online', 'engine' => 'bing'],
        ['title' => 'قلاية فيليبس <b>HD9200</b>', 'url' => 'https://www.jumia.com.eg/philips-hd9200.html', 'snippet' => 'قلاية فيليبس HD9200 بسعر 1,299 جنيه', 'engine' => 'bing'],
        ['title' => 'dup', 'url' => 'https://amazon.eg/dp/B08/', 'snippet' => '', 'engine' => 'duckduckgo'],
        ['title' => 'intranet', 'url' => 'https://internal.test/x', 'snippet' => 'no price', 'engine' => 'google'],
        ['title' => 'uae', 'url' => 'https://shop-uae.com/hd9200', 'snippet' => 'AED 399', 'engine' => 'google'],
        ['title' => 'bad', 'url' => 'javascript:alert(1)', 'snippet' => '', 'engine' => 'google'],
    ], 'errors' => ['duckduckgo' => 'RatelimitException']]))]);
    $ld = '<script type="application/ld+json">{"@type":"Product","name":"Philips Essential Airfryer HD9200/90","offers":{"price":"1150","priceCurrency":"EGP","availability":"InStock"}}</script>';
    Http::fake([
        'https://www.amazon.eg/*' => Http::response("<html><head>$ld</head><body>x</body></html>", 200),
        'https://www.jumia.com.eg/*' => Http::response('blocked', 403),
        'https://shop-x.com/*' => Http::response('<html><body><nav>Home</nav><h1>Air fryer</h1><p>Price: EGP 1,320</p></body></html>', 200),
        'https://shop-uae.com/*' => Http::response('<html><body>AED 399</body></html>', 200),
        'llm.test/*' => function (HttpRequest $r) {
            $items = json_decode($r->data()['messages'][1]['content'], true)['items'];
            $ids = array_column($items, 'id');
            expect($ids)->not->toContain('p0');   // amazon (row 0) already has a structured price

            return priceLlm([
                ['id' => 's1', 'price' => 1299, 'currency' => 'EGP', 'in_stock' => null, 'quote' => 'بسعر 1,299 جنيه'],          // jumia snippet
                ['id' => 'p2', 'price' => 1320, 'currency' => 'EGP', 'in_stock' => null, 'quote' => 'Price: EGP 1,320'],          // shop-x page
                ['id' => 'p4', 'price' => 399, 'currency' => 'AED', 'in_stock' => null, 'quote' => 'AED 399'],
                ['id' => 's3', 'price' => 50, 'currency' => 'EGP', 'in_stock' => null, 'quote' => 'EGP 50'],                      // hallucinated
            ]);
        },
    ]);

    $out = app(MarketPriceService::class)->search('Philips HD9200');
    $rows = collect($out['rows']);

    expect($out['searched'])->toBe('Philips HD9200 price Egypt')->and($out['cached_at'])->toBeNull()
        ->and($rows->pluck('domain')->all())->toBe(['amazon.eg', 'jumia.com.eg', 'shop-x.com', 'internal.test', 'shop-uae.com'])
        ->and($rows[0])->toMatchArray(['marketplace' => 'Amazon.eg', 'allowed' => true, 'price' => 1150.0, 'currency' => 'EGP', 'in_stock' => true,
            'price_source' => 'structured_data', 'title' => 'Philips Essential Airfryer HD9200/90'])
        ->and($rows[1])->toMatchArray(['marketplace' => 'Jumia', 'price' => 1299.0, 'price_source' => 'snippet', 'quote' => 'بسعر 1,299 جنيه', 'title' => 'قلاية فيليبس HD9200'])
        ->and($rows[2])->toMatchArray(['allowed' => false, 'price' => 1320.0, 'price_source' => 'page_text'])
        ->and($rows[3])->toMatchArray(['price' => null, 'price_source' => 'none'])
        ->and($rows[4])->toMatchArray(['price' => 399.0, 'currency' => 'AED'])
        ->and($out['stats'])->toBe(['currency' => 'EGP', 'min' => 1150.0, 'median' => 1299.0, 'max' => 1320.0, 'priced' => 4, 'counted' => 3, 'total' => 5])
        ->and($out['pages_failed'])->toBe(2)   // jumia 403 + internal.test blocked
        ->and($out['errors'])->toBe(['duckduckgo' => 'RatelimitException']);
    Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'internal.test'));
    Process::assertRan(fn (PendingProcess $p) => $p->command[2] === 'text' && $p->command[3] === 'Philips HD9200 price Egypt'
        && in_array('google,bing,duckduckgo', $p->command, true) && in_array('eg-ar', $p->command, true));

    $again = app(MarketPriceService::class)->search('philips   hd9200');
    expect($again['cached_at'])->toBe($out['computed_at'])->and($again['rows'])->toBe($out['rows']);
    Process::assertRanTimes(fn () => true, 1);

    app(MarketPriceService::class)->search('Philips HD9200', fresh: true);
    Process::assertRanTimes(fn () => true, 2);
});

it('still lists the listings, without LLM prices, when the gateway fails, and does not cache that run', function () {
    Process::fake(['*' => Process::result(json_encode(['results' => [['title' => 'A', 'url' => 'https://noon.com/egypt-en/a', 'snippet' => 'EGP 100', 'engine' => 'bing']], 'errors' => []]))]);
    Http::fake(['https://noon.com/*' => Http::response('<p>EGP 100</p>'), 'llm.test/*' => Http::response(['error' => ['code' => 'quota_exceeded']], 429)]);

    $out = app(MarketPriceService::class)->search('thing');
    expect($out['rows'])->toHaveCount(1)->and($out['rows'][0])->toMatchArray(['marketplace' => 'noon', 'price' => null, 'price_source' => 'none'])
        ->and($out['errors'])->toBe(['ai' => 'quota_exceeded']);
    app(MarketPriceService::class)->search('thing');
    Process::assertRanTimes(fn () => true, 2);
});
