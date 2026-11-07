<?php

use App\Models\City;
use App\Models\Country;
use Database\Seeders\CitySeeder;

it('seeds all countries and every city of the site country idempotently', function () {
    settings(['country' => 'EG']);
    $this->seed(CitySeeder::class);
    $this->seed(CitySeeder::class);

    expect(Country::count())->toBeGreaterThan(240)
        ->and(Country::where('code', 'EG')->first()->getTranslations('name')['ar'])->toBe('مصر');

    $cities = City::query()->get();
    expect($cities->count())->toBeGreaterThan(200)
        ->and($cities->pluck('state')->filter()->count())->toBe($cities->count() - 0 - $cities->whereNull('state')->count())
        ->and($cities->filter(fn ($c) => $c->getTranslations('name')['en'] === 'Cairo')->count())->toBe(1);
});
