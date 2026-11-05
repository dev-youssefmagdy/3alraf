<?php

namespace Database\Seeders;

use App\DTOs\BulkEnableData;
use App\Enums\ProductListType;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductListItem;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\BarcodeService;
use App\Services\Qr\QrCodeManager;
use App\Services\Stock\StockLedger;
use App\Services\TenantCatalogService;
use Database\Seeders\Support\PlaceholderImage;
use Database\Seeders\Support\RemoteImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gift store catalog: trending / social-media-viral gifts available in the Egyptian market (beauty & cosmetics ~half, all <= 500 EGP and mostly local / popular brands, watches, wallets & bags,
 * stainless steel accessories, gadgets, home gifts, kids & toys, gift boxes, daily essentials). Bilingual names, EGP prices, a real product photo each.
 * Data: database/seeders/data/gift_catalog.json (built from Jumia Egypt listings, see the `source_url` of every row).
 * Idempotent: categories by slug, brands by slug, products by slug, listings by product. Listings + opening stock go to every EGP tenant.
 * Two flavours (override withStock()): this class = dev, the whole catalog with the planned opening stock (hidden phase-2 products included);
 * GiftCatalogNoStockSeeder = live, the whole catalog listed and active but with NO stock at all.
 */
class GiftCatalogSeeder extends Seeder
{
    /** Opening stock per product when a data row has no planned `opening_qty` (adjustment_in at the estimated cost). Set to 0 to skip. */
    public const OPENING_QTY = 25;

    public const OPENING_NOTE = 'Opening stock (gift catalog seed)';

