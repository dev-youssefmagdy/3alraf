<?php

// Growth / integration-API test helpers shared by several Feature files (loaded from tests/Pest.php, so every file and every
// parallel worker has them — they used to live inside GrowthFoundationTest / GrowthMetricsTest, which broke GrowthGapFixesTest
// whenever it ran in another worker: "Call to undefined function growthClient()/mkOrder()/ownerOf()", a baseline failure).

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\MetricsService;
use App\Services\Growth\SignatureService;
use App\Services\Growth\StatsAggregator;
use Carbon\Carbon;

/** Create an integration client, return [client, token, secret]. */
function growthClient(array $scopes = ['read', 'proposals:write', 'ingest:write', 'events:read'], ?string $webhook = null): array
{
    return app(IntegrationClientService::class)->create('n8n', $scopes, $webhook, null);
}

/** Signed call to the integration API. */
function integrationCall($test, string $method, string $path, ?array $body, string $token, string $secret, array $headers = [], ?int $ts = null)
{
    $raw = $body === null ? '' : json_encode($body);
    $ts ??= time();
    $h = ['Authorization' => 'Bearer '.$token, 'X-Timestamp' => (string) $ts, 'X-Signature' => app(SignatureService::class)->sign($secret, $ts, $raw), 'Accept' => 'application/json', 'CONTENT_TYPE' => 'application/json'] + $headers;
    $server = collect($h)->mapWithKeys(fn ($v, $k) => [$k === 'CONTENT_TYPE' ? $k : 'HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all();

    return $test->call($method, '/api/integration/v1/'.$path, [], [], [], $server, $raw);
}

function ownerOf(string $slug): Admin
{
    return Admin::query()->where('email', "owner@$slug.test")->first();
}

/** A delivered, paid demo order with the given lines (growth metrics fixtures). */
function mkOrder(array $o = [], array $items = [['q' => 2, 'price' => 100, 'cost' => 40]]): Order
{
    static $seq = 0;
    $sub = collect($items)->sum(fn ($i) => $i['q'] * $i['price']);
    $cogs = collect($items)->sum(fn ($i) => $i['q'] * ($i['cost'] ?? 0));
    $order = new Order;
    $order->forceFill(array_merge(['number' => 'T'.++$seq.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '01000000100', 'address' => 'a', 'status' => 'delivered', 'payment_status' => 'paid',
        'subtotal' => $sub, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 10, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => $sub,
        'cogs' => $cogs, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => Carbon::parse('2026-10-20 12:00')], $o))->save();
    foreach ($items as $i) {
        $it = new OrderItem;
        $it->forceFill(['order_id' => $order->id, 'product_id' => $i['pid'] ?? 1, 'name' => 'P', 'unit_price' => $i['price'], 'unit_cost' => $i['cost'] ?? 0, 'quantity' => $i['q'], 'line_subtotal' => $i['q'] * $i['price'], 'line_discount' => 0])->save();
    }

    return $order;
}

function rebuild(Carbon $d)
{
    app(StatsAggregator::class)->rebuild($d, $d);

    return app(MetricsService::class)->summary($d, $d);
}
