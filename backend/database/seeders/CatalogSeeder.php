<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\BarcodeService;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Database\Seeder;

/** Central catalog: brands, attributes, 3-level category tree (raneen-style sections), ~40 products incl. variants, local placeholder images. Idempotent by slug/code. */
class CatalogSeeder extends Seeder
{
    /** slug => [ar, en, children[slug => [ar, en, children[slug => [ar, en]]]]] */
    public const TREE = [
        'appliances' => ['الأجهزة المنزلية', 'Home Appliances', [
            'large-appliances' => ['أجهزة كبيرة', 'Large Appliances', ['refrigerators' => ['ثلاجات', 'Refrigerators'], 'washing-machines' => ['غسالات', 'Washing Machines'], 'air-conditioners' => ['تكييفات', 'Air Conditioners']]],
            'small-appliances' => ['أجهزة صغيرة', 'Small Appliances', ['blenders' => ['خلاطات', 'Blenders'], 'kettles' => ['غلايات', 'Kettles']]],
        ]],
        'kitchen-tools' => ['أدوات المطبخ', 'Kitchen Tools', [
            'cookware' => ['أواني الطهي', 'Cookware', ['pans' => ['طواجن ومقالي', 'Pans & Pots']]],
            'tableware' => ['أدوات المائدة', 'Tableware', ['dinner-sets' => ['أطقم سفرة', 'Dinner Sets']]],
        ]],
        'furniture' => ['الأثاث', 'Furniture', [
            'living-room' => ['غرفة المعيشة', 'Living Room', ['sofas' => ['كنب', 'Sofas'], 'tables' => ['ترابيز', 'Tables']]],
            'bedroom' => ['غرفة النوم', 'Bedroom', ['beds' => ['أسرّة', 'Beds']]],
        ]],
        'tvs' => ['التلفزيونات', 'TVs & Audio', [
            'television' => ['تلفزيونات', 'Televisions', ['smart-tvs' => ['شاشات ذكية', 'Smart TVs']]],
            'audio' => ['الصوتيات', 'Audio', ['speakers' => ['سماعات', 'Speakers']]],
        ]],
        'home-textiles' => ['المفروشات', 'Home Textiles', [
            'bedding' => ['مفارش', 'Bedding', ['bed-sheets' => ['ملايات', 'Bed Sheets'], 'blankets' => ['بطاطين', 'Blankets']]],
            'bath' => ['الحمام', 'Bath', ['towels' => ['فوط', 'Towels']]],
        ]],
        'mobiles' => ['الموبايلات', 'Mobiles', [
            'smartphones' => ['هواتف ذكية', 'Smartphones', ['android' => ['أندرويد', 'Android'], 'iphone' => ['آيفون', 'iPhone']]],
            'accessories' => ['إكسسوارات', 'Accessories', ['chargers' => ['شواحن', 'Chargers']]],
        ]],
        'fashion' => ['الأزياء', 'Fashion', [
            'men' => ['رجالي', 'Men', ['men-shirts' => ['قمصان', 'Shirts'], 'men-shoes' => ['أحذية', 'Shoes']]],
            'women' => ['حريمي', 'Women', ['dresses' => ['فساتين', 'Dresses']]],
        ]],
        'auto-tools' => ['أدوات السيارات', 'Auto Tools', [
            'car-care' => ['العناية بالسيارة', 'Car Care', ['car-cleaning' => ['منظفات', 'Cleaning']]],
            'car-electronics' => ['إلكترونيات', 'Car Electronics', ['dash-cams' => ['كاميرات', 'Dash Cams']]],
        ]],
        'beauty' => ['الجمال', 'Beauty', [
            'skin-care' => ['العناية بالبشرة', 'Skin Care', ['moisturizers' => ['مرطبات', 'Moisturizers']]],
            'fragrance' => ['العطور', 'Fragrance', ['perfumes' => ['عطور', 'Perfumes']]],
        ]],
    ];

