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
    public function __construct(private PostingGuard $guard, private PurchasePoster $purchases, private StockPoster $stock, private ExpensePoster $expenses) {}

    public function subscribe($events): array
    {
        return [
            \App\Events\Erp\PurchaseReceived::class => 'onPurchaseReceived',
            \App\Events\Erp\PurchaseCancelled::class => 'onPurchaseCancelled',
            \App\Events\Erp\StockAdjusted::class => 'onStockAdjusted',
            \App\Events\Erp\StockTransferMoved::class => 'onTransferMoved',
            \App\Events\Erp\ExpenseSaved::class => 'onExpenseSaved',
            \App\Events\Erp\ExpenseDeleted::class => 'onExpenseDeleted',
        ];
    }

    public function onPurchaseReceived(PurchaseReceived $e): void
    {
        $this->guard->run($e->purchase->tenant_id, 'purchase_order', $e->purchase->id, 'purchase.received', fn () => $this->purchases->received($e->purchase));
    }

    public function onPurchaseCancelled(PurchaseCancelled $e): void
    {
        $this->guard->run($e->purchase->tenant_id, 'purchase_order', $e->purchase->id, 'purchase.cancelled', fn () => $this->purchases->cancelled($e->purchase));
    }

    public function onStockAdjusted(StockAdjusted $e): void
    {
        $this->guard->run($e->adjustment->tenant_id, 'stock_adjustment', $e->adjustment->id, 'stock.adjusted', fn () => $this->stock->adjusted($e->adjustment));
    }

    public function onTransferMoved(StockTransferMoved $e): void
    {
        $t = $e->transfer;
        $this->guard->run($t->tenant_id, 'stock_transfer', $t->id, 'transfer.'.$e->stage, fn () => $e->stage === 'sent' ? $this->stock->transferSent($t) : $this->stock->transferReceived($t));
    }

    public function onExpenseSaved(ExpenseSaved $e): void
    {
        $this->guard->run($e->expense->tenant_id, 'expense', $e->expense->id, 'expense.saved', fn () => $this->expenses->sync($e->expense));
    }

    public function onExpenseDeleted(ExpenseDeleted $e): void
    {
        $this->guard->run($e->expense->tenant_id, 'expense', $e->expense->id, 'expense.deleted', fn () => $this->expenses->deleted($e->expense));
    }
}
