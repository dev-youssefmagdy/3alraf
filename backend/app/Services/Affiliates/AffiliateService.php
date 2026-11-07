<?php

namespace App\Services\Affiliates;

use App\DTOs\AffiliateRegisterData;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Product;
use App\Repositories\Contracts\AffiliateCommissionRepositoryInterface;
use App\Repositories\Contracts\AffiliatePayoutRepositoryInterface;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Services\Qr\QrCodeManager;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffiliateService
{
    public function __construct(
        private AffiliateRepositoryInterface $affiliates,
        private AffiliatePayoutRepositoryInterface $payouts,
        private AffiliateCommissionRepositoryInterface $commissions,
        private AffiliateCommissionService $commissionService,
        private AffiliateTracker $tracker,
        private QrCodeManager $qr,
    ) {}

    public function find(int|string $id): Affiliate
    {
        return $this->affiliates->findOrFail($id);
    }

    /** New accounts wait for admin approval. */
    public function register(AffiliateRegisterData $d): Affiliate
    {
        return $this->affiliates->create(['name' => $d->name, 'email' => $d->email, 'phone' => $d->phone, 'phone_country_code' => $d->phoneCode, 'password' => $d->password, 'status' => AffiliateStatus::PENDING]);
    }

    public function setStatus(Affiliate $a, AffiliateStatus $status): Affiliate
    {
        $a = $this->affiliates->update($a, ['status' => $status, 'approved_at' => $status === AffiliateStatus::APPROVED ? ($a->approved_at ?? now()) : $a->approved_at]);
        $this->qr->affiliateChanged($a); // approved => QR for every product/variant, otherwise removed

        return $a;
    }

    public function delete(Affiliate $a): void
    {
        $this->qr->deleteForAffiliate($a);
        $this->affiliates->delete($a);
    }

    /** Dashboard numbers for one affiliate. */
    public function stats(Affiliate $a): array
    {
        $clicks = $a->clicks()->count();
        $orders = $a->orders()->count();
        $sales = $this->commissionService->lifetimeSales($a);
        $tier = $this->commissionService->tierFor($sales);
        $next = \App\Models\AffiliateTier::query()->whereRaw('min_sales > ?', [$sales])->orderBy('min_sales')->first();

        return [
            'clicks' => $clicks, 'orders' => $orders,
            'conversion' => $clicks > 0 ? round($orders / $clicks * 100, 1) : 0.0,
            'sales' => $sales, 'tier' => $tier, 'next_tier' => $next,
            'commissions' => $this->commissionService->totals($a),
        ];
    }

    /** Sellable products with the affiliate's unique link + stored QR (->qr_url) and, for variable products, per-variant links/QR (->variant_links). */
    public function products(Affiliate $a)
    {
        $codes = $this->qr->affiliateCodes($a);

        return Product::query()->where('is_active', true)->whereNotNull('price')
            ->with(['images', 'variants.values'])->latest('id')->get()
            ->each(function (Product $p) use ($a, $codes) {
                $p->setAttribute('affiliate_url', $this->tracker->productUrl($a, $p));
                $p->setAttribute('qr_url', $codes->get($this->qr->codeKey($p))?->file_url);
                $p->setAttribute('variant_links', $p->variants->filter(fn ($v) => $v->is_active)->map(fn ($v) => [
                    'id' => $v->id, 'label' => $v->label(), 'sku' => $v->sku,
                    'link' => $this->qr->url($p->slug, $v->id, $a),
                    'qr_url' => $codes->get($this->qr->codeKey($v))?->file_url,
                ])->values()->all());
            });
    }

    /** Pays out every approved, not yet requested commission. */
    public function requestPayout(Affiliate $a): AffiliatePayout
    {
        return DB::transaction(function () use ($a) {
            $rows = $this->commissions->query()->where('affiliate_id', $a->id)->where('status', CommissionStatus::APPROVED->value)->whereNull('payout_id')->lockForUpdate()->get();
            $amount = Money::round((string) $rows->sum('amount'));
            if ($rows->isEmpty() || Money::cmp($amount, '0') <= 0) {
                throw ValidationException::withMessages(['amount' => __('growth.nothing_to_payout')]);
            }
            $payout = $this->payouts->create(['affiliate_id' => $a->id, 'amount' => $amount, 'status' => PayoutStatus::REQUESTED]);
            $this->commissions->query()->whereIn('id', $rows->pluck('id'))->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    /** @param string|null $paidFrom account the money left (cash|bank|pos_cash|wallet|gateway; default cash) - ERP: Dr Affiliate payable / Cr it */
    public function markPaid(AffiliatePayout $p, ?string $note = null, ?string $paidFrom = null): AffiliatePayout
    {
        $this->ensureRequested($p);
        if ($paidFrom !== null && ! in_array($paidFrom, \App\Services\Erp\Posting\AffiliatePoster::PAY_ROLES, true)) {
            throw ValidationException::withMessages(['paid_from' => __('validation.in', ['attribute' => 'paid_from'])]);
        }

        return DB::transaction(function () use ($p, $note, $paidFrom) {
            $p->commissions()->where('status', CommissionStatus::APPROVED->value)->update(['status' => CommissionStatus::PAID->value]);
            // voided commissions inside the payout reduce what is actually paid
            $paid = Money::round((string) $p->commissions()->where('status', CommissionStatus::PAID->value)->sum('amount'));

            $p = $this->payouts->update($p, ['status' => PayoutStatus::PAID, 'amount' => $paid, 'paid_from' => $paidFrom ?? 'cash', 'paid_at' => now(), 'note' => $note]);
            DB::afterCommit(fn () => event(new \App\Events\Erp\AffiliatePayoutPaid($p))); // ERP: clears the affiliate payable

            return $p;
        });
    }

    public function rejectPayout(AffiliatePayout $p, ?string $note = null): AffiliatePayout
    {
        $this->ensureRequested($p);

        return DB::transaction(function () use ($p, $note) {
            $p->commissions()->update(['payout_id' => null]);

            return $this->payouts->update($p, ['status' => PayoutStatus::REJECTED, 'note' => $note]);
        });
    }

    public function findPayout(int|string $id): AffiliatePayout
    {
        return $this->payouts->findOrFail($id);
    }

    private function ensureRequested(AffiliatePayout $p): void
    {
        if ($p->status !== PayoutStatus::REQUESTED) {
            throw ValidationException::withMessages(['status' => __('growth.payout_closed')]);
        }
    }
}
