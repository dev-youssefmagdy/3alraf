<?php

namespace App\Listeners\Erp;

use App\Events\Erp\AffiliateBonusAwarded;
use App\Events\Erp\AffiliatePayoutPaid;
use App\Events\Erp\ExpenseDeleted;
use App\Events\Erp\ExpenseSaved;
use App\Events\Erp\PurchaseCancelled;
use App\Events\Erp\PurchaseReceived;
use App\Events\Erp\StockAdjusted;
use App\Events\Erp\StockTransferMoved;
use App\Services\Erp\Posting\AffiliatePoster;
use App\Services\Erp\Posting\ExpensePoster;
use App\Services\Erp\Posting\PostingGuard;
use App\Services\Erp\Posting\PurchasePoster;
use App\Services\Erp\Posting\StockPoster;

/** Thin adapters: purchases / stock / expenses hooks -> ledger posters. */
class PostOperationsEvents
{
    public function __construct(private PostingGuard $guard, private PurchasePoster $purchases, private StockPoster $stock, private ExpensePoster $expenses,
        private AffiliatePoster $affiliates) {}

    public function subscribe($events): array
    {
        return [
            PurchaseReceived::class => 'onPurchaseReceived',
            PurchaseCancelled::class => 'onPurchaseCancelled',
            StockAdjusted::class => 'onStockAdjusted',
            StockTransferMoved::class => 'onTransferMoved',
            ExpenseSaved::class => 'onExpenseSaved',
            ExpenseDeleted::class => 'onExpenseDeleted',
            AffiliatePayoutPaid::class => 'onAffiliatePayoutPaid',
            AffiliateBonusAwarded::class => 'onAffiliateBonusAwarded',
        ];
    }

    public function onPurchaseReceived(PurchaseReceived $e): void
    {
        $this->guard->run('purchase_order', $e->purchase->id, 'purchase.received', fn () => $this->purchases->received($e->purchase));
    }

    public function onPurchaseCancelled(PurchaseCancelled $e): void
    {
        $this->guard->run('purchase_order', $e->purchase->id, 'purchase.cancelled', fn () => $this->purchases->cancelled($e->purchase));
    }

    public function onStockAdjusted(StockAdjusted $e): void
    {
        $this->guard->run('stock_adjustment', $e->adjustment->id, 'stock.adjusted', fn () => $this->stock->adjusted($e->adjustment));
    }

    public function onTransferMoved(StockTransferMoved $e): void
    {
        $t = $e->transfer;
        $this->guard->run('stock_transfer', $t->id, 'transfer.'.$e->stage, fn () => $e->stage === 'sent' ? $this->stock->transferSent($t) : $this->stock->transferReceived($t));
    }

    public function onExpenseSaved(ExpenseSaved $e): void
    {
        $this->guard->run('expense', $e->expense->id, 'expense.saved', fn () => $this->expenses->sync($e->expense));
    }

    public function onExpenseDeleted(ExpenseDeleted $e): void
    {
        $this->guard->run('expense', $e->expense->id, 'expense.deleted', fn () => $this->expenses->deleted($e->expense));
    }

    public function onAffiliatePayoutPaid(AffiliatePayoutPaid $e): void
    {
        $p = $e->payout;
        $this->guard->run('affiliate_payout', $p->id, 'affiliate.payout_paid', fn () => $this->affiliates->payoutPaid($p));
    }

    public function onAffiliateBonusAwarded(AffiliateBonusAwarded $e): void
    {
        $b = $e->bonus;
        $this->guard->run('affiliate_bonus', $b->id, 'affiliate.bonus_awarded', fn () => $this->affiliates->bonusAwarded($b));
    }
}
