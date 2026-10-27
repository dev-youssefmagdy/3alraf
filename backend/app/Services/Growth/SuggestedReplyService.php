<?php

namespace App\Services\Growth;

use App\Models\BrandMemoryEntry;
use App\Models\Interaction;
use App\Models\ReputationAlert;
use App\Models\TenantProduct;

/**
 * Maturity L1 classify only, L2 suggest (live facts + brand memory FAQ, Truth Guard checked), L3 auto-reply for ALLOWLISTED tested FAQs only, L4 routing.
 * Escalations (complaint, safety, refund, conflict, missing data), opted-out customers and topics paused by Reputation Radar are never automatic.
 */
class SuggestedReplyService
{
    public const ROUTES = ['price' => 'sales', 'availability' => 'sales', 'shipping' => 'logistics', 'returns' => 'support', 'safety' => 'support', 'trust' => 'support', 'photos' => 'sales', 'size_color' => 'sales', 'usage' => 'support'];

    public function __construct(private InteractionService $ix, private TruthGuardService $guard, private GrowthFeatures $features, private GrowthConfig $config, private MetricsService $metrics) {}

    /** @return array{mode:string,reply:?string,reason:?string,auto_allowed:bool,window_open:?bool,route_to:?string,level:int,faq_key:?string} */
    public function suggest(Interaction $i, bool $persist = true): array
    {
        $level = $this->ix->replyLevel();
        $out = ['mode' => 'classify_only', 'reply' => null, 'reason' => null, 'auto_allowed' => false, 'window_open' => $this->ix->windowOpen($i), 'route_to' => null, 'level' => $level, 'faq_key' => null];
        if ($i->opted_out) {
            return $this->done($i, $persist, ['mode' => 'none', 'reason' => 'opted_out'] + $out);
        }
        if ($level >= 4) {
            $out['route_to'] = self::ROUTES[$i->category] ?? 'support';
        }
        if ($i->escalation_reason) {
            return $this->done($i, $persist, ['mode' => 'human', 'reason' => $i->escalation_reason] + $out);
        }
        if ($level < 2) {
            return $this->done($i, $persist, $out);
        }
        [$text, $faqKey] = $this->compose($i);
        if ($text === null) {
            return $this->done($i, $persist, ['mode' => 'human', 'reason' => 'missing_data'] + $out);
        }
        $g = $this->guard->check($text, $i->product_id ? [$i->product_id] : null);
        if (! $g['ok'] || $g['removed'] || $g['flagged']) {
            return $this->done($i, $persist, ['mode' => 'human', 'reason' => 'truth_guard'] + $out);
        }
        $out = ['mode' => 'suggest', 'reply' => $text, 'faq_key' => $faqKey] + $out;
        if ($faqKey && $level >= 3 && $this->features->active('auto_reply') && $this->ix->realEvalPasses() && in_array($faqKey, (array) $this->config->get('auto_reply_allowlist', []), true) && ! $this->paused($i)) {
            $out['mode'] = 'auto';
            $out['auto_allowed'] = true;
        }

        return $this->done($i, $persist, $out);
    }

    public function paused(Interaction $i): bool
    {
        return ReputationAlert::where('status', 'open')->where('pause_auto_reply', true)
            ->where(fn ($q) => $q->where('product_id', $i->product_id)->orWhere(fn ($w) => $w->whereNull('product_id')->where('topic', $i->category)))->exists();
    }

    private function done(Interaction $i, bool $persist, array $out): array
    {
        if ($persist) {
            $i->update(['suggested_reply' => $out['reply'] ?? null, 'reply_mode' => in_array($out['mode'], ['suggest', 'auto', 'human'], true) ? $out['mode'] : null]);
        }

        return $out;
    }

    /** @return array{0:?string,1:?string} [text, faq key] built only from live facts / approved FAQ answers. */
    private function compose(Interaction $i): array
    {
        $ar = ($i->language ?: 'ar') === 'ar';
        $faq = $this->matchFaq($i);
        if ($faq) {
            return [$faq->body, 'faq'.$faq->id];
        }
        if (! $i->product_id) {
            return [null, null];
        }
        $tp = TenantProduct::where('product_id', $i->product_id)->where('is_active', true)->first();
        if (! $tp) {
            return [null, null];
        }
        $name = $tp->product?->getTranslation('name', $ar ? 'ar' : 'en', true) ?? '';
        $cur = current_tenant()->currency_symbol;
        if ($i->category === 'price') {
            $price = number_format((float) ($tp->sale_price ?: $tp->price), 2);

            return [$ar ? "سعر {$name} حالياً {$price} {$cur}." : "{$name} is currently {$price} {$cur}.", null];
        }
        if ($i->category === 'availability') {
            $on = (int) ($this->metrics->velocityRows(now())->firstWhere('product_id', $i->product_id)['on_hand'] ?? 0);

            return [$on > 0 ? ($ar ? "{$name} متاح حالياً." : "{$name} is in stock.") : ($ar ? "{$name} غير متاح حالياً." : "{$name} is currently out of stock."), null];
        }

        return [null, null];
    }

    private function matchFaq(Interaction $i): ?BrandMemoryEntry
    {
        $t = mb_strtolower($i->text_masked);
        foreach (BrandMemoryEntry::where('kind', 'faq')->where('is_active', true)->get() as $f) {
            $words = array_values(array_filter(preg_split('/[\s\?\؟,،\.]+/u', mb_strtolower($f->title)), fn ($w) => mb_strlen($w) >= 3));
            if (count($words) < 2) {
                continue;
            }
            $hit = count(array_filter($words, fn ($w) => str_contains($t, $w)));
            if ($hit / count($words) >= 0.6) {
                return $f;
            }
        }

        return null;
    }
}
