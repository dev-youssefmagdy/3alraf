<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Watch attributes (ar + en): variant axes first (colors, size, strap material, band size, packaging...), then specs used as storefront filters
 * (movement, water resistance, crystal, features...). Codes are prefixed `watch_` so they never clash with the generic demo attributes.
 *
 * Idempotent: attributes are matched by `code`, values by their English text. Names, colors and order are refreshed; nothing is deleted
 * (a value may already be used by variants).
 *
 *   php artisan db:seed --class=WatchAttributeSeeder
 */
class WatchAttributeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->attributes() as $sort => [$code, $ar, $en, $type, $values]) {
                $attr = Attribute::query()->updateOrCreate(['code' => $code], ['name' => ['ar' => $ar, 'en' => $en], 'type' => $type, 'sort_order' => $sort + 1]);
                $existing = AttributeValue::query()->where('attribute_id', $attr->id)->get()
                    ->keyBy(fn (AttributeValue $v) => mb_strtolower((string) ($v->getTranslations('value')['en'] ?? '')));

                foreach ($values as $i => $row) {
                    [$vEn, $vAr, $hex] = $row + [2 => null];
                    $data = ['value' => ['ar' => $vAr, 'en' => $vEn], 'color_hex' => $type === 'color' ? $hex : null, 'sort_order' => $i];
                    ($v = $existing->get(mb_strtolower($vEn))) ? $v->update($data) : AttributeValue::create($data + ['attribute_id' => $attr->id]);
                }
            }
        });
    }

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:array<int,array{0:string,1:string,2?:string}>}> [code, ar, en, type, [[en, ar, hex?], ...]] */
    private function attributes(): array
    {
        return [
            // ── variant axes ───────────────────────────────────────────────
            ['watch_case_color', 'لون الهيكل', 'Case Color', 'color', [...$this->metalColors(), ...$this->colors()]],
            ['watch_dial_color', 'لون الميناء', 'Dial Color', 'color', [...$this->colors(), ...$this->metalColors(),
                ['Mother of Pearl', 'صدف', '#F3EEE7'], ['Skeleton (Transparent)', 'هيكلي (شفاف)', '#E5E7EB'], ['Sunburst Blue', 'أزرق مشع', '#1E40AF'], ['Sunburst Green', 'أخضر مشع', '#166534'], ['Sunburst Grey', 'رمادي مشع', '#6B7280']]],
            ['watch_strap_color', 'لون السوار', 'Strap / Bracelet Color', 'color', [...$this->metalColors(), ...$this->colors(), ['Camel', 'جملي', '#C19A6B']]],
            ['watch_strap_material', 'خامة السوار', 'Strap / Bracelet Material', 'select', [
                ['Stainless Steel', 'ستانلس ستيل'], ['Genuine Leather', 'جلد طبيعي'], ['Synthetic Leather (PU)', 'جلد صناعي'], ['Crocodile-Pattern Leather', 'جلد بنقشة تمساح'],
                ['Silicone', 'سيليكون'], ['Rubber', 'كاوتش (ربر)'], ['Nylon / NATO', 'نايلون / ناتو'], ['Fabric / Canvas', 'قماش'], ['Milanese Mesh', 'شبك ميلانيز'],
                ['Titanium', 'تيتانيوم'], ['Ceramic', 'سيراميك'], ['Gold-Plated Steel', 'ستيل مطلي ذهبي'], ['Rose Gold-Plated Steel', 'ستيل مطلي روز جولد'],
                ['Two-Tone Steel', 'ستيل لونين'], ['Resin / Plastic', 'ريزن / بلاستيك'], ['Alloy', 'سبيكة معدنية'], ['Leather & Rubber (Hybrid)', 'جلد وكاوتش (هجين)'], ['Bangle / Chain', 'غويشة / سلسلة'],
            ]],
            ['watch_case_size', 'قطر الهيكل', 'Case Diameter', 'select', array_map(fn ($mm) => ["$mm mm", "$mm مم"], [18, 20, 22, 24, 26, 28, 30, 32, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 50, 52, 55])],
            ['watch_band_size', 'مقاس السوار', 'Band Size (Wrist)', 'select', [
                ['One Size (Adjustable)', 'مقاس واحد (قابل للتعديل)'], ['XS (13–15 cm)', 'XS (13–15 سم)'], ['S (15–17 cm)', 'S (15–17 سم)'], ['M (17–19 cm)', 'M (17–19 سم)'],
                ['L (19–21 cm)', 'L (19–21 سم)'], ['XL (21–23 cm)', 'XL (21–23 سم)'], ['S/M', 'S/M'], ['M/L', 'M/L'],
            ]],
            ['watch_strap_width', 'عرض السوار', 'Strap Width (Lug Width)', 'select', array_map(fn ($mm) => ["$mm mm", "$mm مم"], [8, 10, 12, 14, 16, 18, 19, 20, 21, 22, 23, 24, 26, 28])],
            ['watch_packaging', 'التغليف', 'Packaging', 'select', [
                ['Original Brand Box', 'علبة الماركة الأصلية'], ['Gift Box', 'علبة هدية'], ['Premium Wooden Box', 'علبة خشب فاخرة'], ['Pouch', 'حافظة قماش'], ['Without Box', 'بدون علبة'],
            ]],
            ['watch_set', 'نوع الطقم', 'Set Type', 'select', [
                ['Watch Only', 'ساعة فقط'], ['Watch + Bracelet Set', 'طقم ساعة + أسورة'], ['Watch + Extra Strap', 'ساعة + سوار إضافي'], ['Couple Set (His & Hers)', 'طقم كابلز (رجالي وحريمي)'],
                ['Watch + Jewelry Set', 'طقم ساعة + إكسسوارات'], ['Watch + Wallet Set', 'طقم ساعة + محفظة'],
            ]],

            // ── specs / storefront filters ─────────────────────────────────
            ['watch_gender', 'الفئة', 'Gender', 'select', [['Men', 'رجالي'], ['Women', 'حريمي'], ['Unisex', 'للجنسين'], ['Boys', 'أولادي'], ['Girls', 'بناتي'], ['Kids', 'أطفال']]],
            ['watch_movement', 'نوع الحركة (الماكينة)', 'Movement', 'select', [
                ['Quartz (Battery)', 'كوارتز (بطارية)'], ['Automatic (Self-Winding)', 'أوتوماتيك'], ['Mechanical (Hand-Wound)', 'ميكانيكي (تعبئة يدوية)'], ['Solar', 'طاقة شمسية'],
                ['Kinetic', 'كينتيك (حركية)'], ['Radio-Controlled', 'متحكم لاسلكيًا (راديو)'], ['Digital', 'ديجيتال'], ['Smartwatch', 'ساعة ذكية'], ['Hybrid Smartwatch', 'ساعة ذكية هجينة'],
            ]],
            ['watch_movement_origin', 'منشأ الماكينة', 'Movement Origin', 'select', [
                ['Japanese', 'ياباني'], ['Swiss', 'سويسري'], ['German', 'ألماني'], ['Chinese', 'صيني'], ['Korean', 'كوري'], ['American', 'أمريكي'],
            ]],
            ['watch_display', 'نوع العرض', 'Display', 'select', [['Analog', 'أنالوج (عقارب)'], ['Digital', 'ديجيتال (رقمي)'], ['Analog-Digital', 'أنالوج وديجيتال'], ['Touchscreen', 'شاشة لمس']]],
            ['watch_case_material', 'خامة الهيكل', 'Case Material', 'select', [
                ['Stainless Steel', 'ستانلس ستيل'], ['316L Stainless Steel', 'ستانلس ستيل 316L'], ['Titanium', 'تيتانيوم'], ['Ceramic', 'سيراميك'], ['Gold-Plated', 'مطلي ذهبي'],
                ['Rose Gold-Plated', 'مطلي روز جولد'], ['Black PVD/IP-Plated', 'مطلي أسود PVD'], ['Aluminum', 'ألومنيوم'], ['Zinc Alloy', 'سبيكة زنك'], ['Brass', 'نحاس'],
                ['Bronze', 'برونز'], ['Resin / Plastic', 'ريزن / بلاستيك'], ['Carbon Fiber', 'ألياف كربون'], ['Solid Gold', 'ذهب خالص'], ['Sterling Silver', 'فضة استرليني'],
            ]],
            ['watch_case_shape', 'شكل الهيكل', 'Case Shape', 'select', [
                ['Round', 'دائري'], ['Square', 'مربع'], ['Rectangular', 'مستطيل'], ['Tonneau', 'برميلي (تونو)'], ['Oval', 'بيضاوي'], ['Octagonal', 'ثماني'], ['Hexagonal', 'سداسي'], ['Cushion', 'وسادي (كوشن)'],
            ]],
            ['watch_case_thickness', 'سُمك الهيكل', 'Case Thickness', 'select', [
                ['Ultra-Thin (under 7 mm)', 'رفيع جدًا (أقل من 7 مم)'], ['Thin (7–9 mm)', 'رفيع (7–9 مم)'], ['Medium (9–12 mm)', 'متوسط (9–12 مم)'], ['Thick (12–15 mm)', 'سميك (12–15 مم)'], ['Extra Thick (over 15 mm)', 'سميك جدًا (أكثر من 15 مم)'],
            ]],
            ['watch_crystal', 'نوع الزجاج', 'Crystal (Glass)', 'select', [
                ['Mineral', 'زجاج معدني (مينرال)'], ['Hardlex', 'هاردلكس'], ['Sapphire', 'سافير'], ['Sapphire-Coated Mineral', 'مينرال مطلي سافير'], ['Anti-Reflective Sapphire', 'سافير مضاد للانعكاس'],
                ['Acrylic (Hesalite)', 'أكريليك'], ['Gorilla Glass', 'جوريلا جلاس'],
            ]],
            ['watch_water_resistance', 'مقاومة الماء', 'Water Resistance', 'select', [
                ['Not Water Resistant', 'غير مقاومة للماء'], ['3 ATM / 30 m (Splash)', '3 ATM / 30 م (رذاذ)'], ['5 ATM / 50 m', '5 ATM / 50 م'], ['10 ATM / 100 m', '10 ATM / 100 م'],
                ['20 ATM / 200 m', '20 ATM / 200 م'], ['30 ATM / 300 m', '30 ATM / 300 م'], ['50 ATM / 500 m', '50 ATM / 500 م'], ['IP67', 'IP67'], ['IP68', 'IP68'],
            ]],
            ['watch_dial_markers', 'أرقام الميناء', 'Dial Numerals / Markers', 'select', [
                ['Arabic Numerals (1 2 3)', 'أرقام إنجليزي (1 2 3)'], ['Eastern Arabic Numerals (١ ٢ ٣)', 'أرقام عربي (١ ٢ ٣)'], ['Roman Numerals', 'أرقام رومانية'], ['Index / Stick Markers', 'علامات (خطوط)'],
                ['Crystal / Stone Markers', 'علامات فصوص'], ['Diamond Markers', 'علامات ألماس'], ['Mixed', 'مختلط'], ['No Markers (Minimal)', 'بدون أرقام (بسيط)'],
            ]],
            ['watch_bezel', 'الإطار (البيزل)', 'Bezel', 'select', [
                ['Fixed', 'ثابت'], ['Unidirectional Rotating', 'دوار باتجاه واحد'], ['Bidirectional Rotating', 'دوار بالاتجاهين'], ['Ceramic Insert', 'حشوة سيراميك'], ['Tachymeter', 'تاكيميتر'],
                ['Crystal / Stone-Set', 'مرصع بالفصوص'], ['Diamond-Set', 'مرصع بالألماس'], ['Fluted', 'مضلع'],
            ]],
            ['watch_clasp', 'نوع القفل', 'Clasp Type', 'select', [
                ['Pin Buckle', 'إبزيم (توكة)'], ['Deployant (Butterfly)', 'فراشة'], ['Folding Clasp', 'قفل قابل للطي'], ['Push-Button Folding Clasp', 'قفل طي بزر ضغط'], ['Magnetic', 'مغناطيسي'],
                ['Sliding (Mesh)', 'منزلق (للشبك)'], ['Jewelry / Box Clasp', 'قفل مجوهرات'], ['Hook & Loop (Velcro)', 'لاصق (فيلكرو)'], ['Bangle (Open)', 'غويشة (مفتوح)'],
            ]],
            ['watch_features', 'المميزات', 'Features', 'select', [
                ['Date', 'تاريخ'], ['Day & Date', 'اليوم والتاريخ'], ['Chronograph', 'كرونوجراف'], ['Luminous Hands', 'عقارب فسفورية'], ['Moon Phase', 'أطوار القمر'], ['GMT / Dual Time', 'توقيت مزدوج (GMT)'],
                ['World Time', 'التوقيت العالمي'], ['Alarm', 'منبه'], ['Stopwatch', 'ساعة إيقاف'], ['Countdown Timer', 'عداد تنازلي'], ['Backlight', 'إضاءة خلفية'], ['Tachymeter', 'تاكيميتر'],
                ['Power Reserve Indicator', 'مؤشر احتياطي الطاقة'], ['24-Hour Indicator', 'مؤشر 24 ساعة'], ['Skeleton / Open Heart', 'هيكلي / قلب مفتوح'], ['Screw-Down Crown', 'تاج ملولب'],
                ['Shock Resistant', 'مقاومة للصدمات'], ['Anti-Magnetic', 'مقاومة للمغناطيسية'], ['Heart Rate Monitor', 'قياس نبض القلب'], ['Blood Oxygen (SpO2)', 'قياس الأكسجين'],
                ['Blood Pressure', 'قياس ضغط الدم'], ['Sleep Tracking', 'تتبع النوم'], ['Step Counter', 'عداد خطوات'], ['GPS', 'GPS'], ['Bluetooth Calling', 'مكالمات بلوتوث'],
                ['Notifications', 'إشعارات'], ['NFC Payments', 'دفع NFC'], ['Compass', 'بوصلة'], ['Altimeter / Barometer', 'مقياس ارتفاع / ضغط جوي'], ['Thermometer', 'مقياس حرارة'],
                ['Music Control', 'التحكم في الموسيقى'], ['Always-On Display', 'شاشة تعمل دائمًا'], ['Wireless Charging', 'شحن لاسلكي'],
            ]],
            ['watch_style', 'الستايل', 'Style', 'select', [
                ['Classic / Dress', 'كلاسيك'], ['Casual', 'كاجوال'], ['Sport', 'سبورت'], ['Luxury', 'فاخر'], ['Diver', 'غوص'], ['Pilot / Aviator', 'طيار'], ['Military / Field', 'عسكري'],
                ['Racing', 'سباقات'], ['Fashion', 'موضة'], ['Minimalist', 'بسيط (مينيمال)'], ['Vintage / Retro', 'فينتدج'], ['Smart', 'ذكي'], ['Jewelry Watch', 'ساعة مجوهرات'],
            ]],
            ['watch_power_reserve', 'احتياطي الطاقة', 'Power Reserve (Automatic)', 'select', array_map(fn ($h) => ["$h hours", "$h ساعة"], [36, 38, 40, 42, 48, 50, 60, 70, 72, 80])],
            ['watch_battery_life', 'عمر البطارية', 'Battery Life', 'select', [
                ['Up to 1 Day', 'حتى يوم'], ['Up to 3 Days', 'حتى 3 أيام'], ['Up to 7 Days', 'حتى 7 أيام'], ['Up to 14 Days', 'حتى 14 يوم'], ['Up to 30 Days', 'حتى 30 يوم'],
                ['1–2 Years (Quartz)', 'سنة إلى سنتين (كوارتز)'], ['2–3 Years (Quartz)', '2 إلى 3 سنوات (كوارتز)'], ['Solar (No Battery Change)', 'شمسية (بدون تغيير بطارية)'],
            ]],
            ['watch_compatibility', 'التوافق', 'Smartwatch Compatibility', 'select', [['Android', 'أندرويد'], ['iOS (iPhone)', 'iOS (آيفون)'], ['Android & iOS', 'أندرويد و iOS']]],
            ['watch_warranty', 'الضمان', 'Warranty', 'select', [
                ['No Warranty', 'بدون ضمان'], ['3 Months', '3 شهور'], ['6 Months', '6 شهور'], ['1 Year', 'سنة'], ['2 Years', 'سنتين'], ['International Warranty', 'ضمان دولي'], ['Agent Warranty', 'ضمان الوكيل'],
            ]],
            ['watch_condition', 'الحالة', 'Condition', 'select', [['New', 'جديدة'], ['Pre-Owned', 'مستعملة'], ['Refurbished', 'مجددة']]],
        ];
    }

    /** Metal finishes, used by case / dial / strap colors. */
    private function metalColors(): array
    {
        return [
            ['Silver', 'فضي', '#C0C0C0'], ['Gold', 'ذهبي', '#D4AF37'], ['Rose Gold', 'روز جولد', '#B76E79'], ['Champagne', 'شامبين', '#E8D5A9'],
            ['Gunmetal', 'رصاصي غامق', '#2C3539'], ['Two-Tone Silver & Gold', 'فضي وذهبي', '#CDB77A'], ['Two-Tone Silver & Rose Gold', 'فضي وروز جولد', '#D3A6A1'],
            ['Bronze', 'برونزي', '#CD7F32'], ['Copper', 'نحاسي', '#B87333'],
        ];
    }

    private function colors(): array
    {
        return [
            ['Black', 'أسود', '#111111'], ['White', 'أبيض', '#FFFFFF'], ['Grey', 'رمادي', '#808080'], ['Blue', 'أزرق', '#1D4ED8'], ['Navy', 'كحلي', '#1B2A49'],
            ['Light Blue', 'لبني', '#7DD3FC'], ['Turquoise', 'تركواز', '#14B8A6'], ['Green', 'أخضر', '#15803D'], ['Olive', 'زيتي', '#556B2F'], ['Brown', 'بني', '#6B4423'],
            ['Tan', 'بيج غامق', '#D2B48C'], ['Beige', 'بيج', '#F5F5DC'], ['Red', 'أحمر', '#B91C1C'], ['Burgundy', 'نبيتي', '#800020'], ['Pink', 'وردي', '#F4A7B9'],
            ['Purple', 'بنفسجي', '#6B21A8'], ['Orange', 'برتقالي', '#EA580C'], ['Yellow', 'أصفر', '#FACC15'], ['Multicolor', 'متعدد الألوان', '#9CA3AF'],
        ];
    }
}
