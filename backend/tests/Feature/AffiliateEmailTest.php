<?php

use App\Models\Affiliate;
use App\Models\Tenant;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->form = fn (string $email) => ['name' => 'Aff', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123'];
});

it('normalises e-mail on write and rejects case/space variants on web, pre-validate and API', function () {
    $this->postJson("{$this->s}/affiliate/register", ($this->form)('  New.Aff@Example.COM '))->assertSuccessful();
    expect(Affiliate::where('name', 'Aff')->value('email'))->toBe('new.aff@example.com');

    foreach (['new.aff@example.com', 'NEW.AFF@EXAMPLE.COM', ' New.Aff@example.com '] as $dup) {
        $this->postJson("{$this->s}/affiliate/register", ($this->form)($dup))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson("{$this->s}/affiliate/register/validate", ($this->form)($dup))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson("{$this->api}/affiliate/register", ($this->form)($dup))->assertStatus(422);
    }
    expect(Affiliate::where('email', 'new.aff@example.com')->count())->toBe(1);
});

it('the same e-mail is allowed in another tenant, and the model mutator normalises direct writes', function () {
    $other = Tenant::create(['slug' => 'ksa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $a = Affiliate::create(['name' => 'A', 'email' => 'Shared@X.com', 'password' => 'password123']);
    app()->instance('currentTenant', $other);
    $b = Affiliate::create(['name' => 'B', 'email' => ' shared@x.COM', 'password' => 'password123']);
    expect($a->email)->toBe('shared@x.com')->and($b->email)->toBe('shared@x.com')->and($b->tenant_id)->toBe($other->id);
    expect(fn () => Affiliate::create(['name' => 'C', 'email' => 'SHARED@x.com', 'password' => 'password123']))->toThrow(\Illuminate\Database\QueryException::class);
});

it('emailRules ignores the affiliate itself on update but not others', function () {
    $a = Affiliate::create(['name' => 'A', 'email' => 'me@x.com', 'password' => 'password123']);
    Affiliate::create(['name' => 'B', 'email' => 'other@x.com', 'password' => 'password123']);
    $v = fn (string $e, ?int $ignore) => validator(['email' => $e], ['email' => Affiliate::emailRules($ignore)])->passes();
    expect($v('me@x.com', $a->id))->toBeTrue()->and($v('me@x.com', null))->toBeFalse()->and($v('other@x.com', $a->id))->toBeFalse();
});

it('login keeps working with the normalised e-mail (any input casing)', function () {
    Affiliate::create(['name' => 'L', 'email' => 'Login@X.com', 'password' => 'password123', 'status' => 'approved']);
    $this->postJson("{$this->api}/affiliate/login", ['email' => ' LOGIN@x.com ', 'password' => 'password123'])->assertSuccessful();
});

it('migration normalises stored e-mails and tombstones collisions deterministically (oldest wins)', function () {
    DB::table('affiliates')->where('tenant_id', $this->tenant->id)->delete();
    $row = fn (string $e) => DB::table('affiliates')->insertGetId(['tenant_id' => $this->tenant->id, 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'x', 'email' => $e, 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    $old = $row('Dup@X.com');
    $new = $row(' dup@x.com'); // leading space: not equal for the unique index, equal after normalising
    (include base_path('database/migrations/2026_10_27_100001_affiliate_email_unique.php'))->up();
    expect(DB::table('affiliates')->where('id', $old)->value('email'))->toBe('dup@x.com')
        ->and(DB::table('affiliates')->where('id', $new)->value('email'))->toBe("dup+dup{$new}@x.com");
});
