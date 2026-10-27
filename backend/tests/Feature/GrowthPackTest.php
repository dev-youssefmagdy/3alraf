<?php

use App\Models\Interaction;
use App\Models\SmallBatchSignup;
use App\Models\Tenant;
use App\Services\Growth\GrowthRetentionService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
});

it('ships valid workflow JSONs with no secrets covering every built feature', function () {
    $files = glob(base_path('docs/n8n/workflows/*.json'));
    expect($files)->not->toBeEmpty();
    $covered = [];
    foreach ($files as $f) {
        $raw = file_get_contents($f);
        $j = json_decode($raw, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($raw)->not->toMatch('/Bearer [A-Za-z0-9]{20,}/');
        $covered = array_merge($covered, $j['meta']['features'] ?? []);
    }
    $built = array_keys(array_filter(config('growth.features'), fn ($f) => $f['built']));
    expect(array_values(array_diff($built, $covered)))->toBe([]);
});

it('prunes old growth data per tenant retention and erases a subject', function () {
    $old = now()->subDays(400);
    DB::table('interactions')->insert(['tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'external_id' => 'e1', 'thread_id' => 't1', 'text_masked' => 'x', 'text_hash' => str_repeat('a', 40), 'status' => 'new', 'received_at' => now(), 'created_at' => $old, 'updated_at' => $old]);
    DB::table('interactions')->insert(['tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'external_id' => 'e2', 'thread_id' => 't2', 'text_masked' => 'x', 'text_hash' => str_repeat('b', 40), 'status' => 'new', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $r = app(GrowthRetentionService::class)->prune($this->tenant);
    expect($r['interactions'])->toBe(1)->and(Interaction::withoutGlobalScopes()->count())->toBe(1);
    $e = app(GrowthRetentionService::class)->erase($this->tenant, 't2');
    expect($e['interactions'])->toBe(1)->and(Interaction::withoutGlobalScopes()->count())->toBe(0);
});

it('health endpoint requires auth and the allowlist blocks other IPs', function () {
    $this->getJson('/api/integration/v1/health')->assertStatus(401);
    config(['growth.ip_allowlist' => ['10.9.9.9']]);
    $this->getJson('/api/integration/v1/health')->assertStatus(403)->assertJson(['error' => 'ip_not_allowed']);
});
