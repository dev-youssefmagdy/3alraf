<?php

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\GrowthActionLog;
use App\Models\Interaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProductPrice;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\InteractionService;
use App\Services\Growth\SupplierPriceService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\TenantDemoSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(GrowthAccess::class)->set($this->tenant, true);
    app(GrowthFeatures::class)->enableAll($this->tenant);
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->t = 'http://egy.3alraf.test/admin';
});

function ocOwner()
{
    return TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
}

function ocFeature(string $k, bool $on = true): void
{
    $t = current_tenant();
    $s = $t->settings ?? [];
    $s['growth']['features'][$k] = $on;
    $t->update(['settings' => $s]);
    app()->instance('currentTenant', $t->refresh());
    Cache::flush();
}

// ---- A seeder
it('A: the demo seeder runs clean and seeds >= 10 orders (no proof_required failure)', function () {
    $this->seed([CatalogSeeder::class, TenantDemoSeeder::class]);
    expect(Order::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(10)
        ->and(Order::withoutGlobalScopes()->where('status', 'delivered')->count())->toBeGreaterThanOrEqual(4)
        ->and(Order::withoutGlobalScopes()->where('status', 'cancelled')->count())->toBe(1);
});

// ---- C supplier prices
it('C: ranks suppliers (history + explicit), flags margin, never touches tenant cost, reorder draft uses the supplier quote', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'م'], 'slug' => 'sp1', 'sku' => 'SP1', 'barcode' => '2000000000901']);
    $tp = TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    $a = Supplier::create(['name' => 'A', 'is_active' => true]);
    $b = Supplier::create(['name' => 'B', 'is_active' => true]);
    $c = Supplier::create(['name' => 'C', 'is_active' => true]);
    $branch = Branch::first();
    foreach ([[$a, 55, '2026-01-01'], [$a, 65, '2026-02-01'], [$b, 70, '2026-02-01']] as [$s, $cost, $at]) {
        $po = PurchaseOrder::create(['number' => 'PO'.$s->id.$cost, 'supplier_id' => $s->id, 'branch_id' => $branch->id, 'status' => PurchaseStatus::RECEIVED, 'items_total' => 0, 'total_cost' => 0, 'ordered_at' => $at, 'received_at' => $at]);
        $po->items()->create(['product_id' => $p->id, 'product_variant_id' => 0, 'quantity' => 10, 'unit_cost' => $cost, 'line_total' => $cost * 10]);
    }
    $svc = app(SupplierPriceService::class);
    expect($svc->history($p->id)[$a->id])->toMatchArray(['last' => 65.0, 'avg' => 60.0, 'min' => 55.0, 'n' => 2]);
    SupplierProductPrice::create(['supplier_id' => $c->id, 'product_id' => $p->id, 'unit_cost' => 50, 'moq' => 5, 'lead_time_days' => 30, 'is_preferred' => true]);
    SupplierProductPrice::create(['supplier_id' => $b->id, 'product_id' => $p->id, 'unit_cost' => 45, 'valid_to' => '2020-01-01']); // expired: ignored
    $o = $svc->options($p->id, 60.0, 100.0, 15.0, 20);
    expect(array_column($o, 'supplier_id'))->toBe([$a->id, $b->id, $c->id]);       // C is cheapest but its lead time (30d) does not fit the 20d need
    expect($o[0]['variance_pct'])->toBe(8.3)->and($o[1]['below_margin_floor'])->toBeFalse()->and($o[2]['fits_lead'])->toBeFalse()->and($o[2]['source'])->toBe('explicit');
    $hi = $svc->options($p->id, 60.0, 100.0, 33.0, 20);   // A 65 -> 35% ok, B 70 -> 30% below a 33% floor
    expect($hi[0]['below_margin_floor'])->toBeFalse()->and($hi[1]['below_margin_floor'])->toBeTrue();
    expect($svc->costFor($b->id, $p->id))->toBe(70.0)->and($svc->costFor($c->id, $p->id))->toBe(50.0);
    expect($tp->fresh()->cost)->toBe($tp->cost);                                     // listing cost never changed by price lists
});

it('C: admin can save/delete a supplier price (audited, switch-gated) and the inventory page lists alternatives', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'م'], 'slug' => 'sp2', 'sku' => 'SP2', 'barcode' => '2000000000902']);
    TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    $s = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    $this->actingAs(ocOwner(), 'tenant');
    ocFeature('inventory_engine', false);
    $this->postJson("{$this->t}/growth/inventory/prices", ['product_id' => $p->id, 'supplier_id' => $s->id, 'unit_cost' => 50])->assertStatus(403);   // switch OFF
    ocFeature('inventory_engine');
    $this->postJson("{$this->t}/growth/inventory/prices", ['product_id' => $p->id, 'supplier_id' => $s->id, 'unit_cost' => 0])->assertStatus(422);
    $this->postJson("{$this->t}/growth/inventory/prices", ['product_id' => $p->id, 'supplier_id' => $s->id, 'unit_cost' => 50, 'moq' => 3, 'is_preferred' => 1])->assertOk();
    $row = SupplierProductPrice::first();
    expect((string) $row->unit_cost)->toBe('50.00')->and(GrowthActionLog::where('action', 'inventory.supplier_price_saved')->exists())->toBeTrue();
    $this->get("{$this->t}/growth/inventory")->assertOk();
    $this->deleteJson("{$this->t}/growth/inventory/prices/{$row->id}")->assertOk();
    expect(SupplierProductPrice::count())->toBe(0);
});

