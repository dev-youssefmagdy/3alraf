<?php

namespace App\Services\Casting;

/**
 * Picks 4-6 genuinely distinct Egyptian casting candidates from a curated archetype pool, ranked by fit with the product brief.
 * Distinctness is enforced (no two candidates share gender + age bracket + hair style), so a session never returns near-duplicates.
 */
class CandidatePlanner
{
    /** key => archetype. fits = segments it suits best. */
    public const POOL = [
        ['name' => 'مريم', 'en' => 'Mariam', 'gender' => 'female', 'age' => '25-32', 'hair' => 'long dark brown wavy hair, loose', 'skin' => 'warm wheat skin tone', 'face' => 'soft oval face, dark almond eyes, natural makeup',
            'clothing' => 'cream linen blouse and light trousers', 'personality' => 'warm, confident, approachable', 'role' => 'The relatable everyday customer', 'fits' => ['beauty', 'fashion', 'home', 'general', 'gift'],
            'poses' => ['holding_product', 'three_quarter_portrait', 'looking_at_camera'], 'locations' => ['bright modern apartment', 'Cairo cafe terrace']],
        ['name' => 'هدى', 'en' => 'Hoda', 'gender' => 'female', 'age' => '34-42', 'hair' => 'neat dark hijab in a muted dusty-rose colour', 'skin' => 'light olive skin tone', 'face' => 'gentle round face, kind brown eyes, subtle smile lines',
            'clothing' => 'modest long cardigan over a soft pastel top', 'personality' => 'caring, reliable, home-proud', 'role' => 'The trusted mum who recommends', 'fits' => ['kitchen', 'kids', 'home', 'general', 'gift'],
            'poses' => ['using_product', 'sitting', 'looking_at_product'], 'locations' => ['family kitchen', 'living room with sofa']],
        ['name' => 'أحمد', 'en' => 'Ahmed', 'gender' => 'male', 'age' => '28-35', 'hair' => 'short black hair with a clean fade, light stubble', 'skin' => 'medium tan skin tone', 'face' => 'strong jawline, dark eyes, friendly grin',
            'clothing' => 'navy casual shirt with rolled sleeves', 'personality' => 'energetic, smart, easygoing', 'role' => 'The modern young professional', 'fits' => ['tech', 'fashion', 'general', 'gift'],
            'poses' => ['holding_product', 'walking', 'looking_at_camera'], 'locations' => ['urban street in Cairo', 'home office']],
        ['name' => 'ياسمين', 'en' => 'Yasmine', 'gender' => 'female', 'age' => '19-24', 'hair' => 'chic black hijab with a modern wrap style', 'skin' => 'fair warm skin tone', 'face' => 'bright eyes, youthful face, playful expression',
            'clothing' => 'trendy oversized denim jacket and white tee', 'personality' => 'playful, trend-aware, social', 'role' => 'The social-media-savvy young shopper', 'fits' => ['fashion', 'beauty', 'tech', 'general'],
            'poses' => ['looking_at_camera', 'holding_product', 'walking'], 'locations' => ['campus lawn', 'Nile corniche at golden hour']],
        ['name' => 'محمود', 'en' => 'Mahmoud', 'gender' => 'male', 'age' => '40-50', 'hair' => 'short salt-and-pepper hair, trimmed beard', 'skin' => 'tanned skin tone', 'face' => 'kind deep-set eyes, relaxed confident expression',
            'clothing' => 'light grey polo shirt', 'personality' => 'dependable, practical, fatherly', 'role' => 'The family provider who values quality', 'fits' => ['kitchen', 'home', 'tech', 'kids', 'general'],
            'poses' => ['using_product', 'sitting', 'three_quarter_portrait'], 'locations' => ['living room', 'balcony with plants']],
        ['name' => 'نورهان', 'en' => 'Nourhan', 'gender' => 'female', 'age' => '28-36', 'hair' => 'shoulder-length auburn-brown straight hair', 'skin' => 'light wheat skin tone', 'face' => 'defined cheekbones, elegant polished look, soft lipstick',
            'clothing' => 'tailored beige blazer over a silk top', 'personality' => 'elegant, refined, aspirational', 'role' => 'The premium lifestyle aspirant', 'fits' => ['beauty', 'fashion', 'gift', 'home'],
            'poses' => ['beauty_close_up', 'three_quarter_portrait', 'full_body'], 'locations' => ['luxury boutique interior', 'marble vanity']],
        ['name' => 'كريم', 'en' => 'Karim', 'gender' => 'male', 'age' => '20-26', 'hair' => 'tousled dark curly hair', 'skin' => 'medium olive skin tone', 'face' => 'cheerful eyes, wide smile, youthful',
            'clothing' => 'graphic-free grey hoodie', 'personality' => 'cheerful, spontaneous, gadget-loving', 'role' => 'The young trend follower', 'fits' => ['tech', 'fashion', 'general', 'gift'],
            'poses' => ['looking_at_product', 'sitting', 'holding_product'], 'locations' => ['urban cafe', 'apartment rooftop']],
        ['name' => 'سلمى', 'en' => 'Salma', 'gender' => 'female', 'age' => '44-55', 'hair' => 'elegant silver-streaked dark bob', 'skin' => 'warm light skin tone', 'face' => 'poised, warm eyes, graceful expression',
            'clothing' => 'soft emerald blouse with a delicate scarf', 'personality' => 'wise, gracious, hospitable', 'role' => 'The experienced host who gives gifts', 'fits' => ['kitchen', 'home', 'gift', 'kids', 'general'],
            'poses' => ['holding_product', 'sitting', 'front_portrait'], 'locations' => ['dining room set for guests', 'tea corner']],
        ['name' => 'عمر', 'en' => 'Omar', 'gender' => 'male', 'age' => '30-38', 'hair' => 'short wavy dark hair, neat beard', 'skin' => 'deep wheat skin tone', 'face' => 'expressive eyes, charismatic smile',
            'clothing' => 'olive chore jacket over a white tee', 'personality' => 'charismatic, stylish, grounded', 'role' => 'The style-conscious everyman', 'fits' => ['fashion', 'beauty', 'home', 'general'],
            'poses' => ['full_body', 'walking', 'three_quarter_portrait'], 'locations' => ['old Cairo street', 'modern loft']],
    ];