    /** slug => [ar, en, children[slug => [ar, en, children?]]] */
    public const TREE = [
        'beauty-care' => ['الجمال والعناية', 'Beauty & Care', [
            'skin-care' => ['العناية بالبشرة', 'Skin Care', [
                'cleansers' => ['غسول ومنظفات', 'Cleansers'],
                'serums' => ['سيروم وتونر', 'Serums & Toners'],
                'moisturizers' => ['مرطبات وكريمات', 'Moisturizers'],
                'sunscreens' => ['واقي الشمس', 'Sunscreens'],
                'masks-patches' => ['ماسكات ولاصقات', 'Masks & Patches'],
                'eye-lip-care' => ['العناية بالعين والشفايف', 'Eye & Lip Care'],
            ]],
            'makeup' => ['المكياج', 'Makeup', [
                'face-makeup' => ['مكياج الوجه', 'Face Makeup'],
                'eye-makeup' => ['مكياج العين', 'Eye Makeup'],
                'lip-makeup' => ['مكياج الشفايف', 'Lip Makeup'],
                'makeup-tools' => ['أدوات وفرش المكياج', 'Makeup Tools & Brushes'],
                'nail-care' => ['العناية بالأظافر', 'Nail Care'],
            ]],
            'hair-care' => ['العناية بالشعر', 'Hair Care', [
                'shampoo-conditioner' => ['شامبو وبلسم وماسكات', 'Shampoo, Conditioner & Masks'],
                'hair-oils-treatments' => ['زيوت وعلاجات الشعر', 'Hair Oils & Treatments'],
                'hair-styling-tools' => ['أجهزة وأدوات تصفيف الشعر', 'Hair Styling Tools'],
            ]],
            'body-and-bath' => ['العناية بالجسم', 'Body Care', [
                'body-care' => ['لوشن وسكراب واستحمام', 'Lotions, Scrubs & Bath'],
                'body-mists-deodorants' => ['بادي ميست ومزيل عرق', 'Body Mists & Deodorants'],
            ]],
            'fragrances' => ['العطور', 'Fragrances', [
                'womens-fragrances' => ['عطور حريمي', "Women's Perfumes"],
                'mens-fragrances' => ['عطور رجالي', "Men's Perfumes"],
                'oriental-musk' => ['عطور شرقية ومسك وبخور', 'Oriental, Musk & Bakhoor'],
            ]],
            'devices-grooming' => ['أجهزة التجميل والعناية الرجالية', "Beauty Devices & Men's Grooming", [
                'beauty-devices' => ['أجهزة التجميل', 'Beauty Devices'],
                'mens-grooming' => ['العناية الرجالية', "Men's Grooming"],
                'beauty-gift-sets' => ['أطقم هدايا التجميل', 'Beauty Gift Sets'],
            ]],
        ]],
        'watches' => ['الساعات', 'Watches', [
            'mens-watches' => ['ساعات رجالي', "Men's Watches"],
            'womens-watches' => ['ساعات حريمي', "Women's Watches"],
            'smart-watches' => ['ساعات ذكية وديجيتال وأطفال', 'Smart, Digital & Kids Watches'],
        ]],
        'wallets-bags' => ['المحافظ والشنط', 'Wallets & Bags', [
            'mens-wallets' => ['محافظ وأحزمة وشنط رجالي', "Men's Wallets, Belts & Bags"],
            'womens-bags' => ['شنط ومحافظ حريمي', "Women's Bags & Wallets"],
        ]],
        'accessories' => ['الإكسسوارات', 'Accessories', [
            'womens-jewelry' => ['إكسسوارات حريمي استانلس', "Women's Jewelry"],
            'mens-accessories' => ['إكسسوارات رجالي', "Men's Accessories"],
            'hair-accessories' => ['إكسسوارات الشعر', 'Hair Accessories'],
        ]],
        'gadgets' => ['الإلكترونيات والجادجتس', 'Electronics & Gadgets', [
            'audio' => ['سماعات وسبيكرات', 'Audio'],
            'phone-accessories' => ['إكسسوارات الموبايل والكمبيوتر', 'Phone & PC Accessories'],
            'smart-gadgets' => ['أجهزة ذكية ومنزلية', 'Smart & Home Gadgets'],
        ]],
        'home-gifts' => ['هدايا وديكور المنزل', 'Home Gifts & Decor', [
            'lamps-lights' => ['إضاءات ولمبات', 'Lamps & Lights'],
            'mugs-tumblers' => ['مجات وزمزميات', 'Mugs & Tumblers'],
            'decor-gifts' => ['ديكور وهدايا تذكارية', 'Decor & Keepsakes'],
        ]],
        'kids-toys' => ['الأطفال والمدرسة', 'Kids, Toys & School', [
            'school-supplies' => ['أدوات مدرسية', 'School Supplies'],
            'toys' => ['ألعاب ومستلزمات أطفال', 'Toys, Games & Kids Essentials'],
            'plush' => ['دباديب ودمى قطيفة', 'Plush Toys'],
        ]],
        'gift-boxes-occasions' => ['بوكسات الهدايا والمناسبات', 'Gift Boxes & Occasions', [
            'gift-boxes' => ['بوكسات هدايا', 'Gift Boxes'],
        ]],
        'daily-essentials' => ['الشتاء والضروريات', 'Winter & Essentials', [
            'winter' => ['مستلزمات الشتا والتدفئة', 'Winter Warmers'],
            'essentials' => ['مستلزمات ضرورية', 'Essentials'],
        ]],
    ];

    /** Leaves merged into a sibling (owner asked for fewer, stronger categories): their products move, the empty category is removed. */
    private const RETIRED = ['kids-watches' => 'smart-watches', 'watch-accessories' => 'smart-watches', 'mens-belts-bags' => 'mens-wallets', 'womens-wallets' => 'womens-bags', 'kids-essentials' => 'toys'];

    /** flag letter in the data => [tag slug, ar, en] */
    private const TAGS = [
        't' => ['trending', 'ترند السوشيال ميديا', 'Social Media Trend'],
        'b' => ['best-seller', 'الأكثر مبيعاً', 'Best seller'],
        'n' => ['new', 'جديد', 'New'],
        'e' => ['essentials', 'ضروريات', 'Essentials'],
        'w' => ['winter-trend', 'ترند الشتا', 'Winter Trend'],
        's' => ['back-to-school', 'المدارس', 'Back to School'],
        'k' => ['tiktok-viral', 'فيرال تيك توك', 'TikTok Viral'],
    ];