// ---- D classifier loop
it('D: blind set exists (>= 200, independent labels) and the evaluator reports before/after numbers', function () {
    $set = json_decode(file_get_contents(base_path('tests/Fixtures/growth/interactions_blind.json')), true);
    expect(count($set))->toBeGreaterThanOrEqual(200);
    $res = app(InteractionService::class)->evaluateBlind($set);
    expect($res['n'])->toBe(count($set))->and($res['accuracy']['category'])->toBeNull()->and($res['accuracy']['sentiment'])->toBeGreaterThan(0.5);
    $this->artisan('growth:eval-classifier', ['--set' => 'blind'])->assertSuccessful();
});

function ocInteraction(array $o = []): Interaction
{
    static $n = 0;
    $n++;

    return Interaction::create($o + ['channel' => 'facebook', 'external_id' => 'x'.$n, 'text_masked' => 'How much is it?', 'text_hash' => sha1('t'.$n), 'category' => 'price', 'intent' => 'question', 'sentiment' => 'neutral', 'status' => 'classified', 'received_at' => now()]);
}

it('D: review control stores the human label (audited), validates enums; real eval refuses a verdict below the minimum', function () {
    $i = ocInteraction();
    $this->actingAs(ocOwner(), 'tenant');
    $this->postJson("{$this->t}/growth/interactions/{$i->id}/review", ['category' => 'bogus', 'intent' => 'question', 'sentiment' => 'neutral'])->assertStatus(422);
    $this->postJson("{$this->t}/growth/interactions/{$i->id}/review", ['category' => 'shipping', 'intent' => 'complaint', 'sentiment' => 'negative'])->assertOk();
    $i->refresh();
    expect($i->reviewed_category)->toBe('shipping')->and($i->reviewed_by)->not->toBeNull()->and($i->reviewed_at)->not->toBeNull()
        ->and(GrowthActionLog::where('action', 'interaction.reviewed')->exists())->toBeTrue();
    $res = app(InteractionService::class)->evaluateReal();
    expect($res['n'])->toBe(1)->and($res['verdict'])->toBe('insufficient_data');
    $this->artisan('growth:eval-classifier', ['--real' => true, '--tenant' => 'egy'])->assertFailed();
    expect(app(InteractionService::class)->realEvalPasses())->toBeFalse();
});

it('D: real eval with >= min reviewed rows gives a verdict, stores it, and unlocks the gate only when targets are met', function () {
    config(['growth.classifier.min_reviewed' => 10]);
    foreach (range(1, 10) as $k) {
        ocInteraction(['reviewed_category' => 'price', 'reviewed_intent' => 'question', 'reviewed_sentiment' => 'neutral', 'reviewed_at' => now(), 'category' => $k <= 3 ? 'shipping' : 'price']);
    }
    $this->artisan('growth:eval-classifier', ['--real' => true, '--tenant' => 'egy'])->assertSuccessful();
    $ce = $this->tenant->fresh()->settings['growth']['classifier_eval'];
    expect($ce['verdict'])->toBe('meets_target')->and($ce['n'])->toBe(10)->and($ce['accuracy']['category'])->toBe(0.7);
    app()->instance('currentTenant', $this->tenant->fresh());
    expect(app(InteractionService::class)->realEvalPasses())->toBeTrue();
    Interaction::query()->limit(5)->update(['category' => 'shipping']);
    $this->artisan('growth:eval-classifier', ['--real' => true, '--tenant' => 'egy'])->assertFailed();
    app()->instance('currentTenant', $this->tenant->fresh());
    expect(app(InteractionService::class)->realEvalPasses())->toBeFalse()->and(app(InteractionService::class)->aiFallback('where is my order'))->toBe([]);
});

it('D: the labeling export is stratified, masked and has no contact or thread data', function () {
    foreach (['price', 'shipping', 'trust'] as $c) {
        foreach (range(1, 4) as $_) {
            ocInteraction(['category' => $c, 'text_masked' => 'call me on 01012345678 about '.$c, 'thread_id' => 'secret-thread']);
        }
    }
    $out = sys_get_temp_dir().'/oc-sample-'.uniqid().'.json';
    $this->artisan('growth:export-labeling-sample', ['--tenant' => 'egy', '--n' => 6, '--out' => $out])->assertSuccessful();
    $rows = json_decode(file_get_contents($out), true);
    @unlink($out);
    expect($rows)->toHaveCount(6)->and(collect($rows)->pluck('predicted.category')->unique()->count())->toBe(3)
        ->and(json_encode($rows))->not->toContain('01012345678')->not->toContain('secret-thread');
});