    /** key => [ar, en, leaf category, brand, price EGP, variable attr codes[]]; 'color' / 'size' / 'storage' */
    private array $products = [
        ['ثلاجة 2 باب 450 لتر', 'Two-Door Refrigerator 450L', 'refrigerators', 'Zanussi', 24999, []],
        ['ثلاجة ديجيتال 350 لتر', 'Digital Refrigerator 350L', 'refrigerators', 'Toshiba', 18999, []],
        ['غسالة أوتوماتيك 9 كجم', 'Automatic Washer 9kg', 'washing-machines', 'Zanussi', 15499, []],
        ['غسالة تحميل أمامي 8 كجم', 'Front Load Washer 8kg', 'washing-machines', 'Toshiba', 13299, []],
        ['تكييف سبليت 1.5 حصان', 'Split AC 1.5HP', 'air-conditioners', 'Sharp', 21999, []],
        ['خلاط كهربائي 1000 وات', 'Electric Blender 1000W', 'blenders', 'Braun', 1899, ['color']],
        ['غلاية مياه ستانلس', 'Stainless Kettle', 'kettles', 'Braun', 749, ['color']],
        ['طقم حلل جرانيت 10 قطع', 'Granite Cookware Set 10pc', 'pans', 'Tefal', 2899, []],
        ['طاسة تيفال غير لاصقة', 'Tefal Non-stick Pan', 'pans', 'Tefal', 650, ['size']],
        ['طقم سفرة 36 قطعة', 'Dinner Set 36pc', 'dinner-sets', 'Luminarc', 1999, []],
        ['كنبة ركنة مودرن', 'Modern Corner Sofa', 'sofas', 'HomeCraft', 18500, ['color']],
        ['ترابيزة سفرة 6 كراسي', 'Dining Table 6 Chairs', 'tables', 'HomeCraft', 12900, []],
        ['سرير مفرد خشب زان', 'Single Beech Bed', 'beds', 'HomeCraft', 6900, ['size']],
        ['شاشة سمارت 55 بوصة 4K', 'Smart TV 55" 4K', 'smart-tvs', 'Samsung', 17999, []],
        ['شاشة سمارت 43 بوصة', 'Smart TV 43"', 'smart-tvs', 'LG', 11499, []],
        ['سماعة بلوتوث محمولة', 'Portable Bluetooth Speaker', 'speakers', 'Sony', 1799, ['color']],
        ['ملاية سرير قطن 3 قطع', 'Cotton Bed Sheet 3pc', 'bed-sheets', 'Nilo', 599, ['color', 'size']],
        ['بطانية فرو ناعمة', 'Soft Fleece Blanket', 'blankets', 'Nilo', 899, ['color']],
        ['طقم فوط حمام 4 قطع', 'Bath Towel Set 4pc', 'towels', 'Nilo', 449, ['color']],
        ['موبايل أندرويد 128 جيجا', 'Android Phone 128GB', 'android', 'Samsung', 8999, ['color', 'storage']],
        ['موبايل ريدمي 256 جيجا', 'Redmi Phone 256GB', 'android', 'Xiaomi', 7499, ['color', 'storage']],
        ['آيفون 15', 'iPhone 15', 'iphone', 'Apple', 42999, ['color', 'storage']],
        ['شاحن سريع 65 وات', '65W Fast Charger', 'chargers', 'Anker', 549, []],
        ['كابل USB-C 2 متر', 'USB-C Cable 2m', 'chargers', 'Anker', 149, ['color']],
        ['قميص قطن رجالي', 'Men Cotton Shirt', 'men-shirts', 'Defacto', 499, ['color', 'size']],
        ['قميص كلاسيك رجالي', 'Men Classic Shirt', 'men-shirts', 'Defacto', 599, ['size']],
        ['حذاء رياضي رجالي', 'Men Running Shoes', 'men-shoes', 'Nike', 1999, ['color', 'size']],
        ['حذاء كاجوال', 'Casual Shoes', 'men-shoes', 'Nike', 1499, ['size']],
        ['فستان صيفي', 'Summer Dress', 'dresses', 'Defacto', 799, ['color', 'size']],
        ['فستان سهرة', 'Evening Dress', 'dresses', 'Defacto', 1599, ['size']],
        ['ملمع تابلوه سيارة', 'Dashboard Polish', 'car-cleaning', 'Meguiars', 189, []],
        ['شامبو غسيل سيارات', 'Car Wash Shampoo', 'car-cleaning', 'Meguiars', 159, []],
        ['كاميرا طبلون Full HD', 'Full HD Dash Cam', 'dash-cams', 'Xiaomi', 1799, []],
        ['كريم مرطب للوجه', 'Face Moisturizer', 'moisturizers', 'Nivea', 249, []],
        ['كريم مرطب لليدين', 'Hand Cream', 'moisturizers', 'Nivea', 99, []],
        ['عطر رجالي 100 مل', "Men's Perfume 100ml", 'perfumes', 'Dior', 3200, ['size']],
        ['عطر حريمي 50 مل', "Women's Perfume 50ml", 'perfumes', 'Dior', 2800, []],
        ['مكواة بخار', 'Steam Iron', 'kettles', 'Philips', 1199, []],
        ['مكنسة كهربائية', 'Vacuum Cleaner', 'blenders', 'Philips', 3299, []],
        ['ميكروويف 25 لتر', 'Microwave 25L', 'blenders', 'Sharp', 3699, []],
    ];

