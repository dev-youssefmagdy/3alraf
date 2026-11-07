<?php

namespace App\Listeners\Erp;

use App\Events\Erp\ExpenseDeleted;
use App\Events\Erp\ExpenseSaved;
use App\Events\Erp\PurchaseCancelled;
use App\Events\Erp\PurchaseReceived;
use App\Events\Erp\StockAdjusted;
use App\Events\Erp\StockTransferMoved;
use App\Services\Erp\Posting\ExpensePoster;
use App\Services\Erp\Posting\PostingGuard;
use App\Services\Erp\Posting\PurchasePoster;
use App\Services\Erp\Posting\StockPoster;

/** Thin adapters: purchases / stock / expenses hooks -> ledger posters. */
class PostOperationsEvents
{
    public function __construct(private PostingGuard $guard, private PurchasePoster $purchases, private StockPoster $stock, private ExpensePoster $expenses,
        private \App\Services\Erp\Posting\AffiliatePoster $affiliates) {}

    public function subscribe($events): array
    {
        return [
            \App\Events\Erp\PurchaseReceived::class => 'onPurchaseReceived',
            \App\Events\Erp\PurchaseCancelled::class => 'onPurchaseCancelled',
            \App\Events\Erp\StockAdjusted::class => 'onStockAdjusted',
            \App\Events\Erp\StockTransferMoved::class => 'onTransferMoved',
            \App\Events\Erp\ExpenseSaved::class => 'onExpenseSaved',
            \App\Events\Erp\ExpenseDeleted::class => 'onExpenseDeleted',
            \App\Events\Erp\AffiliatePayoutPaid::class => 'onAffiliatePayoutPaid',
            \App\Events\Erp\AmbassadorRewardsPaid::class => 'onAmbassadorRewardsPaid',
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

    public function onAffiliatePayoutPaid(\App\Events\Erp\AffiliatePayoutPaid $e): void
    {
        $p = $e->payout;
        $this->guard->run('affiliate_payout', $p->id, 'affiliate.payout_paid', fn () => $this->affiliates->payoutPaid($p));
    }

    public function onAmbassadorRewardsPaid(\App\Events\Erp\AmbassadorRewardsPaid $e): void
    {
        $this->guard->run('ambassador_rewards', $e->affiliateId, 'ambassador.rewards_paid', fn () => $this->affiliates->rewardsPaid($e->affiliateId, $e->amount, $e->ref, $e->paidFrom));
    }
}
