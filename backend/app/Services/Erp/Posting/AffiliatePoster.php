<?php

namespace App\Services\Erp\Posting;

use App\Models\AffiliateBonus;
use App\Models\AffiliatePayout;
use App\Models\Erp\JournalEntry;
use App\Support\Money;

/**
 * Affiliate money out (ERP-003). Commissions are accrued at revenue recognition (Dr Affiliate expense / Cr Affiliate payable, SalesPoster);
 * paying them clears the liability:  payout paid : Dr Affiliate payable / Cr cash-like account (payout.paid_from, default cash).
 * Sales bonuses accrue the same way when awarded: Dr Affiliate expense / Cr Affiliate payable (sub-ledger affiliate); the payout clears them too.
 */
class AffiliatePoster extends Poster
{
    public const PAY_ROLES = ['cash', 'bank', 'pos_cash', 'gateway', 'wallet'];

    private function from(?string $role): string
    {
        return in_array($role, self::PAY_ROLES, true) ? $role : 'cash';
    }

    public function payoutPaid(AffiliatePayout $p): ?JournalEntry
    {
        $amount = Money::round((string) $p->amount);
        if (! $this->pos($amount)) {
            return null;
        }
        $m = 'PAYOUT-'.$p->id;

        return $this->post('affpayout:'.$p->id, $this->today(), 'Affiliate payout #'.$p->id, 'affiliate_payout', $p->id, [
            $this->dr('affiliate_payable', $amount, null, $m, 'affiliate', $p->affiliate_id), $this->cr($this->from($p->paid_from), $amount, null, $m),
        ]);
    }

    /** Sales bonus awarded (docs/CALCULATIONS.md §Affiliate sales bonus); key affbonus:{id}, posted once. */
    public function bonusAwarded(AffiliateBonus $b): ?JournalEntry
    {
        $amount = Money::round((string) $b->amount);
        if (! $this->pos($amount)) {
            return null;
        }
        $m = 'BONUS-'.$b->id;

        return $this->post('affbonus:'.$b->id, $this->today(), 'Affiliate sales bonus #'.$b->milestone.' (affiliate '.$b->affiliate_id.')', 'affiliate_bonus', $b->id, [
            $this->dr('affiliate_expense', $amount, null, $m), $this->cr('affiliate_payable', $amount, null, $m, 'affiliate', $b->affiliate_id),
        ]);
    }
}
