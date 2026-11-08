<?php

// P6 (MARKETER_PLAN.md, MCP.md §7 / §8 / §12): Meta Marketing API client + campaign drafts. Http::fake only — never a real call.
// PAUSED-only payloads, no activate / budget / delete path, approval bound to version + content hash, edit / tamper / expiry invalidate, idempotent publish and
// resume after failure, status verification, permissions matrix, audit without secrets, cached reads, internal API scopes (no approve / publish route).

use App\DTOs\Marketing\MarketingTokenData;
use App\DTOs\Marketing\MetaCampaignDraftData;
use App\Models\Admin;
use App\Models\MetaAuditLog;
use App\Models\MetaCampaignApproval;
use App\Models\MetaCampaignDraft;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\Marketing\MarketingActionException;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Meta\MetaCampaignDraftService;
use App\Services\Marketing\Meta\MetaException;
use App\Services\Marketing\Meta\MetaGraphClient;
use App\Services\Marketing\Meta\MetaPublisher;
use App\Services\Marketing\Meta\MetaReadService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

const META_TEST_TOKEN = 'EAAB-test-token-should-never-leak';
const META_TEST_SECRET = 'app-secret-should-never-leak';

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test', 'meta.access_token' => META_TEST_TOKEN, 'meta.app_secret' => META_TEST_SECRET,
        'meta.ad_account_id' => 'act_111', 'meta.page_id' => '222', 'meta.graph_version' => 'v99.0', 'meta.graph_url' => 'https://graph.test']);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->staff = function (array $perms = [], ?string $role = null, string $email = 's@egy.test') {
        $a = Admin::create(['name' => 'S '.$email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        $role && $a->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->product = listProduct(Product::create(['type' => 'simple', 'name' => ['en' => 'Ad item', 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => 'ad-item', 'sku' => 'AD-1',
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '100', 'cost' => '60', 'is_active' => true]);
    $this->input = fn (array $o = []) => array_replace(['name' => 'Eid sale', 'objective' => 'OUTCOME_SALES', 'product_ids' => [$this->product->id], 'budget_type' => 'daily', 'budget_amount' => 200,
        'start_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'copy' => ['primary_text' => 'جرب الآن', 'headline' => 'عرض العيد'], 'cta' => 'SHOP_NOW',
        'audience' => ['age_min' => 21, 'age_max' => 45, 'genders' => 'female'], 'locations' => ['countries' => ['eg']], 'expected_kpis' => ['ctr' => '1.5%'], 'risks' => 'Low stock', 'assumptions' => 'Eid demand'], $o);
    $this->drafts = app(MetaCampaignDraftService::class);
    $this->draft = fn (array $o = []) => $this->drafts->create(MetaCampaignDraftData::fromArray(($this->input)($o)), SuggestionActor::admin($this->owner))[0];
    $this->approved = function (array $o = []) {
        $d = ($this->draft)($o);
        $this->drafts->submit($d, SuggestionActor::admin($this->owner));
        $this->drafts->approve($d->fresh(), $this->owner);

        return $d->fresh();
    };
    // Graph fake: creates return sequential ids, reads return PAUSED (or $this->statusOverride), $this->failEdge fails once
    $this->failEdge = null;
    $this->statusOverride = null;
    $this->fake = function () {
        Http::fake(function (HttpRequest $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($r->method() === 'POST') {
                $edge = basename($path);
                if ($this->failEdge === $edge) {
                    $this->failEdge = null;

                    return Http::response(['error' => ['message' => 'Invalid parameter, token '.META_TEST_TOKEN, 'code' => 100, 'fbtrace_id' => 'abc']], 400);
                }

                return $edge === 'adimages' ? Http::response(['images' => ['f.jpg' => ['hash' => 'imghash1']]]) : Http::response(['id' => ['campaigns' => '1001', 'adsets' => '1002', 'adcreatives' => '1003', 'ads' => '1004'][$edge]]);
            }

            return Http::response(['id' => basename($path), 'status' => $this->statusOverride ?? 'PAUSED', 'effective_status' => 'PAUSED']);
        });
    };
    $this->posts = fn () => collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn ($r) => $r->method() === 'POST')->values();
});

it('creates campaign, ad set, creative and ad all PAUSED, verifies statuses and stores the Meta ids', function () {
    ($this->fake)();
    $d = ($this->approved)();
    $a = app(MetaPublisher::class)->publish($d, $this->owner);
    $d->refresh();
    expect($a->execution_status)->toBe('succeeded')->and($d->status->value)->toBe('published')->and([$d->meta_campaign_id, $d->meta_adset_id, $d->meta_creative_id, $d->meta_ad_id])->toBe(['1001', '1002', '1003', '1004'])
        ->and($d->meta_statuses['campaign']['status'])->toBe('PAUSED')->and($d->meta_statuses['adset']['status'])->toBe('PAUSED')->and($d->meta_statuses['ad']['status'])->toBe('PAUSED')
        ->and($d->published_by)->toBe($this->owner->id)->and($d->hasMetaObjects())->toBeTrue()->and($a->external_reference['campaign_id'])->toBe('1001');

    $posts = ($this->posts)();
    expect($posts->map(fn ($r) => basename(parse_url($r->url(), PHP_URL_PATH)))->all())->toBe(['campaigns', 'adsets', 'adcreatives', 'ads']);
    foreach ($posts as $r) {
        expect(parse_url($r->url(), PHP_URL_PATH))->toStartWith('/v99.0/act_111/')->and($r->url())->not->toContain(META_TEST_TOKEN)
            ->and($r->hasHeader('Authorization', 'Bearer '.META_TEST_TOKEN))->toBeTrue()->and($r['appsecret_proof'])->toBe(hash_hmac('sha256', META_TEST_TOKEN, META_TEST_SECRET));
        expect(strtoupper($r->body()))->not->toContain('ACTIVE');
    }
    expect($posts[0]['status'])->toBe('PAUSED')->and($posts[0]['objective'])->toBe('OUTCOME_SALES')->and($posts[1]['status'])->toBe('PAUSED')->and($posts[1]['campaign_id'])->toBe('1001')
        ->and((int) $posts[1]['daily_budget'])->toBe(20000)->and($posts[3]['status'])->toBe('PAUSED')->and($posts[3]['adset_id'])->toBe('1002');
    $targeting = json_decode($posts[1]['targeting'], true);
    expect($targeting['geo_locations']['countries'])->toBe(['EG'])->and($targeting['age_min'])->toBe(21)->and($targeting['genders'])->toBe([2]);
    $spec = json_decode($posts[2]['object_story_spec'], true);
    expect($spec['page_id'])->toBe('222')->and($spec['link_data']['link'])->toContain('/product/ad-item')->and($spec['link_data']['message'])->toBe('جرب الآن')->and($spec['link_data']['call_to_action']['type'])->toBe('SHOP_NOW');
    // every status read back; nothing but GET / POST
    expect(collect(Http::recorded())->map(fn ($p) => $p[0]->method())->unique()->sort()->values()->all())->toBe(['GET', 'POST'])
        ->and(collect(Http::recorded())->filter(fn ($p) => $p[0]->method() === 'GET')->count())->toBe(3);

    // audit: every step, no secret anywhere
    $actions = MetaAuditLog::where('draft_id', $d->id)->pluck('action')->all();
    expect($actions)->toContain('draft_created', 'approval_requested', 'approved', 'publish_started', 'graph_create', 'published_paused');
    $dump = MetaAuditLog::all()->toJson().MetaCampaignApproval::all()->toJson().$d->fresh()->toJson();
    expect($dump)->not->toContain(META_TEST_TOKEN)->not->toContain(META_TEST_SECRET);
});

it('has no code path that activates, changes budgets or deletes', function () {
    // the client exposes only reads and guarded creates
    $public = collect((new ReflectionClass(MetaGraphClient::class))->getMethods(ReflectionMethod::IS_PUBLIC))->map->getName()->reject(fn ($m) => $m === '__construct')->sort()->values()->all();
    expect($public)->toBe(['accountDigits', 'accountId', 'configured', 'create', 'get', 'pageConfigured']);
    foreach (['Publisher' => MetaPublisher::class, 'Read' => MetaReadService::class] as $class) {
        foreach ((new ReflectionClass($class))->getMethods() as $m) {
            expect(strtolower($m->getName()))->not->toMatch('/activate|resume|delete|setbudget|updatebudget/');
        }
    }
    foreach (['app/Services/Marketing/Meta/MetaGraphClient.php', 'app/Services/Marketing/Meta/MetaPublisher.php', 'app/Services/Marketing/Meta/MetaReadService.php'] as $f) {
        $src = file_get_contents(base_path($f));
        expect($src)->not->toMatch("/'status'\s*=>\s*'(ACTIVE|ARCHIVED|DELETED)'/")->not->toMatch('/->(delete|put|patch)\(\s*\$?[\'"a-z]*path/i');
    }
    Http::fake();
    $client = app(MetaGraphClient::class);
    foreach ([
        ['campaigns', ['name' => 'x', 'status' => 'ACTIVE']],
        ['campaigns', ['name' => 'x']],
        ['adsets', ['status' => 'active']],
        ['ads', ['status' => 'ARCHIVED']],
        ['campaigns/123', ['status' => 'PAUSED']],
        ['123', ['status' => 'PAUSED']],
        ['customaudiences', ['name' => 'x']],
        ['campaigns', ['status' => 'PAUSED', 'spend_cap' => 1]],
        ['campaigns', ['status' => 'PAUSED', 'execution_options' => ['validate_only']]],
        ['adcreatives', ['status' => 'ACTIVE']],
    ] as [$edge, $params]) {
        expect(fn () => $client->create($edge, $params))->toThrow(MetaException::class);
    }
    foreach (['me/../x', 'https://evil.test/x', 'act_1?access_token=x', ''] as $bad) {
        expect(fn () => $client->get($bad))->toThrow(MetaException::class);
    }
    Http::assertNothingSent();
    // routes: no activate / budget / delete anywhere near Meta; the API has no approve / reject / publish
    $routes = collect(Route::getRoutes()->getRoutes());
    $metaUris = $routes->filter(fn ($r) => str_contains($r->uri(), 'meta-ads') || str_contains($r->uri(), 'marketing/meta'));
    expect($metaUris->count())->toBeGreaterThan(15)->and($metaUris->filter(fn ($r) => preg_match('/activate|budget|delete/i', $r->uri()) || in_array('DELETE', $r->methods(), true))->count())->toBe(0);
    $api = $routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/internal/marketing/meta'));
    expect($api->filter(fn ($r) => preg_match('/approve|reject|publish/', $r->uri()))->count())->toBe(0)
        ->and($routes->first(fn ($r) => str_ends_with($r->uri(), 'meta-ads/{id}/publish'))->gatherMiddleware())->toContain('can:meta_ads.publish');
});

it('binds approval to version + hash: edit invalidates, tampering and expiry block publishing', function () {
    ($this->fake)();
    $pub = app(MetaPublisher::class);
    // no approval
    $d = ($this->draft)();
    expect(fn () => $pub->publish($d, $this->owner))->toThrow(MarketingActionException::class);
    // approve refused unless submitted
    expect(fn () => $this->drafts->approve($d->fresh(), $this->owner))->toThrow(MarketingActionException::class);

    $d = ($this->approved)(['name' => 'Second']);
    $hash = $d->content_hash;
    expect($d->validApproval()?->content_hash)->toBe($hash)->and($d->validApproval()->draft_version)->toBe(1);
    // a no-op edit changes nothing
    [$same] = $this->drafts->update($d, ['name' => 'Second'], SuggestionActor::admin($this->owner));
    expect($same->fresh()->status->value)->toBe('approved')->and($same->fresh()->version)->toBe(1);
    // a real edit: version + 1, new hash, approval invalidated, back to draft
    [$e] = $this->drafts->update($d->fresh(), ['budget_amount' => 300], SuggestionActor::admin($this->owner));
    $e->refresh();
    expect($e->status->value)->toBe('draft')->and($e->version)->toBe(2)->and($e->content_hash)->not->toBe($hash)->and($e->validApproval())->toBeNull()
        ->and(MetaCampaignApproval::where('draft_id', $e->id)->where('approval_status', 'invalidated')->where('invalidated_reason', 'edited')->count())->toBe(1);
    expect(fn () => $pub->publish($e, $this->owner))->toThrow(fn (MarketingActionException $x) => expect($x->errorCode)->toBe('approval_required'));

    // tampered row (budget changed directly in the DB after approval)
    $t = ($this->approved)(['name' => 'Third']);
    DB::table('meta_campaign_drafts')->where('id', $t->id)->update(['budget_amount' => 9999]);
    expect(fn () => $pub->publish($t->fresh(), $this->owner))->toThrow(fn (MarketingActionException $x) => expect($x->errorCode)->toBe('hash_mismatch'));

    // expired approval
    $x = ($this->approved)(['name' => 'Fourth']);
    $this->travel((int) config('meta.approval_valid_hours') + 1)->hours();
    expect(fn () => $pub->publish($x->fresh(), $this->owner))->toThrow(fn (MarketingActionException $y) => expect($y->errorCode)->toBe('approval_required'));
    $this->travelBack();
    Http::assertNothingSent();

    // not configured: refused before any call
    $c = ($this->approved)(['name' => 'Fifth']);
    config(['meta.access_token' => null]);
    expect(fn () => $pub->publish($c->fresh(), $this->owner))->toThrow(fn (MarketingActionException $y) => expect($y->errorCode)->toBe('not_configured'));
    Http::assertNothingSent();
});

it('is idempotent and resumes after a failure without duplicating objects', function () {
    ($this->fake)();
    $pub = app(MetaPublisher::class);
    $d = ($this->approved)();
    $pub->publish($d, $this->owner);
    $n = ($this->posts)()->count();
    $again = $pub->publish($d->fresh(), $this->owner);
    expect($again->execution_status)->toBe('succeeded')->and(($this->posts)()->count())->toBe($n);

    // failure on the ad set: campaign id kept, error scrubbed, retry resumes
    Http::swap(new Illuminate\Http\Client\Factory);
    ($this->fake)();
    $this->failEdge = 'adsets';
    $f = ($this->approved)(['name' => 'Failing']);
    $res = $pub->publish($f, $this->owner);
    $f->refresh();
    expect($res->execution_status)->toBe('failed')->and($f->status->value)->toBe('failed')->and($f->meta_campaign_id)->toBe('1001')->and($f->meta_adset_id)->toBeNull()
        ->and($f->last_error)->not->toContain(META_TEST_TOKEN)->toContain('[redacted]')->and($res->execution_error)->not->toContain(META_TEST_TOKEN)->and(MetaAuditLog::where('action', 'publish_failed')->count())->toBe(1);
    // frozen: objects exist in Meta
    expect(fn () => $this->drafts->update($f, ['budget_amount' => 300], SuggestionActor::admin($this->owner)))->toThrow(fn (MarketingActionException $x) => expect($x->errorCode)->toBe('frozen'));
    $ok = $pub->publish($f, $this->owner);
    $edges = ($this->posts)()->map(fn ($r) => basename(parse_url($r->url(), PHP_URL_PATH)))->all();
    expect($ok->execution_status)->toBe('succeeded')->and($f->fresh()->status->value)->toBe('published')->and(array_count_values($edges)['campaigns'])->toBe(1)->and(array_count_values($edges)['ads'])->toBe(1);
});

it('releases a run that died mid-way and resumes it without duplicating', function () {
    ($this->fake)();
    $pub = app(MetaPublisher::class);
    $d = ($this->approved)();
    DB::table('meta_campaign_drafts')->where('id', $d->id)->update(['status' => 'publishing', 'meta_campaign_id' => '1001', 'updated_at' => now()]);
    MetaCampaignApproval::where('draft_id', $d->id)->update(['execution_status' => 'running']);
    // still fresh: a second click is refused, nothing sent
    expect(fn () => $pub->publish($d->fresh(), $this->owner))->toThrow(fn (MarketingActionException $x) => expect($x->errorCode)->toBe('in_progress'));
    Http::assertNothingSent();
    DB::table('meta_campaign_drafts')->where('id', $d->id)->update(['updated_at' => now()->subMinutes(MetaPublisher::STALE_MINUTES + 1)]);
    expect($pub->publish($d->fresh(), $this->owner)->execution_status)->toBe('succeeded')->and(MetaAuditLog::where('action', 'publish_interrupted')->count())->toBe(1);
    expect(($this->posts)()->map(fn ($r) => basename(parse_url($r->url(), PHP_URL_PATH)))->all())->toBe(['adsets', 'adcreatives', 'ads']);
});

it('fails loudly when Meta does not report PAUSED and never tries to change it', function () {
    ($this->fake)();
    $this->statusOverride = 'ACTIVE';
    $d = ($this->approved)();
    $res = app(MetaPublisher::class)->publish($d, $this->owner);
    $d->refresh();
    expect($res->execution_status)->toBe('failed')->and($d->status->value)->toBe('failed')->and($d->meta_statuses['campaign']['status'])->toBe('ACTIVE')->and(MetaAuditLog::where('action', 'status_not_paused')->count())->toBe(1);
    expect(($this->posts)()->count())->toBe(4);   // only the four creates
});

it('uploads an approved library image as the creative', function () {
    Illuminate\Support\Facades\Storage::fake(config('media.private_disk'));
    ($this->fake)();
    $this->actingAs($this->owner, 'admin');
    $id = $this->post('/marketer/media', ['product_id' => $this->product->id, 'file' => Illuminate\Http\UploadedFile::fake()->image('ad.jpg', 600, 600)], ['Accept' => 'application/json'])->assertOk()->json('data.id');
    // a library item that is not approved cannot be approved into a campaign
    $d = ($this->draft)(['creative' => ['asset_ids' => [$id]]]);
    $this->drafts->submit($d, SuggestionActor::admin($this->owner));
    expect(fn () => $this->drafts->approve($d->fresh(), $this->owner))->toThrow(MarketingActionException::class);
    $this->postJson("/marketer/media/$id/approve")->assertOk();
    $this->drafts->approve($d->fresh(), $this->owner);
    app(MetaPublisher::class)->publish($d->fresh(), $this->owner);
    $posts = ($this->posts)();
    expect(basename(parse_url($posts[2]->url(), PHP_URL_PATH)))->toBe('adimages')->and(json_decode($posts[3]['object_story_spec'], true)['link_data']['image_hash'])->toBe('imghash1')->and($d->fresh()->meta_image_hash)->toBe('imghash1');
});

it('enforces the permissions matrix in the panel', function () {
    ($this->fake)();
    $d = ($this->approved)();
    $pending = ($this->draft)(['name' => 'Pending']);
    $this->drafts->submit($pending, SuggestionActor::admin($this->owner));
    $valid = ($this->input)(['name' => 'From panel', 'placements' => ['automatic' => '1']]);

    $viewer = ($this->staff)(['meta_ads.view'], null, 'v@egy.test');
    $this->actingAs($viewer, 'admin');
    $this->get('/marketer/meta-ads')->assertOk();
    $this->get("/marketer/meta-ads/{$d->id}")->assertOk()->assertDontSee('data-action="publish"', false)->assertDontSee('data-action="edit"', false);
    $this->get('/marketer/meta-ads/create')->assertForbidden();
    $this->postJson('/marketer/meta-ads', $valid)->assertForbidden();
    $this->postJson("/marketer/meta-ads/{$pending->id}/approve")->assertForbidden();
    $this->postJson("/marketer/meta-ads/{$d->id}/publish")->assertForbidden();

    $drafter = ($this->staff)(['meta_ads.view', 'meta_ads.draft'], null, 'd@egy.test');
    $this->actingAs($drafter, 'admin');
    $this->postJson('/marketer/meta-ads/validate', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name', 'objective', 'product_ids']);
    $this->postJson('/marketer/meta-ads/validate', $valid)->assertOk();
    $this->postJson('/marketer/meta-ads', $valid)->assertOk();
    $this->postJson("/marketer/meta-ads/{$pending->id}/approve")->assertForbidden();
    $this->postJson("/marketer/meta-ads/{$pending->id}/reject")->assertForbidden();

    // the preset marketer role approves but can never publish
    $marketer = ($this->staff)([], 'marketer', 'm@egy.test');
    $this->actingAs($marketer, 'admin');
    expect($marketer->can('meta_ads.approve'))->toBeTrue()->and($marketer->can('meta_ads.publish'))->toBeFalse();
    $this->postJson("/marketer/meta-ads/{$pending->id}/approve")->assertOk();
    $this->get("/marketer/meta-ads/{$d->id}")->assertOk()->assertDontSee('data-action="publish"', false);
    $this->postJson("/marketer/meta-ads/{$d->id}/publish")->assertForbidden();
    expect(fn () => app(MetaPublisher::class)->publish($d->fresh(), $marketer))->toThrow(MarketingActionException::class);   // service checks independently
    expect(($this->posts)()->count())->toBe(0);   // the index page reads (GET) only; nothing was created

    $this->actingAs($this->owner, 'admin');
    $this->get("/marketer/meta-ads/{$d->id}")->assertOk()->assertSee('data-action="publish"', false);
    $this->postJson("/marketer/meta-ads/{$d->id}/publish")->assertOk();
    expect($d->fresh()->status->value)->toBe('published');
});

it('validates business rules of a draft', function () {
    $save = fn (array $o) => $this->actingAs($this->owner, 'admin')->postJson('/marketer/meta-ads', ($this->input)($o));
    $save(['budget_amount' => 1])->assertStatus(422)->assertJsonValidationErrors(['budget_amount']);
    $save(['budget_type' => 'lifetime', 'end_at' => null])->assertStatus(422)->assertJsonValidationErrors(['end_at']);
    $save(['product_ids' => [999999]])->assertStatus(422)->assertJsonValidationErrors(['product_ids']);
    $save(['landing_url' => 'https://evil.test/x'])->assertStatus(422)->assertJsonValidationErrors(['landing_url']);
    $save(['landing_url' => '/admin'])->assertStatus(422)->assertJsonValidationErrors(['landing_url']);
    $save(['objective' => 'LINK_CLICKS'])->assertStatus(422)->assertJsonValidationErrors(['objective']);
    $save(['cta' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['cta']);
    $save(['audience' => ['age_min' => 50, 'age_max' => 20]])->assertStatus(422)->assertJsonValidationErrors(['audience.age_min']);
    $save(['creative' => ['canva_url' => 'https://evil.test/d']])->assertStatus(422)->assertJsonValidationErrors(['creative.canva_url']);
    $save(['creative' => ['asset_ids' => [12345]]])->assertStatus(422)->assertJsonValidationErrors(['creative.asset_ids']);
    $id = $save(['copy' => ['primary_text' => '<script>x</script>نص']])->assertOk()->json('redirect');
    $d = MetaCampaignDraft::query()->latest('id')->first();
    expect($id)->toContain('/marketer/meta-ads/'.$d->id)->and($d->copy['primary_text'])->toBe('نص')->and($d->landing_url)->toBe('/ar/product/ad-item')->and($d->product_urls[0])->toContain('/ar/product/ad-item')->and($d->optimization_goal)->toBe('LINK_CLICKS');
});

it('renders every panel page (ar + en) with and without a Meta connection', function () {
    ($this->fake)();
    $d = ($this->approved)();
    config(['meta.access_token' => null]);
    $this->actingAs($this->owner, 'admin');
    foreach (['ar', 'en'] as $loc) {
        $this->withSession(['admin_locale' => $loc]);
        $this->get('/marketer/meta-ads')->assertOk()->assertSee('data-panel="marketer"', false)->assertSee('data-safety-notice', false)->assertSee('data-not-configured', false)->assertSee('data-draft="'.$d->id.'"', false);
        $this->get('/marketer/meta-ads/insights')->assertOk()->assertSee('data-not-configured', false);
        $this->get('/marketer/meta-ads/create?product_id='.$this->product->id)->assertOk()->assertSee('data-draft-form', false);
        $this->get("/marketer/meta-ads/{$d->id}")->assertOk()->assertSee('data-meta-draft', false)->assertSee('data-payload-preview', false)->assertDontSee('data-action="publish"', false);   // not configured
        $this->get("/marketer/meta-ads/{$d->id}/edit")->assertOk()->assertSee('data-draft-form', false);
    }
    $this->get('/marketer')->assertSee(route('marketer.meta-ads.index'), false);
    foreach ([$this->get('/marketer/meta-ads'), $this->get("/marketer/meta-ads/{$d->id}")] as $r) {
        expect($r->getContent())->not->toContain(META_TEST_SECRET);
    }
});

it('caches reads, normalises insights and never returns the token', function () {
    Http::fake(['*/insights*' => Http::response(['data' => [['spend' => '120.50', 'impressions' => '1000', 'reach' => '800', 'clicks' => '50', 'ctr' => '5.0', 'cpc' => '2.41', 'cpm' => '120.5', 'date_start' => '2026-10-01', 'date_stop' => '2026-10-30',
        'actions' => [['action_type' => 'purchase', 'value' => '4'], ['action_type' => 'omni_purchase', 'value' => '4'], ['action_type' => 'link_click', 'value' => '50']],
        'action_values' => [['action_type' => 'purchase', 'value' => '800']], 'purchase_roas' => [['action_type' => 'omni_purchase', 'value' => '6.6']]]]]),
        '*/campaigns*' => Http::response(['data' => [['id' => '9', 'name' => 'C', 'objective' => 'OUTCOME_SALES', 'status' => 'PAUSED', 'effective_status' => 'PAUSED', 'daily_budget' => '20000']], 'paging' => ['cursors' => ['after' => 'abc'], 'next' => 'https://x']]),
        '*' => Http::response(['id' => 'act_111', 'account_id' => '111', 'name' => 'Main', 'currency' => 'EGP', 'data' => [['id' => 'act_111', 'account_id' => '111', 'name' => 'Main', 'amount_spent' => '12345']]])]);
    $r = app(MetaReadService::class);
    $i = $r->insights();
    $r->insights();
    expect($i[0]['spend'])->toBe(120.5)->and($i[0]['conversions'])->toBe(4.0)->and($i[0]['conversion_value'])->toBe(800.0)->and($i[0]['roas'])->toBe(6.6);
    Http::assertSentCount(1);
    $c = $r->campaigns();
    expect($c['items'][0]['daily_budget'])->toBe(200.0)->and($c['items'][0]['daily_budget_minor'])->toBe(20000)->and($c['next_cursor'])->toBe('abc');
    expect($r->adAccounts()[0]['amount_spent'])->toBe(123.45);
    Http::assertSent(fn (HttpRequest $q) => ! str_contains($q->url(), META_TEST_TOKEN) && $q->hasHeader('Authorization', 'Bearer '.META_TEST_TOKEN));
});

it('exposes reads and own-draft writes on the internal API, never approve or publish', function () {
    Http::fake(['*' => Http::response(['data' => []])]);
    $svc = app(MarketingTokenService::class);
    [$rt, $read] = $svc->create(new MarketingTokenData('r', ['meta:read'], 30, null));
    [, $write] = $svc->create(new MarketingTokenData('w', ['meta:read', 'meta:draft'], 30, null));
    [, $other] = $svc->create(new MarketingTokenData('o', ['meta:read', 'meta:draft'], 30, null));
    [, $mkt] = $svc->create(new MarketingTokenData('m', ['marketing:read'], 30, null));
    $b = '/api/internal/marketing/meta';
    $h = fn ($t) => ['Authorization' => 'Bearer '.$t];
    $this->getJson("$b/status")->assertUnauthorized();
    $this->getJson("$b/campaigns", $h($mkt))->assertForbidden();
    $st = $this->getJson("$b/status", $h($read))->assertOk()->assertJsonPath('data.configured', true)->assertJsonPath('data.safety.activation_supported', false);
    expect($st->getContent())->not->toContain(META_TEST_TOKEN)->not->toContain(META_TEST_SECRET);
    foreach (['ad-accounts', 'account', 'campaigns', 'adsets?campaign_id=12', 'ads?adset_id=12', 'insights?level=campaign&date_preset=last_7d', 'insights?from=2026-10-01&to=2026-10-30', 'drafts'] as $u) {
        $this->getJson("$b/$u", $h($read))->assertOk()->assertJsonPath('success', true);
    }
    $this->getJson("$b/insights?date_preset=forever", $h($read))->assertStatus(422);
    $this->getJson("$b/adsets?campaign_id=../x", $h($read))->assertStatus(422);

    $body = ($this->input)();
    $this->postJson("$b/drafts", $body, $h($read))->assertForbidden();                       // read scope cannot write
    $this->postJson("$b/drafts", $body + ['status' => 'approved'], $h($write))->assertStatus(422)->assertJsonValidationErrors(['status']);   // unknown field
    $c = $this->postJson("$b/drafts", $body, $h($write))->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.own', true)->assertJsonPath('data.safety.created_status', 'PAUSED');
    $id = $c->json('data.id');
    expect(MetaCampaignDraft::find($id)->created_by_token_id)->toBeInt();
    $this->patchJson("$b/drafts/$id", ['budget_amount' => 300], $h($other))->assertForbidden()->assertJsonPath('error', 'not_owner');
    $this->patchJson("$b/drafts/$id", ['budget_amount' => 1], $h($write))->assertStatus(422);
    $this->patchJson("$b/drafts/$id", ['budget_amount' => 300], $h($write))->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.budget_amount', '300.00');
    $this->postJson("$b/drafts/$id/submit", [], $h($other))->assertForbidden();
    $this->postJson("$b/drafts/$id/submit", [], $h($write))->assertOk()->assertJsonPath('data.status', 'pending_approval');
    expect(MetaCampaignApproval::where('draft_id', $id)->where('approval_status', 'pending')->whereNotNull('requested_by_token_id')->count())->toBe(1);
    $this->getJson("$b/drafts/$id", $h($read))->assertOk()->assertJsonPath('data.approvals.0.status', 'pending')->assertJsonMissingPath('data.access_token');
    $this->getJson("$b/drafts/999999", $h($read))->assertNotFound();
    // the token can never approve / publish / activate: nothing answers
    foreach (['approve', 'reject', 'publish', 'activate'] as $a) {
        $this->postJson("$b/drafts/$id/$a", [], $h($write))->assertStatus(403);    // fallback route needs marketing:read, which this token lacks
        $this->postJson("$b/drafts/$id/$a", [], $h($mkt))->assertNotFound();
    }
    // after approval a token can no longer edit it
    $this->drafts->approve(MetaCampaignDraft::find($id), $this->owner);
    $this->patchJson("$b/drafts/$id", ['budget_amount' => 400], $h($write))->assertStatus(409);
    expect(MetaCampaignDraft::find($id)->status->value)->toBe('approved');
    expect(MetaAuditLog::where('draft_id', $id)->where('actor_type', 'token')->where('action', 'draft_created')->count())->toBe(1);
});
