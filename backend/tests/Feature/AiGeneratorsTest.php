<?php

use App\Enums\AiDriverType;
use App\Enums\AiGenerationStatus;
use App\Enums\StockMovementType as T;
use App\Models\AiGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Ai\GeneratorService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    $p = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'oa-gen', 'api_key' => 'sk-test-1234567', 'is_active' => true]);
    $this->text = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'gpt-gen', 'label' => 'T', 'kind' => 'text', 'capabilities' => ['json_mode' => true], 'is_active' => true]);
    $this->image = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'img-gen', 'label' => 'I', 'kind' => 'image', 'is_active' => true]);
    AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], ['ai_enabled' => true, 'features' => ['generators' => true], 'allowed_model_ids' => [$this->text->id, $this->image->id],
        'default_ai_model_id' => $this->text->id, 'monthly_token_quota' => null, 'monthly_image_quota' => null, 'brand_voice' => null, 'forbidden_words' => []]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/ai/generators';
    $this->product = function (string $slug, string $price = '100.00', string $cost = '50', int $stock = 20) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        if ($stock > 0) {
            app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, $cost);
        }

        return $pr;
    };
    $this->llm = fn (array $draft) => ['choices' => [['message' => ['content' => json_encode($draft)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
    $this->gen = function (string $kind, array $draft, array $o = []) {
        Http::swap(new \Illuminate\Http\Client\Factory);   // fakes stack: the first match wins, so start clean
        Http::fake(['*' => Http::response(($this->llm)($draft))]);

        return ($this->owner)()->postJson($this->base.'/generate', $o + ['kind' => $kind, 'goal' => 'Sell more', 'margin_floor' => 10])->assertCreated();
    };
    $this->start = now()->addDay()->format('Y-m-d H:i');
    $this->end = now()->addDays(5)->format('Y-m-d H:i');
});

it('flash sale: approve creates exactly one INACTIVE record through the service and links the target', function () {
    $a = ($this->product)('fa'); $b = ($this->product)('fb');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'Weekend', 'ar' => 'نهاية الأسبوع'], 'starts_at' => $this->start, 'ends_at' => $this->end,
        'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 20, 'qty_limit' => 5], ['item' => "{$b->id}-0", 'discount_type' => 'price', 'value' => 80, 'qty_limit' => null]]]);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->status)->toBe(AiGenerationStatus::DRAFT)->and($g->output['errors'])->toBe([]);
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $sale = FlashSale::first();
    expect(FlashSale::count())->toBe(1)->and($sale->is_active)->toBeFalse()->and($sale->items()->count())->toBe(2)
        ->and($g->fresh()->target_id)->toBe($sale->id)->and($g->fresh()->target_type)->toBe(FlashSale::class)->and($g->fresh()->reviewed_by)->not->toBeNull();
    // cannot approve twice
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422)->assertJsonPath('reason', 'state');
    expect(FlashSale::count())->toBe(1);
});

it('blocks a margin-floor violation at generation and at approval, with a clear message', function () {
    $a = ($this->product)('ma', '100.00', '50');   // 20% off = 80 -> margin 37.5 ok ; 60% off = 40 -> below cost
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'Deep'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 60]]]);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->output['errors'])->not->toBeEmpty()->and($g->output['errors'][0]['code'])->toBe('margin_floor');
    $x = ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422)->assertJsonPath('reason', 'blocked');
    expect($x->json('errors.0'))->toContain('10%')->and(FlashSale::count())->toBe(0)->and($g->fresh()->status)->toBe(AiGenerationStatus::DRAFT);
    // editing the discount to a safe value unblocks it
    $edit = ['name' => ['en' => 'Deep'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => '20', 'qty_limit' => '']]];
    ($this->owner)()->putJson("{$this->base}/{$g->id}", ['draft' => $edit])->assertOk()->assertJsonPath('data.errors', []);
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk();
    expect(FlashSale::count())->toBe(1);
});

