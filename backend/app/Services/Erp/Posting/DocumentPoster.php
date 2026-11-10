<?php

namespace App\Services\Erp\Posting;

use App\Enums\OrderStatus;
use App\Exceptions\AccountingException;
use App\Models\AffiliateBonus;
use App\Models\AffiliatePayout;
use App\Models\Erp\AuditLog;
use App\Models\Erp\JournalEntry;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Services\Erp\Accounting\JournalService;

/**
 * Re-runs the posters of ONE business document from its current state (idempotent: existing live keys are reused).
 * Used by the "retry" of a failed posting (ERP-016) and by "repost" of an auto entry (ERP-007): an auto entry produced by a business
 * document is never reversed by hand - the operator reposts it, which reverses the document's entry family and posts a corrected version
 * (versioned idempotency key "{key}:v{n}") from the document as it is now.
 */
class DocumentPoster
{
    /** Source types whose auto entries mirror a business document (manual reversal forbidden, repost instead). */
    public const DOCUMENT_SOURCES = ['order', 'order_return', 'purchase_order', 'stock_adjustment', 'stock_transfer', 'expense', 'affiliate_payout', 'affiliate_bonus', 'ambassador_rewards'];

    public function __construct(private SalesPoster $sales, private PaymentPoster $payments, private ReturnPoster $returns, private PurchasePoster $purchases,
        private StockPoster $stock, private ExpensePoster $expenses, private AffiliatePoster $affiliates, private JournalService $journals) {}

    public static function isDocumentEntry(JournalEntry $e): bool
    {
        return $e->type === 'auto' && in_array($e->source_type, self::DOCUMENT_SOURCES, true) && $e->source_id;
    }

    /** Post whatever this document should have in the ledger and does not yet have. @return bool false when the document no longer exists */
    public function post(string $type, int $id): bool
    {
        switch ($type) {
            case 'order':
                $o = Order::find($id);
                if (! $o) {
                    return false;
                }
                if ($o->status === OrderStatus::CANCELLED) {
                    $this->payments->sync($o);
                    $this->sales->cancelled($o);
                    $this->payments->cancelRefund($o);

                    return true;
                }
                $this->sales->confirmed($o, $o->status === OrderStatus::RETURNED);
                $this->sales->shipped($o);
                $this->sales->syncCommission($o, 'resync');
                $this->payments->sync($o);
                if ($o->carrier_settled_at) {
                    $this->payments->carrierSettled($o);
                }

                return true;
            case 'order_return':
                $r = OrderReturn::find($id);
                if (! $r || ! in_array($r->status->value, ['approved', 'refunded'], true)) {
                    return (bool) $r;
                }
                $this->returns->approved($r);
                if ($r->status->value === 'refunded') {
                    $this->returns->refunded($r);
                }

                return true;
            case 'purchase_order':
                $po = PurchaseOrder::find($id);
                if ($po && $po->status->value === 'received') {
                    $this->purchases->received($po);
                }

                return (bool) $po;
            case 'stock_adjustment':
                $a = StockAdjustment::find($id);
                $a && $this->stock->adjusted($a);

                return (bool) $a;
            case 'stock_transfer':
                $t = StockTransfer::find($id);
                if ($t && in_array($t->status->value, ['in_transit', 'received'], true)) {
                    $this->stock->transferSent($t);
                    $t->status->value === 'received' && $this->stock->transferReceived($t);
                }

                return (bool) $t;
            case 'expense':
                $e = Expense::withTrashed()->find($id);
                if ($e) {
                    $e->trashed() ? $this->expenses->deleted($e) : $this->expenses->sync($e);
                }

                return (bool) $e;
            case 'affiliate_payout':
                $p = AffiliatePayout::find($id);
                if ($p && $p->status->value === 'paid') {
                    $this->affiliates->payoutPaid($p);
                }

                return (bool) $p;
            case 'affiliate_bonus':
                $b = AffiliateBonus::find($id);
                if ($b && $b->status->value !== 'voided') {
                    $this->affiliates->bonusAwarded($b);
                }

                return (bool) $b;
        }

        return false;
    }

    /**
     * Repost an auto document entry: reverse it (for orders: its whole recognition family, so COGS true-ups and commission deltas never double),
     * then post the document again from its current data. Needs erp.journals.reverse + erp.journals.post (controller).
     */
    public function repost(JournalEntry $e, ?int $userId, string $reason): ?JournalEntry
    {
        if (! self::isDocumentEntry($e) || $e->status !== 'posted') {
            throw new AccountingException(__('erp_acc.errors.not_repostable'));
        }
        $key = (string) $e->idempotency_key;
        $base = preg_replace('/:v\d+$/', '', $key);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($e, $userId, $reason, $base) {
            $family = $e->source_type === 'order' && preg_match('/^order:\d+:(confirm|ship|cogs-trueup|commission:)/', $base);
            if ($family) {
                $id = $e->source_id;
                foreach (JournalEntry::where('source_type', 'order')->where('source_id', $id)->where('status', 'posted')->where('type', 'auto')
                    ->where(fn ($w) => $w->where('idempotency_key', 'like', "order:$id:confirm%")->orWhere('idempotency_key', 'like', "order:$id:ship%")
                        ->orWhere('idempotency_key', 'like', "order:$id:cogs-trueup%")->orWhere('idempotency_key', 'like', "order:$id:commission:%"))
                    ->orderByDesc('id')->get() as $x) {
                    $this->journals->reverse($x, $userId, null, 'repost: '.$reason);
                }
            } else {
                $this->journals->reverse($e, $userId, null, 'repost: '.$reason);
            }
            Poster::reposting(fn () => $this->post($e->source_type, (int) $e->source_id));
            AuditLog::create(['action' => 'journal.reposted', 'subject_type' => 'journal', 'subject_id' => $e->id, 'user_id' => $userId, 'meta' => ['key' => $base, 'reason' => $reason]]);

            return Poster::latest($base);
        });
    }
}
