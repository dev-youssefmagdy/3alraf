<?php

namespace App\Services\Growth;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Ambassador;
use App\Models\AmbassadorReward;
use App\Models\Order;
use App\Models\TrackedLink;
use App\Services\Affiliates\AffiliateCommissionService;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Ambassador program extending Affiliates: opt-in, disclosure, per-channel tracked links, reward rules, report. A VALID order is delivered, not returned and
 * past the disclosed return window; anything canceled/returned never earns a reward. Rewards or ideas that pay for fake reviews / artificial engagement are refused.
 */
class AmbassadorService
{
    public const CHANNELS = ['instagram', 'facebook', 'tiktok', 'youtube', 'whatsapp', 'blog', 'other'];

    public const MAX_REWARD = 10000;

    private const FORBIDDEN = '/fake\s+(review|rating|like|follower|comment)|buy(ing)?\s+(review|like|follower)|paid\s+review|(review|rating)s?\s+(in\s+)?(exchange|for\s+(pay|reward|money|free))|positive\s+review|5[\s-]?star\s+review|artificial\s+engagement|like\s+for\s+like|تقييم(ات)?\s+(وهمي|مزيف|مدفوع|مقابل)|مراجع(ة|ات)\s+(وهمي|مزيف|مدفوع)|شراء\s+(لايك|متابع|تقييم)|لايكات\s+مدفوع|متابعين\s+وهمي|تعليقات\s+مدفوع/iu';

    public function __construct(private TrackedLinkService $links, private AffiliateCommissionService $commissions, private ActionLogger $log) {}

    /** Throws when a free-text rule / idea / disclosure rewards fake reviews or artificial engagement. */
    public function assertClean(string $field, ?string $text): void
    {
        if ($text && preg_match(self::FORBIDDEN, $text)) {
            throw ValidationException::withMessages([$field => __('growth_hub.amb.forbidden_text')]);
        }
    }

    public function enroll(int $affiliateId, array $d, ?int $adminId = null): Ambassador
    {
        $aff = Affiliate::find($affiliateId);
        if (! $aff || $aff->status !== AffiliateStatus::APPROVED) {
            throw ValidationException::withMessages(['affiliate_id' => __('growth_hub.amb.not_approved')]);
        }
        if (empty($d['terms_accepted'])) {
            throw ValidationException::withMessages(['terms_accepted' => __('growth_hub.amb.terms_required')]);
        }
        $this->assertClean('reward_rule', $d['reward_rule'] ?? null);
        $this->assertClean('disclosure', $d['disclosure'] ?? null);
        $reward = $adminId ? (float) ($d['reward_per_order'] ?? 0) : 0.0; // only an admin sets money
        if ($reward < 0 || $reward > self::MAX_REWARD) {
            throw ValidationException::withMessages(['reward_per_order' => __('growth_hub.amb.reward_range', ['max' => self::MAX_REWARD])]);
        }
        $a = Ambassador::updateOrCreate(['affiliate_id' => $aff->id], ['status' => 'active', 'disclosure' => mb_substr(trim((string) $d['disclosure']), 0, 300), 'terms_accepted_at' => now(),
            'reward_per_order' => $adminId ? $reward : (Ambassador::where('affiliate_id', $aff->id)->value('reward_per_order') ?? 0), 'reward_rule' => $adminId ? ($d['reward_rule'] ?? null) : Ambassador::where('affiliate_id', $aff->id)->value('reward_rule')]);
        $this->log->log('ambassador.enrolled', $adminId ? 'admin' : 'system', $adminId, null, $a);

        return $a;
    }

    public function update(Ambassador $a, array $d, ?int $adminId): Ambassador
    {
        $this->assertClean('reward_rule', $d['reward_rule'] ?? null);
        $this->assertClean('disclosure', $d['disclosure'] ?? null);
        if (isset($d['reward_per_order']) && ($d['reward_per_order'] < 0 || $d['reward_per_order'] > self::MAX_REWARD)) {
            throw ValidationException::withMessages(['reward_per_order' => __('growth_hub.amb.reward_range', ['max' => self::MAX_REWARD])]);
        }
        $a->update(collect($d)->only(['status', 'disclosure', 'reward_per_order', 'reward_rule'])->all());
        $this->log->log('ambassador.updated', 'admin', $adminId, null, $a);

        return $a;
    }

    /** Per-channel link: lands on the product page with the affiliate ref + utm_source=affiliate so the normal commission flow applies. */
    public function link(Ambassador $a, int $productId, string $channel): TrackedLink
    {
        if ($a->status !== 'active') {
            throw ValidationException::withMessages(['status' => __('growth_hub.amb.inactive')]);
        }
        $channel = in_array($channel, self::CHANNELS, true) ? $channel : 'other';
        $l = $this->links->create($productId, $channel, null, 'amb'.$a->id);
        $l->update(['ambassador_id' => $a->id]);

        return $l;
    }

    public function window(): int
    {
        return $this->commissions->returnWindowDays();
    }

