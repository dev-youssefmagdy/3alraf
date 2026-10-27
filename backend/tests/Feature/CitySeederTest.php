<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Tenant;
use Database\Seeders\CitySeeder;

it('seeds all countries and every Egyptian city idempotently', function () {
    $this->seed(CitySeeder::class);
    $this->seed(CitySeeder::class);

    expect(Country::count())->toBeGreaterThan(240)
        ->and(Country::where('code', 'EG')->first()->getTranslations('name')['ar'])->toBe('مصر');

    $egy = Tenant::where('slug', 'egy')->first();
    $cities = City::withoutGlobalScopes()->where('tenant_id', $egy->id)->get();
    expect($cities->count())->toBeGreaterThan(200)
        ->and($cities->pluck('state')->filter()->count())->toBe($cities->count() - 0 - $cities->whereNull('state')->count())
        ->and($cities->filter(fn ($c) => $c->getTranslations('name')['en'] === 'Cairo')->count())->toBe(1);
});
