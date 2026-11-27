<?php

// Product AI title ideas (PRODUCT_AI_FREELLMAPI_PLAN.md §5.5): validated ar/en lists, apply() writes one locale of the product or
// variant name with an activity-log entry, and ProductVariant::label() shows the variant name when it is set.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Services\ProductAi\TitleSuggestionService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->product = Product::create(['type' => 'variable', 'name' => ['en' => 'Silk Scrunchies', 'ar' => 'توك شعر حرير'], 'slug' => 'silk', 'sku' => 'SCR']);
    $attr = Attribute::create(['name' => ['en' => 'Color', 'ar' => 'اللون'], 'code' => 'color', 'type' => 'color']);
    $red = AttributeValue::create(['attribute_id' => $attr->id, 'value' => ['en' => 'Red', 'ar' => 'أحمر']]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'combination_hash' => (string) $red->id, 'sku' => 'SCR-RED',
        'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->variant->values()->attach($red->id);
    $this->titles = app(TitleSuggestionService::class);
});

it('keeps 1 to 5 clean titles of 10 to 120 characters per language and rejects an answer with an empty language', function () {
    $ok = TitleSuggestionService::validate([
        'ar' => ['<b>توك شعر حرير أحمر</b> من جلام', 'قصير', str_repeat('ع', 121), '1. توك شعر حرير ناعم للشعر', 'توك شعر حرير أحمر من جلام', 'Silk scrunchie English', 'توك ٣', 'توك شعر حرير لون أحمر 6 قطع', 'توك شعر ساتان أحمر فاخر', 'توك شعر حرير طبيعي للنوم', 'سادس عنوان عربي طويل كفاية'],
        'en' => ['"Glam Silk Scrunchies, Red"', 'Too short', 42],
    ]);

    expect($ok['ar'])->toBe(['توك شعر حرير أحمر من جلام', 'توك شعر حرير ناعم للشعر', 'توك شعر حرير لون أحمر 6 قطع', 'توك شعر ساتان أحمر فاخر', 'توك شعر حرير طبيعي للنوم'])
        ->and($ok['en'])->toBe(['Glam Silk Scrunchies, Red'])
        ->and(TitleSuggestionService::validate(['ar' => ['توك شعر حرير أحمر من جلام'], 'en' => ['short']]))->toBeNull()
        ->and(TitleSuggestionService::validate(['en' => ['Glam Silk Scrunchies, Red']]))->toBeNull();
});

it('saves a title into one locale of the product name only, and logs it', function () {
    $this->titles->apply($this->product, 'product', null, 'en', 'Glam Silk Scrunchies, Soft Hair Ties', $this->owner);

    $p = $this->product->fresh();
    expect($p->getTranslations('name'))->toEqual(['en' => 'Glam Silk Scrunchies, Soft Hair Ties', 'ar' => 'توك شعر حرير']);
    $log = Activity::query()->where('log_name', 'product_ai')->sole();
    expect($log->description)->toBe('product_ai.title_applied')->and($log->subject_id)->toBe($p->id)->and($log->causer_id)->toBe($this->owner->id)
        ->and($log->properties->toArray())->toMatchArray(['target_type' => 'product', 'locale' => 'en', 'old' => 'Silk Scrunchies', 'new' => 'Glam Silk Scrunchies, Soft Hair Ties']);
});

it('saves a variant name for one locale; label() shows it in that locale and the attribute values elsewhere', function () {
    expect($this->variant->label())->toBe(app()->getLocale() === 'en' ? 'Red' : 'أحمر');   // no name yet: the current behaviour

    $res = $this->titles->apply($this->product, 'variant', $this->variant->id, 'ar', 'توك شعر حرير أحمر من جلام', $this->owner);

    $v = $this->variant->fresh('values');
    expect($v->getTranslations('name'))->toBe(['ar' => 'توك شعر حرير أحمر من جلام'])->and($res['target']['current'])->toBe(['ar' => 'توك شعر حرير أحمر من جلام', 'en' => null]);
    app()->setLocale('ar');
    expect($v->label())->toBe('توك شعر حرير أحمر من جلام');
    app()->setLocale('en');
    expect($v->label())->toBe('Red')
        ->and(Activity::query()->where('log_name', 'product_ai')->sole()->properties['target_id'])->toBe($v->id)
        ->and($this->product->fresh()->getTranslations('name'))->toEqual(['en' => 'Silk Scrunchies', 'ar' => 'توك شعر حرير']);
});

it('refuses a variant of another product, a bad locale and a title outside 10 to 120 characters', function () {
    $other = Product::create(['type' => 'simple', 'name' => ['en' => 'Other'], 'slug' => 'other', 'sku' => 'O']);
    $ov = ProductVariant::create(['product_id' => $other->id, 'combination_hash' => 'x', 'sku' => 'O-1', 'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);

    expect(fn () => $this->titles->apply($this->product, 'variant', $ov->id, 'en', 'A perfectly fine title'))->toThrow(ValidationException::class)
        ->and(fn () => $this->titles->apply($this->product, 'product', null, 'fr', 'A perfectly fine title'))->toThrow(ValidationException::class)
        ->and(fn () => $this->titles->apply($this->product, 'product', null, 'en', '<b>short</b>'))->toThrow(ValidationException::class);
    expect($ov->fresh()->getTranslations('name'))->toBe([])->and(Activity::query()->where('log_name', 'product_ai')->count())->toBe(0);
});