    /**
     * Idempotent reward ledger (one row per order). valid = delivered, not returned, past the return window; excluded = cancelled/returned (also after having
     * been valid, unless already paid); pending = everything else. Amount = reward_per_order, frozen once valid. No new money rules: payment happens with the
     * affiliate payout (settle()).
     *
     * @return int rows written
     */
    public function syncLedger(?Ambassador $only = null): int
    {
        $n = 0;
        $cutoff = now()->subDays($this->window());
        foreach ($only ? collect([$only]) : Ambassador::where('status', 'active')->get() as $a) {
            $orders = Order::with('returns')->where('affiliate_id', $a->affiliate_id)->get();
            $rows = AmbassadorReward::where('ambassador_id', $a->id)->get()->keyBy('order_id');
            foreach ($orders as $o) {
                $st = $o->status?->value ?? $o->status;
                $row = $rows->get($o->id);
                if ($row && $row->status === 'paid') {
                    continue;
                }
                if (in_array($st, ['cancelled', 'returned'], true)) {
                    [$status, $reason] = ['excluded', $st];
                } elseif ($st === 'delivered' && $o->delivered_at && $o->delivered_at->lte($cutoff)) {
                    [$status, $reason] = ['valid', null];
                } else {
                    [$status, $reason] = [$row && $row->status === 'valid' ? 'valid' : 'pending', null]; // a valid row never falls back to pending
                }
                if (! $row && $status === 'excluded') {
                    continue; // nothing was ever promised for this order
                }
                $amount = $row && $row->status === 'valid' ? (float) $row->amount : (float) $a->reward_per_order;
                if (! $row) {
                    AmbassadorReward::create(['ambassador_id' => $a->id, 'affiliate_id' => $a->affiliate_id, 'order_id' => $o->id, 'amount' => $amount, 'status' => $status, 'reason' => $reason, 'valid_at' => $status === 'valid' ? now() : null]);
                    $n++;
                } elseif ($row->status !== $status || (float) $row->amount !== $amount) {
                    $row->update(['status' => $status, 'reason' => $reason, 'amount' => $amount, 'valid_at' => $status === 'valid' ? ($row->valid_at ?? now()) : null]);
                    $n++;
                }
            }
        }

        return $n;
    }

    /** Called when an affiliate payout is marked paid: the affiliate's valid rewards are settled in the same payment run. */
    public function settle(int $affiliateId): int
    {
        return AmbassadorReward::where('affiliate_id', $affiliateId)->where('status', 'valid')->update(['status' => 'paid', 'paid_at' => now()]);
    }

    /** Participant report for a period (by placed_at). */
    public function report(Ambassador $a, CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = Order::with('items', 'returns')->where('affiliate_id', $a->affiliate_id)->whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $st = fn ($o) => $o->status?->value ?? $o->status;
        $cutoff = now()->subDays($this->window());
        $valid = $orders->filter(fn ($o) => $st($o) === 'delivered' && $o->delivered_at && $o->delivered_at->lte($cutoff));
        $pending = $orders->filter(fn ($o) => in_array($st($o), ['pending', 'confirmed', 'processing', 'shipped'], true) || ($st($o) === 'delivered' && (! $o->delivered_at || $o->delivered_at->gt($cutoff))));
        $excluded = $orders->filter(fn ($o) => in_array($st($o), ['cancelled', 'returned'], true));
        $counted = $valid->merge($pending);
        $new = $counted->filter(fn ($o) => $o->customer_id && ! Order::where('customer_id', $o->customer_id)->where('status', '!=', 'cancelled')->where('placed_at', '<', $o->placed_at)->exists())->pluck('customer_id')->unique()->count();
        $cm = 0.0;
        $missing = false;
        foreach ($valid as $o) {
            $f = StatsAggregator::orderFigures($o);
            $cm += $f['cm'];
            $missing = $missing || $f['missing_cogs'] || $f['missing_shipping'];
        }
        $this->syncLedger($a);
        $reward = round((float) AmbassadorReward::where('ambassador_id', $a->id)->whereIn('status', ['valid', 'paid'])->whereIn('order_id', $orders->pluck('id'))->sum('amount'), 2);
        $commission = round((float) $valid->sum('affiliate_commission'), 2);
        $codes = TrackedLink::where('ambassador_id', $a->id)->get(['code', 'channel', 'clicks']);
        $byChannel = $codes->groupBy('channel')->map(fn ($g) => ['clicks' => (int) $g->sum('clicks'),
            'orders' => $orders->filter(fn ($o) => in_array(data_get($o->attribution, 'utm_content'), $g->map(fn ($l) => 'tl'.$l->code)->all(), true) && $st($o) !== 'cancelled')->count()])->all();

        return ['valid_orders' => $valid->count(), 'pending_orders' => $pending->count(), 'excluded_orders' => $excluded->count(), 'new_customers' => $new,
            'new_customer_share' => $counted->count() > 0 ? round($new / $counted->count() * 100, 1) : null, 'window_days' => $this->window(),
            'reward_cost' => $reward, 'commission_cost' => $commission, 'referral_cac' => $new > 0 ? round(($reward + $commission) / $new, 2) : null,
            'margin_after_rewards' => $valid->isEmpty() ? null : ($missing ? null : round($cm - $reward, 2)), 'by_channel' => $byChannel, 'disclosure' => $a->disclosure];
    }
}
