<?php

namespace App\Services\Erp\Posting;

use App\Models\AffiliatePayout;
use App\Models\Erp\JournalEntry;
use App\Support\Money;

/**
 * Affiliate money out (ERP-003). Commissions are accrued at revenue recognition (Dr Affiliate expense / Cr Affiliate payable, SalesPoster);
 * paying them clears the liability:  payout paid : Dr Affiliate payable / Cr cash-like account (payout.paid_from, default cash).
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
}
