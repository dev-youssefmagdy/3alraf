<?php

use App\Models\Affiliate;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->s = '/ar';
    $this->api = '/api/v1';
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

it('the model mutator normalises direct writes and the unique index refuses a case/space variant', function () {
    $a = Affiliate::create(['name' => 'A', 'email' => 'Shared@X.com', 'password' => 'password123']);
    expect($a->email)->toBe('shared@x.com');
    expect(fn () => Affiliate::create(['name' => 'B', 'email' => ' shared@x.COM', 'password' => 'password123']))->toThrow(\Illuminate\Database\QueryException::class);
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