    public function run(): void
    {
        $brands = [];
        foreach (array_unique(array_column($this->products, 3)) as $i => $name) {
            $brands[$name] = Brand::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => ['ar' => $name, 'en' => $name], 'is_active' => true, 'sort_order' => $i]);
        }

        $attrs = [
            'color' => ['لون', 'Color', 'color', [['أسود', 'Black', '#111111'], ['أبيض', 'White', '#f5f5f5'], ['أحمر', 'Red', '#d62828'], ['أزرق', 'Blue', '#1d4ed8']]],
            'size' => ['مقاس', 'Size', 'select', [['صغير', 'S', null], ['وسط', 'M', null], ['كبير', 'L', null]]],
            'storage' => ['السعة', 'Storage', 'select', [['128 جيجا', '128GB', null], ['256 جيجا', '256GB', null]]],
        ];
        $values = [];
        foreach ($attrs as $code => [$ar, $en, $type, $vals]) {
            $a = Attribute::firstOrCreate(['code' => $code], ['name' => ['ar' => $ar, 'en' => $en], 'type' => $type]);
            foreach ($vals as $i => [$var, $ven, $hex]) {
                $values[$code][] = AttributeValue::where('attribute_id', $a->id)->get()->first(fn ($v) => ($v->getTranslations('value')['en'] ?? null) === $ven)
                    ?? AttributeValue::create(['attribute_id' => $a->id, 'value' => ['ar' => $var, 'en' => $ven], 'color_hex' => $hex, 'sort_order' => $i]);
            }
        }

        $cats = [];
        $walk = function (array $nodes, ?Category $parent) use (&$walk, &$cats) {
            $i = 0;
            foreach ($nodes as $slug => $n) {
                $c = Category::firstOrCreate(['slug' => $slug], ['parent_id' => $parent?->id, 'name' => ['ar' => $n[0], 'en' => $n[1]], 'depth' => $parent ? $parent->depth + 1 : 0, 'path' => '', 'sort_order' => $i++, 'is_active' => true]);
                $c->forceFill(['path' => ($parent ? $parent->path.'/' : '').$c->id, 'depth' => $parent ? $parent->depth + 1 : 0])->save();
                $c->image = $c->image ?: PlaceholderImage::make('catalog/categories', $slug, $n[1], 600, 600)['path'];
                $c->save();
                $cats[$slug] = $c;
                if (isset($n[2])) {
                    $walk($n[2], $c);
                }
            }
        };
        $walk(self::TREE, null);

        $barcodes = app(BarcodeService::class);
        foreach ($this->products as $i => [$ar, $en, $leaf, $brand, $price, $axes]) {
            $slug = \Illuminate\Support\Str::slug($en) ?: 'product-'.$i;
            if (Product::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }
            $p = Product::create([
                'brand_id' => $brands[$brand]->id, 'type' => $axes ? 'variable' : 'simple', 'name' => ['ar' => $ar, 'en' => $en],
                'description' => ['ar' => "وصف تجريبي لـ $ar. جودة عالية وضمان معتمد.", 'en' => "Demo description for $en. High quality with warranty."],
                'slug' => $slug, 'sku' => sprintf('SKU-%04d', $i + 1), 'barcode' => $axes ? null : $barcodes->generateUnique(), 'weight' => 1 + ($i % 7), 'is_active' => true,
            ]);
            $p->categories()->sync([$cats[$leaf]->id]);
            foreach ([0, 1] as $n) {
                $p->images()->create(PlaceholderImage::make('catalog/products', "$slug-$n", $en, 800, 800, $n) + ['sort_order' => $n]);
            }

            if ($axes) {
                $combos = [[]];
                foreach ($axes as $code) {
                    $pick = array_slice($values[$code], 0, $code === 'color' ? 3 : 2);
                    $combos = collect($combos)->flatMap(fn ($c) => collect($pick)->map(fn ($v) => [...$c, $v]))->all();
                }
                foreach ($combos as $k => $combo) {
                    $ids = collect($combo)->pluck('id')->sort()->values();
                    $v = $p->variants()->create(['combination_hash' => $ids->implode('-'), 'sku' => sprintf('SKU-%04d-%02d', $i + 1, $k + 1), 'barcode' => $barcodes->generateUnique(), 'weight' => $p->weight, 'is_active' => true]);
                    $v->values()->sync($ids->all());
                }
            }
        }
    }
}