    /** @return list<array<string,mixed>> */
    public function plan(array $brief, int $count): array
    {
        $count = max((int) config('casting.candidates.min'), min((int) config('casting.candidates.max'), $count));
        $segment = $brief['segment'] ?? 'general';
        $lean = (bool) ($brief['audience']['female_lean'] ?? false);

        $scored = collect(self::POOL)->map(function ($a) use ($segment, $lean) {
            $score = in_array($segment, $a['fits'], true) ? 10 : 0;
            $score += ($lean && $a['gender'] === 'female') ? 3 : 0;

            return [$score + random_int(0, 4), $a];
        })->sortByDesc(fn ($x) => $x[0])->pluck(1);

        $picked = [];
        $seen = [];
        $females = 0;
        foreach ($scored as $a) {
            $sig = $a['gender'].'|'.substr($a['age'], 0, 1).'|'.explode(' ', $a['hair'])[0];
            if (isset($seen[$sig]) || count($picked) >= $count) {
                continue;
            }
            // keep the mix balanced: never more than ceil(2/3) of one gender
            $isF = $a['gender'] === 'female';
            if (($isF ? $females + 1 : count($picked) - $females + 1) > (int) ceil($count * 2 / 3)) {
                continue;
            }
            $seen[$sig] = true;
            $females += $isF ? 1 : 0;
            $picked[] = $a;
        }
        foreach ($scored as $a) {        // top up if the balance rule left gaps
            if (count($picked) >= $count) {
                break;
            }
            if (! in_array($a, $picked, true)) {
                $picked[] = $a;
            }
        }

        return array_map(fn ($a) => $this->candidate($a, $brief), $picked);
    }

    private function candidate(array $a, array $brief): array
    {
        $tone = $brief['audience']['tone'] ?? 'friendly';

        return [
            'name' => $a['name'], 'gender' => $a['gender'], 'age_range' => $a['age'],
            'appearance' => ucfirst($a['skin']).', '.$a['face'],
            'hairstyle' => $a['hair'], 'clothing' => $a['clothing'], 'personality' => $a['personality'], 'ad_role' => $a['role'],
            'suggested_poses' => $a['poses'], 'suggested_locations' => $a['locations'],
            'rationale' => sprintf('%s — %s for "%s" (%s audience, tone: %s).', $a['en'], lcfirst($a['role']), $brief['name'] ?? '', str_replace('_', ' ', $brief['audience']['group'] ?? 'general'), $tone),
            'en_name' => $a['en'],
        ];
    }
}
