<?php

namespace App\Services\Affiliates;

use App\DTOs\AffiliateRegisterData;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\TenantProduct;
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
        return $this->affiliates->create(['name' => $d->name, 'email' => $d->email, 'phone' => $d->phone, 'password' => $d->password, 'status' => AffiliateStatus::PENDING]);
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

        return TenantProduct::query()->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with(['product.images', 'variants.variant.values'])->latest('id')->get()
            ->each(function (TenantProduct $tp) use ($a, $codes) {
                $tp->setAttribute('affiliate_url', $this->tracker->productUrl($a, $tp->product));
                $tp->setAttribute('qr_url', $codes->get($this->qr->codeKey($tp))?->file_url);
                $tp->setAttribute('variant_links', $tp->variants->filter(fn ($tv) => $tv->is_active && $tv->variant && ! $tv->variant->trashed() && $tv->variant->is_active)->map(fn ($tv) => [
                    'id' => $tv->product_variant_id, 'label' => $tv->variant->label(), 'sku' => $tv->variant->sku,
                    'link' => $this->qr->url(current_tenant(), $tp->product->slug, $tv->product_variant_id, $a),
                    'qr_url' => $codes->get($this->qr->codeKey($tv))?->file_url,
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

    public function markPaid(AffiliatePayout $p, ?string $note = null): AffiliatePayout
    {
        $this->ensureRequested($p);

        return DB::transaction(function () use ($p, $note) {
            $p->commissions()->where('status', CommissionStatus::APPROVED->value)->update(['status' => CommissionStatus::PAID->value]);
            // voided commissions inside the payout reduce what is actually paid
            $paid = Money::round((string) $p->commissions()->where('status', CommissionStatus::PAID->value)->sum('amount'));

            return $this->payouts->update($p, ['status' => PayoutStatus::PAID, 'amount' => $paid, 'paid_at' => now(), 'note' => $note]);
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
