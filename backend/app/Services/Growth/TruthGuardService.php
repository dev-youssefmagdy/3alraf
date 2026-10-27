<?php

namespace App\Services\Growth;

use App\Models\Product;
use App\Models\TenantProduct;

/**
 * Product Truth Guard (rule based, deterministic). Splits text into sentences, detects RISKY claims (ingredient / benefit / authenticity / result / testimonial),
 * and keeps a sentence only if it is supported by an approved claim or by the product's own facts (name, description). Unsupported risky sentences are removed;
 * a sentence that contradicts the facts (negates something the facts state) or uses a prohibited word is FLAGGED for human review (never silently kept).
 *
 * @phpstan-type Result array{ok:bool,text:string,removed:array<int,array{sentence:string,type:string}>,flagged:array<int,array{sentence:string,reason:string}>}
 */
class TruthGuardService
{
    /** type => regex of trigger terms (EN + AR). */
    private const TRIGGERS = [
        'authenticity' => '/\b(original|authentic|genuine|certified|fda|guarantee[d]?|warranty|clinically|dermatologically|patented|official\s+distributor)\b|أصلي|اصلي|أصلية|اصلية|مضمون|ضمان|معتمد|موثق|وكيل\s+رسمي|مختبر\s+طبي|سريرياً|سريريا/iu',
        'ingredient' => '/\b(contains?|made\s+(with|from)|ingredients?|natural|organic|vegan|paraben|sulfate|alcohol|gluten|cruelty|hypoallergenic|herbal|chemical[- ]free|free\s+(of|from))\b|يحتوي|مكونات|طبيعي|طبيعية|عضوي|عضوية|نباتي|خالي\s+من|خالية\s+من|خال\s+من|بدون\s+(كحول|بارابين|مواد)|مستخلص/iu',
        'benefit' => '/\b(cures?|treats?|heals?|prevents?|reduces?\s+(wrinkles|acne|pain|fat)|whitens?|anti-?aging|detox|boosts?\s+(immunity|hair)|slim(s|ming)|lose\s+weight|safe\s+for\s+(everyone|kids|pregnan))\b|يعالج|يشفي|يمنع|يزيل|يبيض|يفتح|يخسس|ينحف|يقوي\s+المناعة|يجدد|يكافح|مضاد\s+للشيخوخة|آمن\s+للجميع|آمن\s+للحوامل/iu',
        'result' => '/\b(results?\s+in\s+\d+|in\s+\d+\s+(days?|weeks?|hours?)|100\s*%|guaranteed\s+results?|visible\s+results?|instantly|permanent(ly)?|proven)\b|نتائج\s+(مضمونة|مؤكدة|فورية)|في\s+\d+\s+(يوم|ايام|أيام|اسبوع|أسابيع)|فوراً|فورا|دائم|مثبت\s+علمي|١٠٠\s*%/iu',
        'testimonial' => '/\b(customers?\s+(love|say|report)|thousands\s+of\s+(customers|clients|women|men)|rated\s+\d|#1|best[- ]seller\s+in|as\s+seen\s+on|loved\s+by)\b|آلاف\s+(العملاء|الزباين|الزبائن)|الأكثر\s+مبيع[اً]|العملاء\s+(بيقولوا|يقولون)|تقييم\s+\d/iu',
    ];

    /** Negation markers used to find "X-free" claims that contradict facts stating X. */
    private const NEG = '/(?:\b(?:free\s+(?:of|from)|no|without|non)[\s-]+|خالي\s+من\s+|خاليه\s+من\s+|خال\s+من\s+|بدون\s+)([\p{L}]{3,})/iu';

    /** Suffix form: "alcohol-free" / "paraben free". */
    private const NEG_SUFFIX = '/([\p{L}]{3,})[\s-]+free\b/iu';

    public function __construct(private BrandMemoryService $memory) {}

    /** @return Result */
    public function check(string $text, int|array|null $products = null, ?string $facts = null): array
    {
        $ids = array_values(array_filter((array) $products));
        $claims = collect($ids)->flatMap(fn ($id) => $this->memory->claims((int) $id)->pluck('claim'))->all();
        $facts ??= $this->factsFor($ids);
        $support = $this->norm($facts.' '.implode(' ', $claims));
        $prohibited = array_map(fn ($w) => $this->norm($w), $this->memory->words('prohibited_words'));
        $keep = [];
        $removed = [];
        $flagged = [];
        foreach ($this->sentences($text) as $s) {
            $n = $this->norm($s);
            if ($bad = collect($prohibited)->first(fn ($w) => $w !== '' && str_contains($n, $w))) {
                $flagged[] = ['sentence' => $s, 'reason' => 'prohibited_word'];
                $keep[] = $s;

                continue;
            }
            if ($why = $this->contradiction($n, $support)) {
                $flagged[] = ['sentence' => $s, 'reason' => 'conflict:'.$why];
                $keep[] = $s;

                continue;
            }
            $type = $this->riskType($s);
            if ($type && ! $this->supported($s, $n, $support, $claims)) {
                $removed[] = ['sentence' => $s, 'type' => $type];

                continue;
            }
            $keep[] = $s;
        }

        return ['ok' => ! $removed && ! $flagged, 'text' => trim(implode(' ', $keep)), 'removed' => $removed, 'flagged' => $flagged];
    }