it('discount code: percent above the safe headroom is blocked, a safe one creates an inactive code', function () {
    ($this->product)('ca', '100.00', '50');   // floor 10% -> min net 55.56 -> max 44.4% off
    $bad = ($this->gen)('discount_code', ['code' => 'MEGA60', 'type' => 'percent', 'value' => 60, 'min_order' => 0, 'scope' => 'all', 'starts_at' => $this->start, 'ends_at' => $this->end]);
    expect(AiGeneration::find($bad->json('data.id'))->output['errors'][0]['code'])->toBe('code_margin');
    $ok = ($this->gen)('discount_code', ['code' => 'save15', 'type' => 'percent', 'value' => 15, 'min_order' => 0, 'scope' => 'all', 'starts_at' => $this->start, 'ends_at' => $this->end]);
    ($this->owner)()->postJson("{$this->base}/{$ok->json('data.id')}/approve")->assertOk();
    $c = DiscountCode::first();
    expect($c->code)->toBe('SAVE15')->and($c->is_active)->toBeFalse();
    // duplicate code is rejected on the next draft
    $dup = ($this->gen)('discount_code', ['code' => 'SAVE15', 'type' => 'percent', 'value' => 10, 'scope' => 'all']);
    expect(AiGeneration::find($dup->json('data.id'))->output['errors'][0]['code'])->toBe('code_taken');
});

it('bundle: stock and margin are enforced; approval creates an inactive bundle with its components', function () {
    $a = ($this->product)('ba', '100.00', '40'); $b = ($this->product)('bb', '60.00', '30', 2);
    $over = ($this->gen)('bundle', ['name' => ['en' => 'Pack'], 'price' => 140, 'items' => [['item' => "{$a->id}-0", 'quantity' => 1], ['item' => "{$b->id}-0", 'quantity' => 5]]]);
    expect(collect(AiGeneration::find($over->json('data.id'))->output['errors'])->pluck('code'))->toContain('qty_over_stock');
    $low = ($this->gen)('bundle', ['name' => ['en' => 'Pack'], 'price' => 75, 'items' => [['item' => "{$a->id}-0", 'quantity' => 1], ['item' => "{$b->id}-0", 'quantity' => 1]]]);   // cost 70 -> margin 6.7% < 10
    expect(collect(AiGeneration::find($low->json('data.id'))->output['errors'])->pluck('code'))->toContain('margin_floor');
    $ok = ($this->gen)('bundle', ['name' => ['en' => 'Pack', 'ar' => 'باقة'], 'description' => ['en' => 'Two'], 'price' => 130, 'items' => [['item' => "{$a->id}-0", 'quantity' => 1], ['item' => "{$b->id}-0", 'quantity' => 1]]]);
    ($this->owner)()->postJson("{$this->base}/{$ok->json('data.id')}/approve")->assertOk();
    $bundle = Bundle::first();
    expect(Bundle::count())->toBe(1)->and($bundle->is_active)->toBeFalse()->and($bundle->items()->count())->toBe(2)->and((string) $bundle->price)->toBe('130.00');
});

it('banner: generates an image sized for the placement and approval creates an inactive banner', function () {
    \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));
    $png = base64_encode(file_get_contents(base_path('public/favicon.ico')) ?: '');
    $img = imagecreatetruecolor(40, 20); ob_start(); imagepng($img); $png = base64_encode(ob_get_clean());
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*images*' => Http::response(['data' => [['b64_json' => $png]]]), '*' => Http::response(($this->llm)(['placement' => 'home_hero', 'title' => ['en' => 'Big Sale', 'ar' => 'تخفيضات'], 'link_url' => '/offers', 'image_prompt' => 'sunny'])) ]);
    $r = ($this->owner)()->postJson($this->base.'/generate', ['kind' => 'banner', 'goal' => 'Summer', 'margin_floor' => 10, 'image_model_id' => $this->image->id])->assertCreated();
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->status)->toBe(AiGenerationStatus::DRAFT)->and($g->media[0]['state'])->toBe('ready');
    Http::assertSent(fn ($req) => str_contains($req->url(), 'images') && ($req['size'] ?? null) === '1536x1024');
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk();
    $b = Banner::first();
    expect(Banner::count())->toBe(1)->and($b->is_active)->toBeFalse()->and($b->images)->toHaveKeys(['en', 'ar']);
});

it('banner without an image cannot be approved', function () {
    $r = ($this->gen)('banner', ['placement' => 'popup', 'title' => ['en' => 'Hi'], 'image_prompt' => 'x']);
    $g = AiGeneration::find($r->json('data.id'));
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422)->assertJsonPath('reason', 'blocked');
    expect(Banner::count())->toBe(0);
});

