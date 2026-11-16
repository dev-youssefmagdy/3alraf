<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use Database\Seeders\WatchAttributeSeeder;

it('seeds the watch attributes once, bilingual, colors with swatches, and is safe to re-run', function () {
    $this->seed(WatchAttributeSeeder::class);
    $attrs = Attribute::query()->where('code', 'like', 'watch\_%')->count();
    $values = AttributeValue::query()->count();

    $this->seed(WatchAttributeSeeder::class); // re-run: no duplicates

    expect($attrs)->toBe(28)
        ->and(Attribute::query()->where('code', 'like', 'watch\_%')->count())->toBe($attrs)
        ->and(AttributeValue::query()->count())->toBe($values)->toBe(338)
        ->and(AttributeValue::query()->whereHas('attribute', fn ($q) => $q->where('type', 'color'))->whereNull('color_hex')->count())->toBe(0);

    $a = Attribute::query()->where('code', 'watch_movement')->first();
    expect($a->getTranslations('name'))->toBe(['ar' => 'نوع الحركة (الماكينة)', 'en' => 'Movement'])
        ->and($a->is_draft)->toBeFalse()
        ->and($a->values->first()->getTranslations('value'))->toBe(['ar' => 'كوارتز (بطارية)', 'en' => 'Quartz (Battery)']);
});