    /**
     * Walks a draft (strings / nested arrays), guards every string longer than a few words. Returns the cleaned draft plus aggregated removed/flagged.
     *
     * @param  string[]  $skipKeys  keys never touched (slug, urls, ids)
     * @return array{draft:mixed,removed:array,flagged:array}
     */
    public function guardDraft(mixed $draft, int|array|null $products = null, array $skipKeys = ['slug', 'url', 'sort', 'image_prompt']): array
    {
        $facts = $this->factsFor(array_values(array_filter((array) $products)));
        $removed = [];
        $flagged = [];
        $walk = function ($v, $key = null) use (&$walk, &$removed, &$flagged, $products, $facts, $skipKeys) {
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $x) {
                    $out[$k] = $walk($x, $k);
                }

                return $out;
            }
            if (! is_string($v) || in_array($key, $skipKeys, true) || mb_strlen($v) < 12) {
                return $v;
            }
            $r = $this->check($v, $products, $facts);
            $removed = array_merge($removed, $r['removed']);
            $flagged = array_merge($flagged, $r['flagged']);

            return $r['text'] === '' && $r['removed'] ? '' : ($r['removed'] ? $r['text'] : $v);
        };

        return ['draft' => $walk($draft), 'removed' => $removed, 'flagged' => $flagged];
    }

    private function factsFor(array $ids): string
    {
        return implode(' ', array_map(fn ($id) => $this->facts((int) $id), $ids));
    }

    /** Product facts text: name + description in every locale + attribute-ish fields available on the product. */
    public function facts(int $productId): string
    {
        $p = Product::find($productId);
        if (! $p) {
            return '';
        }
        $parts = [];
        foreach (['name', 'description'] as $f) {
            $v = $p->getTranslations($f) ?? [];
            $parts[] = is_array($v) ? implode(' ', $v) : (string) $v;
        }
        $tp = TenantProduct::where('product_id', $productId)->first();
        $parts[] = (string) ($tp?->getAttribute('notes') ?? '');

        return implode(' ', $parts);
    }

    private function riskType(string $s): ?string
    {
        foreach (self::TRIGGERS as $type => $re) {
            if (preg_match($re, $s)) {
                return $type;
            }
        }

        return null;
    }

    /** Supported when an approved claim is (almost) contained in the sentence, or every risky trigger word of the sentence appears in the facts/claims. */
    private function supported(string $s, string $n, string $support, array $claims): bool
    {
        foreach ($claims as $c) {
            $cn = $this->norm($c);
            if ($cn !== '' && (str_contains($n, $cn) || $this->overlap($cn, $n) >= 0.8)) {
                return true;
            }
        }
        $hits = [];
        foreach (self::TRIGGERS as $re) {
            if (preg_match_all($re, $s, $m)) {
                $hits = array_merge($hits, $m[0]);
            }
        }
        if (! $hits || $support === '') {
            return false;
        }
        foreach ($hits as $h) {
            if (! str_contains($support, $this->norm($h))) {
                return false;
            }
        }

        return true;
    }

    /** Sentence says "free of X" while the facts/claims positively mention X (not negated): returns X. */
    private function contradiction(string $n, string $support): ?string
    {
        $terms = [];
        foreach ([self::NEG, self::NEG_SUFFIX] as $re) {
            if (preg_match_all($re, $n, $m)) {
                $terms = array_merge($terms, $m[1]);
            }
        }
        if ($support === '' || ! $terms) {
            return null;
        }
        foreach ($terms as $term) {
            $t = $this->norm($term);
            if ($t === '' || ! preg_match('/(?<![\p{L}])'.preg_quote($t, '/').'/u', $support)) {
                continue;
            }
            // present in facts: is it only mentioned negated there too?
            $neg = preg_match('/(?:(?:free\s+(?:of|from)|no|without|non|خالي\s+من|خاليه\s+من|خال\s+من|بدون)[\s-]+'.preg_quote($t, '/').'|'.preg_quote($t, '/').'[\s-]+free)/u', $support);
            if (! $neg) {
                return $t;
            }
        }

        return null;
    }

    private function overlap(string $a, string $b): float
    {
        $ta = array_filter(explode(' ', $a), fn ($w) => mb_strlen($w) > 2);
        if (! $ta) {
            return 0.0;
        }
        $tb = explode(' ', $b);

        return count(array_intersect($ta, $tb)) / count($ta);
    }

    /** @return string[] */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?؟…\n])\s+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(strip_tags($s));
        $s = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ـ'], ['ا', 'ا', 'ا', 'ه', 'ي', ''], $s);
        $s = preg_replace('/[\x{064B}-\x{065F}]/u', '', $s);

        return trim(preg_replace('/[^\p{L}\p{N}%# ]+/u', ' ', preg_replace('/\s+/u', ' ', $s)));
    }
}
