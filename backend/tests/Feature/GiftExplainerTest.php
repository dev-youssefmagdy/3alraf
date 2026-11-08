<?php

use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;
use App\Services\GiftFinder\GiftExplainer;
use Database\Seeders\FoundationSeeder;

// P1 (AI removal): the Gift Finder explanation is deterministic and built only from real data (fit, category, price vs budget, approved rating, popularity).

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->mk = fn (string $slug) => Product::create(['type' => 'simple', 'name' => ['en' => $slug, 'ar' => $slug], 'slug' => $slug, 'sku' => strtoupper($slug),
        'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()])->id;
    $this->row = fn (int $id, array $o = []) => $o + ['product_id' => $id, 'name' => 'Rose Box', 'price' => 90.0, 'category_match' => null,
        'recipient_match' => false, 'occasion_match' => false, 'sold' => 0, 'category' => null];
});

it('explains fit, price vs budget, rating and popularity in English without any AI call', function () {
    app()->setLocale('en');
    $c1 = Customer::create(['name' => 'A', 'email' => 'ge1@x.test', 'password' => 'password', 'is_active' => true]);
    $c2 = Customer::create(['name' => 'B', 'email' => 'ge2@x.test', 'password' => 'password', 'is_active' => true]);
    [$a, $b] = [($this->mk)('ge-rose'), ($this->mk)('ge-mug')];
    Review::create(['product_id' => $a, 'customer_id' => $c1->id, 'rating' => 5, 'status' => ReviewStatus::APPROVED]);
    Review::create(['product_id' => $a, 'customer_id' => $c2->id, 'rating' => 4, 'status' => ReviewStatus::APPROVED]);
    Review::create(['product_id' => $b, 'customer_id' => $c1->id, 'rating' => 1, 'status' => ReviewStatus::PENDING]);   // pending: ignored

    $out = app(GiftExplainer::class)->explain([
        ($this->row)($a, ['recipient_match' => true, 'occasion_match' => true, 'category_match' => true, 'sold' => 12]),
        ($this->row)($b, ['name' => 'Mug', 'price' => 30.0, 'category' => 'Kitchen']),
    ], 'mothers_day', 'mother', 100.0);

    expect($out[$a])->toContain('Rose Box')->toContain("Mother's Day")->toContain('Mother')->toContain('category you chose')
        ->toContain('good use of your')->toContain('Rated 4.5/5 by 2 customers')->toContain('most popular')
        ->and($out[$b])->toContain('Mug from our Kitchen range')->toContain('leaves 70')->not->toContain('Rated')->not->toContain('popular');
});

it('has Arabic text from the lang files and returns nothing for no rows', function () {
    app()->setLocale('ar');
    $out = app(GiftExplainer::class)->explain([($this->row)(7, ['recipient_match' => true])], null, 'mother', 100.0);
    expect($out[7])->toContain(__('gift_finder.recipients.mother'))->toContain('بسعر')
        ->and(app(GiftExplainer::class)->explain([], null, null, 50.0))->toBe([]);
});
