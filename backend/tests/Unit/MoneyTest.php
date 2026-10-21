<?php

use App\Models\Language;
use App\Support\Money;

it('rounds half up and computes percent', function () {
    expect(Money::round('1.005'))->toBe('1.01')
        ->and(Money::percent('199.99', 15))->toBe('30.00')
        ->and(Money::add('0.1', '0.2'))->toBe('0.30')
        ->and(Money::mul('19.99', 3))->toBe('59.97')
        ->and(Money::sub('10', '12.5'))->toBe('-2.50')
        ->and(Money::cmp('1.001', '1.004'))->toBe(0);
});

it('translates json columns with fallback', function () {
    $l = new class extends Language {
        use App\Models\Concerns\HasTranslations;
        protected array $translatable = ['name'];
    };
    $l->setTranslations('name', ['ar' => 'مرحبا', 'en' => 'Hello']);
    app()->setLocale('ar');
    expect($l->name)->toBe('مرحبا');
    app()->setLocale('fr');
    expect($l->name)->toBe('Hello')->and($l->getTranslations('name'))->toHaveKeys(['ar', 'en']);
    app()->setLocale('en');
});