    /** How many top-ranked products to pin on the home page "Recommended" rail (only when the store has no pins of its own). */
    private const RECOMMENDED_PINS = 24;

    /** Whether listings get opening stock (and the buying-plan visibility: phase-2 products hidden). false = live: every product listed and active, zero stock. */
    protected function withStock(): bool
    {
        return true;
    }

    /** @return array<int,array> the data rows, best trend rank first */
    protected function rows(): array
    {
        return json_decode((string) file_get_contents(database_path('seeders/data/gift_catalog.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Restrict the listings to one store (used when a tenant is created); null = every EGP store. */
    private ?Tenant $only = null;

    public function forTenant(Tenant $tenant): static
    {
        $this->only = $tenant;

        return $this;
    }

    public function run(): void
    {
        $rows = $this->rows();

        // New store while the shared catalog already exists: only its listings are missing (no image downloads, no product writes).
        if ($this->only) {
            $existing = Product::withTrashed()->whereIn('slug', collect($rows)->pluck('slug'))->get()->keyBy('slug');
            if ($existing->count() === count($rows)) {
                if ($this->only->currency_code === 'EGP') {
                    $this->listings($this->only, $rows, $existing->all());
                }

                return;
            }
        }

        $cats = $this->categories();
        $tags = collect(self::TAGS)->mapWithKeys(fn ($t, $flag) => [$flag => Tag::firstOrCreate(['slug' => $t[0]], ['name' => ['ar' => $t[1], 'en' => $t[2]], 'is_active' => true])]);
        $brands = [];
        foreach (collect($rows)->pluck('brand')->filter()->unique()->values() as $i => $name) {
            $brands[$name] = Brand::firstOrCreate(['slug' => Str::slug($name) ?: 'brand-'.$i], ['name' => ['ar' => $name, 'en' => $name], 'is_active' => true, 'sort_order' => $i]);
        }

        $this->command?->info('Downloading product images ('.count($rows).' products)...');
        $urls = [];
        foreach ($rows as $r) {
            foreach (array_values($r['images']) as $n => $url) {
                $urls[$r['slug'].'-'.$n] = $url;
            }
        }
        $images = RemoteImage::fetchMany('catalog/products/gifts', $urls);

        $barcodes = app(BarcodeService::class);
        $products = [];
        $swapped = 0;
        foreach ($rows as $r) {
            $p = Product::withTrashed()->where('slug', $r['slug'])->first();
            if (! $p) {
                $p = DB::transaction(function () use ($r, $cats, $tags, $brands, $barcodes, $images) {
                    $p = Product::create([
                        'brand_id' => $r['brand'] ? $brands[$r['brand']]->id : null, 'type' => 'simple',
                        'name' => $r['name'], 'description' => $r['description'], 'meta_title' => $r['name'], 'meta_description' => $r['meta_description'],
                        'slug' => $r['slug'], 'sku' => $r['sku'], 'barcode' => $barcodes->generateUnique(), 'weight' => $r['weight'], 'is_active' => true,
                    ]);
                    $p->categories()->sync([$cats[$r['category']]->id]);
                    $p->tags()->sync(collect(str_split($r['flags'] ?? ''))->filter(fn ($f) => isset($tags[$f]))->map(fn ($f) => $tags[$f]->id)->unique()->values()->all());
                    $this->attachImages($p, $r, $images);

                    return $p;
                });
            } else {
                $this->moveOutOfRetired($p, $cats[$r['category']]);
                // season / trend tags added by later data (winter, school, TikTok...): add only, never remove what an admin set
                $p->tags()->syncWithoutDetaching(collect(str_split($r['flags'] ?? ''))->filter(fn ($f) => isset($tags[$f]))->map(fn ($f) => $tags[$f]->id)->unique()->values()->all());
            }
            $state = $this->imageState($p);
            $hasPhoto = isset($images[$r['slug'].'-0']);
            // Keep the picture in line with the data: a found photo replaces the name card (or a dead hot-link), a rejected photo goes back to
            // the name card. Images an admin uploaded are never touched.
            if (($hasPhoto && in_array($state, ['placeholder', 'remote', 'none'], true)) || (! $hasPhoto && in_array($state, ['seeded', 'remote', 'none'], true))) {
                DB::transaction(function () use ($p, $r, $images) {
                    $p->images()->get()->each(function ($img) {
                        RemoteImage::deleteFamily($img->path);
                        $img->delete();
                    });
                    $this->attachImages($p, $r, $images);
                });
                $swapped++;
            }
            $products[$r['slug']] = $p;
        }
        $swapped && $this->command?->info("Pictures refreshed for $swapped products (photo found, or photo rejected -> name card).");

        $this->prune(collect($rows)->pluck('slug')->all());
        $this->dropRetiredCategories();
        $this->categoryCovers($cats, $rows, $images);

        foreach ($this->only ? collect([$this->only])->where('currency_code', 'EGP') : Tenant::where('currency_code', 'EGP')->get() as $t) {
            $this->listings($t, $rows, $products);
        }
    }

    private const PLACEHOLDER_PREFIX = 'catalog/products/gifts/placeholder-';

    /** A product still sitting in a merged-away leaf moves to its leaf in the data file (other category links are kept). */
    private function moveOutOfRetired(Product $p, Category $target): void
    {
        $retired = $p->categories()->whereIn('slug', array_keys(self::RETIRED))->pluck('categories.id');
        if ($retired->isNotEmpty()) {
            $p->categories()->detach($retired->all());
            $p->categories()->syncWithoutDetaching([$target->id]);
        }
    }

    private function dropRetiredCategories(): void
    {
        foreach (Category::withTrashed()->whereIn('slug', array_keys(self::RETIRED))->get() as $c) {
            if (! $c->products()->exists() && ! Category::withTrashed()->where('parent_id', $c->id)->exists()) {
                RemoteImage::deleteFamily($c->image);
                $c->forceDelete();
            }
        }
    }

    /**
     * Products this seeder created earlier (GFT- SKU) that are no longer in the data file are removed completely, as long as nothing real
     * references them (no order lines, purchase/adjustment/transfer documents, bundles). Anything referenced is left untouched.
     */
    private function prune(array $keepSlugs): void
    {
        $gone = Product::withTrashed()->where('sku', 'like', 'GFT-%')->whereNotIn('slug', $keepSlugs)->get();
        $n = 0;
        foreach ($gone as $p) {
            $used = DB::table('order_items')->where('product_id', $p->id)->exists()
                || DB::table('purchase_order_items')->where('product_id', $p->id)->exists()
                || DB::table('stock_adjustment_items')->where('product_id', $p->id)->exists()
                || DB::table('stock_transfer_items')->where('product_id', $p->id)->exists()
                || DB::table('bundle_items')->where('product_id', $p->id)->exists();
            if ($used) {
                continue;
            }
            DB::transaction(function () use ($p) {
                foreach (TenantProduct::withoutGlobalScopes()->where('product_id', $p->id)->get() as $tp) {
                    app()->instance('currentTenant', Tenant::find($tp->tenant_id));
                    app(QrCodeManager::class)->deleteFor($tp);
                    app()->forgetInstance('currentTenant');
                    $tp->variants()->delete();
                    $tp->delete();
                }
                foreach (['stock_movements', 'stock_levels', 'cart_items', 'wishlist_items', 'flash_sale_items', 'reviews', 'category_product', 'product_tag'] as $table) {
                    DB::table($table)->where('product_id', $p->id)->delete();
                }
                $p->images()->get()->each(function ($img) {
                    RemoteImage::deleteFamily($img->path);
                    $img->delete();
                });
                $p->forceDelete();
            });
            $n++;
        }
        $n && $this->command?->info("Removed $n catalog products that are no longer in the gift catalog.");
    }

    /** Real photos that were downloaded, otherwise one name-card picture (replaced by a later run that finds the photo). */
    private function attachImages(Product $p, array $r, array $images): void
    {
        $got = collect(array_keys($r['images']))->map(fn ($n) => $images[$r['slug'].'-'.$n] ?? null)->filter()->values();
        if ($got->isEmpty()) {
            $p->images()->create(PlaceholderImage::make('catalog/products/gifts', 'placeholder-'.$r['slug'], $r['name']['en']) + ['sort_order' => 0]);

            return;
        }
        foreach ($got as $n => $img) {
            $p->images()->create($img + ['sort_order' => $n]);
        }
    }

    /** none | placeholder (name card) | seeded (photo this seeder stored) | remote (hot-linked URL) | admin (anything else: never touched) */
    private function imageState(Product $p): string
    {
        $paths = $p->images()->pluck('path')->map(fn ($x) => (string) $x);
        if ($paths->isEmpty()) {
            return 'none';
        }
        if ($paths->contains(fn ($x) => ! str_starts_with($x, 'catalog/products/gifts/') && ! preg_match('#^https?://#i', $x))) {
            return 'admin';
        }
        if ($paths->every(fn ($x) => str_starts_with($x, self::PLACEHOLDER_PREFIX))) {
            return 'placeholder';
        }

        return $paths->contains(fn ($x) => preg_match('#^https?://#i', $x)) ? 'remote' : 'seeded';
    }

    /** @return array<string,Category> */
    private function categories(): array
    {
        $cats = [];
        $walk = function (array $nodes, ?Category $parent) use (&$walk, &$cats) {
            $i = 0;
            foreach ($nodes as $slug => $n) {
                $c = Category::withTrashed()->firstOrCreate(['slug' => $slug], ['parent_id' => $parent?->id, 'name' => ['ar' => $n[0], 'en' => $n[1]], 'depth' => 0, 'path' => '', 'sort_order' => $i, 'is_active' => true]);
                $c->trashed() && $c->restore();
                $c->forceFill(['name' => ['ar' => $n[0], 'en' => $n[1]], 'parent_id' => $parent?->id, 'path' => ($parent ? $parent->path.'/' : '').$c->id, 'depth' => $parent ? $parent->depth + 1 : 0, 'sort_order' => $i++])->save();
                $cats[$slug] = $c;
                if (isset($n[2])) {
                    $walk($n[2], $c);
                }
            }
        };
        $walk(self::TREE, null);

        return $cats;
    }

    /** Each category without an image gets a copy of the photo of its first product (or of the first product anywhere below it). */
    private function categoryCovers(array $cats, array $rows, array $images): void
    {
        $firstByLeaf = [];
        foreach ($rows as $r) {
            $firstByLeaf[$r['category']] ??= $images[$r['slug'].'-0']['path'] ?? null;
        }
        foreach ($cats as $slug => $c) {
            if ($c->image) {
                continue;
            }
            $src = $firstByLeaf[$slug] ?? null;
            if (! $src) {
                $below = collect($cats)->filter(fn ($x) => str_starts_with($x->path, $c->path.'/'))->keys();
                $src = $below->map(fn ($s) => $firstByLeaf[$s] ?? null)->filter()->first();
            }
            if ($src && ($path = RemoteImage::copy($src, 'catalog/categories', $slug))) {
                $c->forceFill(['image' => $path])->save();
            }
        }
    }

    /**
     * Apply the buying plan to listings created by an earlier run: phase-1 products (opening_qty > 0) are active with that opening stock,
     * phase-2 products are hidden with no stock. Only touches products whose stock history is still just this seeder's opening entry and
     * that were never ordered (pre-launch seed data, nothing real to preserve); anything else is left exactly as it is.
     */
    private function reconcileOpeningStock(Tenant $t, Collection $rows, array $products): void
    {
        $ledger = app(StockLedger::class);
        $qr = app(QrCodeManager::class);
        $branch = Branch::where('is_default', true)->first() ?? Branch::first();
        $listings = TenantProduct::whereIn('product_id', $rows->map(fn ($r) => $products[$r['slug']]->id))->get()->keyBy('product_id');
        $n = 0;
        foreach ($rows as $r) {
            $pid = $products[$r['slug']]->id;
            $tp = $listings[$pid] ?? null;
            $want = (int) ($r['opening_qty'] ?? self::OPENING_QTY);
            $moves = DB::table('stock_movements')->where('tenant_id', $t->id)->where('product_id', $pid)->get();
            $seedOnly = $moves->isEmpty() || ($moves->count() === 1 && $moves[0]->note === self::OPENING_NOTE);
            $have = $moves->isEmpty() ? 0 : (int) $moves[0]->quantity;
            $costOk = $moves->isEmpty() || bccomp((string) $moves[0]->unit_cost, (string) $r['cost'], 2) === 0;
            if (! $tp || ! $branch || ! $seedOnly || ($have === $want && $costOk && $tp->is_active === ($want > 0)) || DB::table('order_items')->where('product_id', $pid)->exists()) {
                continue;
            }
            DB::transaction(function () use ($t, $tp, $moves, $want, $r, $ledger, $branch) {
                if ($moves->isNotEmpty()) {
                    DB::table('stock_movements')->where('id', $moves[0]->id)->delete();
                    DB::table('stock_levels')->where('tenant_id', $t->id)->where('product_id', $tp->product_id)->where('product_variant_id', 0)->delete();
                }
                if ($want > 0) {
                    $ledger->record($branch->id, $tp->product_id, 0, StockMovementType::ADJUSTMENT_IN, $want, (string) $r['cost'], null, self::OPENING_NOTE);
                }
                $tp->update(['is_active' => $want > 0]);
            });
            $qr->listingSaved($tp);
            $n++;
        }
        $n && $this->command?->info("{$t->slug}: buying plan applied to $n existing listings (opening stock / visibility)");
    }

    /**
     * Live flavour on a store that already holds dev listings: the phase-2 products the dev seed hid become active (there is no stock plan to wait for).
     * Same ownership rule as rebuildOutOfOrder: only listings nobody edited (no user change in the activity log, no custom wording) and never ordered.
     */
    private function activateHiddenListings(Tenant $t, Collection $rows, array $products): void
    {
        $hidden = TenantProduct::whereIn('product_id', $rows->map(fn ($r) => $products[$r['slug']]->id))->where('is_active', false)->whereNull('content')->get();
        $edited = DB::table('activity_log')->where('subject_type', TenantProduct::class)->whereNotNull('causer_id')->whereIn('subject_id', $hidden->pluck('id'))->pluck('subject_id')->flip();
        $ordered = DB::table('order_items')->whereIn('product_id', $hidden->pluck('product_id'))->distinct()->pluck('product_id')->flip();
        $qr = app(QrCodeManager::class);
        $n = 0;
        foreach ($hidden->reject(fn ($tp) => isset($edited[$tp->id]) || isset($ordered[$tp->product_id])) as $tp) {
            $tp->update(['is_active' => true]);
            $qr->listingSaved($tp);
            $n++;
        }
        $n && $this->command?->info("{$t->slug}: $n hidden listings activated (live: whole catalog, no stock)");
    }

    /**
     * Listings for an EGP store, in trend-rank order. The storefront lists newest listing first, so listings are created from the lowest to the
     * highest rank (the top product gets the newest listing). Steps: rebuild seed listings that are out of rank order, create missing ones through
     * the normal catalog service (QR codes etc.), apply the buying plan (opening stock / visibility), pin the Recommended rail.
     */
    private function listings(Tenant $t, array $rows, array $products): void
    {
        app()->instance('currentTenant', $t);
        try {
            $rows = collect($rows)->values();                       // the data file is already in rank order (best first)
            $this->rebuildOutOfOrder($t, $rows, $products);
            $have = TenantProduct::pluck('product_id')->flip();
            $new = $rows->reject(fn ($r) => isset($have[$products[$r['slug']]->id]));
            if ($new->isNotEmpty()) {
                app(TenantCatalogService::class)->enableBulk(new BulkEnableData($new->reverse()->map(fn ($r) => [
                    'product_id' => $products[$r['slug']]->id, 'price' => (string) $r['price'], 'sale_price' => $r['sale_price'] !== null ? (string) $r['sale_price'] : null,
                    'cost' => (string) $r['cost'], 'is_active' => ! $this->withStock() || (bool) ($r['active'] ?? true),
                ])->values()->all()));
                $byProduct = TenantProduct::whereIn('product_id', $new->map(fn ($r) => $products[$r['slug']]->id))->get()->keyBy('product_id');
                foreach ($new as $r) {
                    $byProduct[$products[$r['slug']]->id]?->update(['gift_suitable' => $r['gift']['suitable'], 'gift_recipients' => $r['gift']['recipients'], 'gift_occasions' => $r['gift']['occasions']]);
                }
                $this->command?->info("{$t->slug}: {$new->count()} products listed");
            }
            $this->withStock() ? $this->reconcileOpeningStock($t, $rows, $products) : $this->activateHiddenListings($t, $rows, $products);
            $this->pinRecommended($rows, $products);
        } finally {
            app()->forgetInstance('currentTenant');
        }
    }

    /**
     * Seed listings are deleted (and re-created from the data by the next step) when the store order no longer follows the trend rank or the
     * data price changed. Only listings nobody has edited (no change by a user in the activity log, no custom wording) whose product was never
     * ordered; stock is kept per product, so it is untouched. Anything a person changed keeps its listing exactly as it is.
     */
    private function rebuildOutOfOrder(Tenant $t, Collection $rows, array $products): void
    {
        $listings = TenantProduct::whereIn('product_id', $rows->map(fn ($r) => $products[$r['slug']]->id))->get()->keyBy('product_id');
        $byRow = $rows->keyBy(fn ($r) => $products[$r['slug']]->id);
        $rank = $rows->mapWithKeys(fn ($r, $i) => [$products[$r['slug']]->id => $i])->all();
        $edited = DB::table('activity_log')->where('subject_type', TenantProduct::class)->whereNotNull('causer_id')->whereIn('subject_id', $listings->pluck('id'))->pluck('subject_id')->flip();
        $ordered = DB::table('order_items')->whereIn('product_id', $listings->keys())->distinct()->pluck('product_id')->flip();
        $seedOwned = $listings->filter(fn ($tp, $pid) => $tp->content === null && ! isset($edited[$tp->id]) && ! isset($ordered[$pid]));
        $same = fn ($a, $b) => ($a === null && $b === null) || ($a !== null && $b !== null && bccomp((string) $a, (string) $b, 2) === 0);
        $stale = $seedOwned->contains(fn ($tp, $pid) => ! $same($tp->price, $byRow[$pid]['price']) || ! $same($tp->sale_price, $byRow[$pid]['sale_price']) || ! $same($tp->cost, $byRow[$pid]['cost']));
        $inOrder = $listings->sortByDesc('id')->keys()->values()->map(fn ($pid) => $rank[$pid])->sliding(2)->every(fn ($pair) => $pair->first() < $pair->last());
        if ($inOrder && ! $stale && $listings->count() === $rows->count()) {
            return;
        }
        $qr = app(QrCodeManager::class);
        foreach ($seedOwned as $tp) {
            DB::transaction(function () use ($tp, $qr) {
                $qr->deleteFor($tp);
                $tp->variants()->delete();
                $tp->delete();
            });
        }
        $seedOwned->isNotEmpty() && $this->command?->info("{$t->slug}: {$seedOwned->count()} seed listings re-created in trend-rank order with current data");
    }

    /** Home page "Recommended" rail = the top-ranked launch products, unless the store already pinned its own picks. */
    private function pinRecommended(Collection $rows, array $products): void
    {
        if (ProductListItem::where('list_type', ProductListType::RECOMMENDED->value)->exists()) {
            return;
        }
        $active = TenantProduct::where('is_active', true)->pluck('product_id')->flip();
        $top = $rows->map(fn ($r) => $products[$r['slug']]->id)->filter(fn ($pid) => isset($active[$pid]))->take(self::RECOMMENDED_PINS)->values();
        foreach ($top as $i => $pid) {
            ProductListItem::create(['list_type' => ProductListType::RECOMMENDED->value, 'product_id' => $pid, 'sort_order' => $i]);
        }
        $top->isNotEmpty() && $this->command?->info('Recommended rail: pinned the top '.$top->count().' trending products.');
    }
}
