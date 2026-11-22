<?php

namespace App\Services\Casting;

use App\Models\CastingLiveProduct;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Product + audience analysis for casting. Deterministic and rule-based (keywords over the product's own name / description / categories / gift tags),
 * so it works offline and never invents product facts. Claude (via the store MCP) can refine the result by editing the session notes.
 */
class ProductBriefBuilder
{
    /** segment => [keywords (ar+en), audience label, tone, scene hints] */
    private const SEGMENTS = [
        'kitchen' => [['مطبخ', 'طبخ', 'حلل', 'حله', 'ستانلس', 'استانلس', 'ستانلس ستيل', 'kitchen', 'cook', 'pot', 'pan', 'stainless', 'مقلاة', 'طاسة'], 'home_makers', 'warm, practical, trustworthy', ['modern Egyptian apartment kitchen', 'family dining table']],
        'beauty' => [['مكياج', 'كريم', 'عطر', 'بشرة', 'شعر', 'عناية', 'makeup', 'cream', 'perfume', 'skin', 'serum', 'lipstick', 'cosmetic', 'تجميل'], 'beauty_shoppers', 'elegant, fresh, polished', ['bright vanity corner', 'soft daylight bathroom']],
        'fashion' => [['ملابس', 'قميص', 'فستان', 'حذاء', 'شنطة', 'حقيبة', 'ساعة', 'نظارة', 'shirt', 'dress', 'shoe', 'bag', 'watch', 'fashion', 'hijab', 'حجاب'], 'style_followers', 'confident, trendy', ['Cairo street cafe', 'Nile corniche at golden hour']],
        'kids' => [['اطفال', 'أطفال', 'طفل', 'لعبة', 'العاب', 'kids', 'baby', 'toy', 'children'], 'parents', 'playful, caring', ['bright living room', 'family garden']],
        'tech' => [['موبايل', 'سماعة', 'شاحن', 'كمبيوتر', 'phone', 'headphone', 'charger', 'laptop', 'gadget', 'كهربائ'], 'young_professionals', 'smart, energetic', ['modern home office', 'urban cafe']],
        'home' => [['ديكور', 'مفرش', 'ستارة', 'سجاد', 'انارة', 'إضاءة', 'home', 'decor', 'lamp', 'cushion', 'rug'], 'home_makers', 'cosy, tasteful', ['stylish living room', 'balcony with plants']],
        'gift' => [['هدية', 'هدايا', 'gift'], 'gift_givers', 'joyful, thoughtful', ['gift wrapping table', 'celebration at home']],
    ];

    public function build(Product|CastingLiveProduct|Subject $product): array
    {
        $x = Subject::of($product);
        $name = $x->name();
        $cats = $x->categoryNames();
        $text = Str::lower($name.' '.$x->description().' '.implode(' ', $cats).' '.implode(' ', $x->tagNames()));

        $scores = [];
        foreach (self::SEGMENTS as $seg => [$kw]) {
            $scores[$seg] = collect($kw)->filter(fn ($k) => str_contains($text, Str::lower($k)))->count();
        }
        arsort($scores);
        $segment = ($top = array_key_first($scores)) && $scores[$top] > 0 ? $top : 'general';
        [, $audience, $tone, $scenes] = self::SEGMENTS[$segment] ?? [[], 'general_shoppers', 'friendly, relatable', ['bright modern home']];

        $recipients = $x->giftRecipients();
        $price = $x->price();

        return [
            'product_id' => $x->productId(), 'live_product_id' => $x->liveProductId(),
            'name' => $name,
            'brand' => $x->brandName(),
            'categories' => $cats,
            'segment' => $segment,
            'price_tier' => $price >= 1500 ? 'premium' : ($price >= 300 ? 'mid' : 'value'),
            'has_image' => $x->hasImage(),
            'audience' => [
                'group' => $audience, 'gift_recipients' => $recipients, 'market' => 'egypt',
                'tone' => $tone, 'scenes' => $scenes,
                'genders' => in_array($segment, ['beauty', 'kitchen', 'home'], true) ? ['female', 'male'] : ['female', 'male'],
                'female_lean' => in_array($segment, ['beauty', 'kitchen', 'kids', 'home'], true),
            ],
        ];
    }
}