it('reject creates nothing, stores the reason and blocks a later approve', function () {
    $a = ($this->product)('ra');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $id = $r->json('data.id');
    ($this->owner)()->postJson("{$this->base}/$id/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason');
    ($this->owner)()->postJson("{$this->base}/$id/reject", ['reason' => 'Not now'])->assertOk()->assertJsonPath('data.status', 'rejected');
    expect(FlashSale::count())->toBe(0)->and(AiGeneration::find($id)->reject_reason)->toBe('Not now');
    ($this->owner)()->postJson("{$this->base}/$id/approve")->assertStatus(422);
    expect(FlashSale::count())->toBe(0);
});

it('stale drafts expire: approval refused and the sweep rejects them', function () {
    $a = ($this->product)('ea');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $g = AiGeneration::find($r->json('data.id'));
    AiGeneration::where('id', $g->id)->update(['created_at' => now()->subDays(GeneratorService::TTL_DAYS + 1)]);
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422)->assertJsonPath('reason', 'expired');
    expect(FlashSale::count())->toBe(0);
    $this->artisan('ai:expire-drafts')->assertSuccessful();
    expect($g->fresh()->status)->toBe(AiGenerationStatus::REJECTED)->and($g->fresh()->reject_reason)->toBe('expired');
});

it('splits permissions: use can generate and edit, only approve can approve or reject', function () {
    $a = ($this->product)('pa');
    $role = Role::create(['name' => 't'.$this->tenant->id.':gen-use', 'guard_name' => 'tenant']);
    $role->givePermissionTo('ai_generators.use');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $staff = TenantAdmin::create(['name' => 'S', 'email' => 'gen-staff@egy.test', 'password' => 'password', 'is_active' => true]);
    $staff->assignRole($role);
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $id = $r->json('data.id');
    $this->actingAs($staff, 'tenant');
    $this->get("{$this->base}/$id")->assertOk();
    $this->get($this->base)->assertOk();
    $this->postJson("{$this->base}/$id/approve")->assertForbidden();
    $this->postJson("{$this->base}/$id/reject", ['reason' => 'nope'])->assertForbidden();
    expect(FlashSale::count())->toBe(0);
    // and a user without any generator permission sees nothing
    $none = TenantAdmin::create(['name' => 'N', 'email' => 'gen-none@egy.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($none, 'tenant')->get($this->base)->assertForbidden();
});

it('double approve race: the row lock lets exactly one approval create a record', function () {
    $a = ($this->product)('da');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $id = $r->json('data.id');
    $svc = app(GeneratorService::class);
    // two "requests" that both loaded the draft before either committed: the second sees APPROVED under the lock
    $svc->approve($id, 1);
    expect(fn () => $svc->approve($id, 1))->toThrow(\App\Exceptions\AiException::class);
    expect(FlashSale::count())->toBe(1);
});

it('is tenant isolated: another tenant cannot see, edit or approve a draft, and cannot use foreign products', function () {
    $a = ($this->product)('ia');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $id = $r->json('data.id');
    // same id looked up under the other tenant
    app()->instance('currentTenant', $this->other);
    expect(app(GeneratorService::class)->find($id))->toBeNull()->and(AiGeneration::find($id))->toBeNull();
    expect(fn () => app(GeneratorService::class)->approve($id, 1))->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    app()->instance('currentTenant', $this->tenant);
    expect(FlashSale::count())->toBe(0);
    // a product only the other tenant lists is rejected as an item
    $foreign = Product::create(['type' => 'simple', 'name' => ['en' => 'Foreign'], 'slug' => 'foreign-1', 'sku' => 'FOREIGN1', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['tenant_id' => $this->other->id, 'product_id' => $foreign->id, 'price' => '10', 'cost' => '5', 'is_active' => true]);
    $x = ($this->gen)('bundle', ['name' => ['en' => 'Pack'], 'price' => 50, 'items' => [['item' => "{$a->id}-0", 'quantity' => 1], ['item' => "{$foreign->id}-0", 'quantity' => 1]]]);
    expect(collect(AiGeneration::find($x->json('data.id'))->output['errors'])->pluck('code'))->toContain('item_invalid');
});

it('requires the generators feature, shows the review pages and datatable', function () {
    $a = ($this->product)('va');
    $r = ($this->gen)('flash_sale', ['name' => ['en' => 'X'], 'starts_at' => $this->start, 'ends_at' => $this->end, 'items' => [['item' => "{$a->id}-0", 'discount_type' => 'percent', 'value' => 10]]]);
    $id = $r->json('data.id');
    ($this->owner)()->get($this->base)->assertOk()->assertSee(__('ai.gen.generate'));
    ($this->owner)()->get("{$this->base}/$id")->assertOk()->assertSee(__('ai.gen.approve'));
    ($this->owner)()->getJson("{$this->base}/datatable")->assertOk()->assertJsonPath('recordsTotal', 1);
    AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['features' => ['chat' => true]]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    ($this->owner)()->get($this->base)->assertForbidden();
});
