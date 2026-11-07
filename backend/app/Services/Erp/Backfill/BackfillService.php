<?php

namespace App\Services\Erp\Backfill;

use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\BackfillRun;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Period;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Expense;
use App\Services\Erp\Accounting\AccountResolver;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Posting\ExpensePoster;
use App\Services\Erp\Posting\PaymentPoster;
use App\Services\Erp\Posting\Poster;
use App\Services\Erp\Posting\PurchasePoster;
use App\Services\Erp\Posting\ReturnPoster;
use App\Services\Erp\Posting\SalesPoster;
use App\Services\Erp\Posting\StockPoster;
use App\Services\Erp\Reports\InventoryReport;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Historical data migration (docs/ERP_PLAN.md phase 9). Idempotent: documents are posted through the Phase-4 posters with their deterministic
 * idempotency keys and their OWN business date, so a second run finds every key and creates nothing. Business rows are never modified.
 *
 * Documents confirmed before --from are carried by auditable opening-balance entries (receivable per customer, carrier COD receivable,
 * supplier payable, inventory per branch against `opening_equity`); cash/bank can not be inferred and is left to a manual opening journal.
 */
class BackfillService
{
    /** Document types in processing order. `opening` posts the balance-sheet snapshot (inventory last, once the documents are in). */
    public const TYPES = ['opening', 'purchases', 'orders', 'payments', 'returns', 'stock', 'expenses'];

    private const CHUNK = 200;

    public function __construct(private SalesPoster $sales, private PaymentPoster $payments, private ReturnPoster $returns, private PurchasePoster $purchases,
        private StockPoster $stock, private ExpensePoster $expenses, private JournalService $journals) {}

    /** SQL expression: first confirmation date of an order (history), falling back to placed/created date for confirmed orders without history; NULL = never confirmed. */
    private static function confExpr(): string
    {
        $sales = "'".implode("','", OpeningState::salesValues())."'";

        return "COALESCE((SELECT MIN(h.created_at) FROM order_status_histories h WHERE h.order_id = orders.id AND h.to_status IN ($sales)), CASE WHEN orders.status IN ($sales,'returned') THEN COALESCE(orders.placed_at, orders.created_at) END)";
    }

    // ------------------------------------------------------------------ driver

    /**
     * @param  array<int,string>  $only  subset of TYPES (empty = all)
     * @param  ?callable  $progress  fn(string $event 'start'|'tick'|'end', string $type, int $n)
     * @return array{status:string,counts:array,opening:array,run:?BackfillRun}
     */
    public function run(string $from, bool $dry = false, array $only = [], ?callable $progress = null, string $by = 'cli', ?int $userId = null): array
    {
        $types = $only ? array_values(array_intersect(self::TYPES, $only)) : self::TYPES;
        $settings = app(ErpSettings::class);
        $existing = $settings->get('opening_date');
        if ($existing && $existing !== $from && in_array('opening', $types, true)) {
            throw new RuntimeException(__('erp.bf.err_opening_date', ['date' => $existing]));
        }
        $closed = $this->closedPeriods($from);
        if ($closed && ! $dry) {
            throw new RuntimeException(__('erp.bf.err_closed', ['periods' => implode(', ', $closed)]));
        }

        $run = null;
        if (! $dry) {
            $this->seed($from);
            if (in_array('opening', $types, true)) {
                $settings->set('opening_date', $from);
            }
            $run = BackfillRun::create(['from_date' => $from, 'started_at' => now(), 'status' => 'running', 'dry_run' => false, 'options' => ['only' => $only],
                'triggered_by' => $by, 'user_id' => $userId]);
        }
        $counts = [];
        $opening = [];
        try {
            foreach ($types as $type) {
                if ($type === 'opening') {
                    $opening = $this->opening($from, $dry, $counts, $progress, false);

                    continue;
                }
                $counts[$type] = $this->process($type, $from, $dry, $progress, $by === 'cli' ? null : $userId);
            }
            if (in_array('opening', $types, true)) {
                $opening += $this->opening($from, $dry, $counts, $progress, true); // inventory last: needs the documents in the ledger
            }
        } catch (Throwable $e) {
            if ($run) {
                $run->update(['status' => 'failed', 'finished_at' => now(), 'counts' => $counts + ['error' => $e->getMessage()]]);
            }
            throw $e;
        }

        $failed = array_sum(array_column($counts, 'failed'));
        $status = $dry ? 'dry_run' : ($failed ? 'partial' : 'completed');
        if ($run) {
            $run->update(['status' => $status, 'finished_at' => now(), 'counts' => $counts]);
            AuditLog::create(['action' => 'backfill.finished', 'subject_type' => 'backfill_run', 'subject_id' => $run->id, 'user_id' => $userId, 'meta' => ['from' => $from, 'status' => $status, 'counts' => $counts]]);
            if ($status === 'completed' && ! $only && ! $settings->bool('auto_posting_enabled')) {
                $settings->set('auto_posting_enabled', true);
                AuditLog::create(['action' => 'auto_posting.enabled', 'subject_type' => 'erp_settings', 'subject_id' => 0, 'user_id' => $userId, 'meta' => ['by' => 'backfill']]);
            }
        }

        return ['status' => $status, 'counts' => $counts, 'opening' => $opening, 'run' => $run, 'closed' => $closed];
    }

    /** @return array<int,string> names of closed periods that the backfill would have to post into */
    public function closedPeriods(string $from): array
    {
        return Period::where('ends_on', '>=', $from)->where('status', '!=', 'open')->orderBy('starts_on')->pluck('name')->all();
    }

    private function seed(string $from): void
    {
        if (! Account::where('role_key', 'receivable')->exists()) {
            app(CoaTemplate::class)->seed();
            app(AccountResolver::class)->flush();
        }
        $periods = app(PeriodService::class);
        for ($y = (int) substr($from, 0, 4); $y <= (int) now()->format('Y'); $y++) {
            $periods->createYear($y);
        }
    }

    // ------------------------------------------------------------------ documents

    /** Candidate documents of one type dated >= $from. @return \Illuminate\Support\LazyCollection */
    public function documents(string $type, string $from)
    {
        $d = $from.' 00:00:00';

        return match ($type) {
            'purchases' => PurchaseOrder::where('status', 'received')->where('received_at', '>=', $d)->lazyById(self::CHUNK),
            'orders' => Order::where('status', '!=', 'cancelled')->whereRaw('('.self::confExpr().') >= ?', [$d])->lazyById(self::CHUNK),
            'payments' => Order::where('status', '!=', 'cancelled')->where(fn ($q) => $q->where('paid_amount', '>', 0)->orWhereNotNull('carrier_settled_at'))
                ->whereRaw('COALESCE(('.self::confExpr().'), orders.placed_at, orders.created_at) >= ?', [$d])->lazyById(self::CHUNK),
            'returns' => OrderReturn::whereIn('status', ['approved', 'refunded'])->whereRaw('COALESCE(approved_at, created_at) >= ?', [$d])
                ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->lazyById(self::CHUNK),
            'stock' => $this->stockDocuments($from),
            'expenses' => Expense::where('expense_date', '>=', $from)->lazyById(self::CHUNK),
        };
    }

    private function stockDocuments(string $from)
    {
        $d = $from.' 00:00:00';
        $adj = StockAdjustment::where('created_at', '>=', $d)->lazyById(self::CHUNK);
        $trf = StockTransfer::whereIn('status', ['in_transit', 'received'])
            ->where(fn ($q) => $q->where('sent_at', '>=', $d)->orWhereNull('received_at')->orWhere('received_at', '>=', $d))->lazyById(self::CHUNK);

        return $adj->concat($trf);
    }

    private function key(string $k): bool
    {
        return JournalEntry::where('idempotency_key', $k)->exists();
    }

    /** A document key counts as posted only while a LIVE version exists: a manually reversed auto entry is "missing" again (ERP-007). */
    private function live(string $k): bool
    {
        return Poster::live($k);
    }

    /** Is every journal this document should have already in the ledger? */
    public function posted(string $type, $doc, string $from): bool
    {
        switch ($type) {
            case 'purchases':
                return $this->live('po:'.$doc->id.':receive') || $this->zeroValue($doc->total_cost);
            case 'orders':
                return $this->live(SalesPoster::confirmKey($doc));
            case 'payments':
                $paidOk = ! $this->positive($doc->paid_amount) || $this->live('order:'.$doc->id.':pay:'.Money::round((string) $doc->paid_amount));
                $carrierOk = ! ($doc->carrier_settled_at && $this->positive($doc->cod_amount)) || $this->live('order:'.$doc->id.':carrier-settle');

                return $paidOk && $carrierOk;
            case 'returns':
                $goods = $this->live('return:'.$doc->id.':goods') || ($this->zeroValue($doc->return_value) && $this->zeroValue($doc->returned_cogs));

                return $goods && ($doc->status->value !== 'refunded' || ! $this->positive($doc->refund_amount) || $this->live('return:'.$doc->id.':refund'));
            case 'stock':
                if ($doc instanceof StockAdjustment) {
                    return $this->live('adj:'.$doc->id) || $this->stockValue($doc) === '0.00';
                }

                return ($this->live('transfer:'.$doc->id.':out') || $this->stockValue($doc) === '0.00')
                    && ($doc->status->value !== 'received' || ! $doc->received_at || $doc->received_at->toDateString() < $from || $this->live('transfer:'.$doc->id.':in') || $this->stockValue($doc) === '0.00');
            case 'expenses':
                return JournalEntry::where('source_type', 'expense')->where('source_id', $doc->id)->where('type', 'auto')->where('status', 'posted')->exists() || $this->zeroValue($doc->amount);
        }

        return true;
    }

    private function positive(mixed $v): bool
    {
        return Money::cmp((string) $v, '0') > 0;
    }

    private function zeroValue(mixed $v): bool
    {
        return Money::cmp((string) $v, '0') === 0;
    }

    private function stockValue($doc): string
    {
        return $doc->items()->get()->reduce(fn ($c, $i) => Money::add($c, Money::mul((string) $i->unit_cost, $i->quantity)), '0');
    }

    /** Business amount shown in the summary / dry-run totals. */
    public function amount(string $type, $doc): string
    {
        return Money::round((string) match ($type) {
            'purchases' => $doc->total_cost, 'orders' => $doc->total, 'payments' => $doc->paid_amount, 'returns' => $doc->return_value, 'expenses' => $doc->amount,
            default => $this->stockValue($doc),
        });
    }

    private function sourceOf(string $type, $doc): array
    {
        return match ($type) {
            'purchases' => ['purchase_order', $doc->id], 'orders', 'payments' => ['order', $doc->id], 'returns' => ['order_return', $doc->id],
            'expenses' => ['expense', $doc->id], default => [$doc instanceof StockAdjustment ? 'stock_adjustment' : 'stock_transfer', $doc->id],
        };
    }

    private function d(mixed $v): string
    {
        return substr((string) $v, 0, 10);
    }

    /** Post one document through its poster(s) at its own business date. */
    public function post(string $type, $doc, string $from): void
    {
        switch ($type) {
            case 'purchases':
                Poster::withDate($this->d($doc->received_at), fn () => $this->purchases->received($doc));
                break;
            case 'orders':
                Poster::withDate($this->d(OpeningState::confirmedAt($doc)), function () use ($doc) {
                    $this->sales->confirmed($doc, true);
                    $this->sales->shipped($doc);                  // COGS true-up / goods out for delivery (zero delta when nothing is missing)
                    $this->sales->syncCommission($doc, 'backfill'); // commission deltas left behind by a manually reversed recognition entry
                });
                break;
            case 'payments':
                $base = OpeningState::confirmedAt($doc) ?? $doc->placed_at ?? $doc->created_at;
                Poster::withDate($this->d($doc->delivered_at ?? $base), fn () => $this->payments->sync($doc));
                if ($doc->carrier_settled_at) {
                    Poster::withDate($this->d($doc->carrier_settled_at), fn () => $this->payments->carrierSettled($doc));
                }
                break;
            case 'returns':
                $date = $this->d($doc->approved_at ?? $doc->created_at);
                Poster::withDate($date, fn () => $this->returns->approved($doc));
                if ($doc->status->value === 'refunded') {
                    Poster::withDate($this->d($doc->refunded_at ?? $doc->approved_at ?? $doc->created_at), fn () => $this->returns->refunded($doc));
                }
                break;
            case 'stock':
                if ($doc instanceof StockAdjustment) {
                    Poster::withDate($this->d($doc->created_at), fn () => $this->stock->adjusted($doc));
                    break;
                }
                $sent = max($this->d($doc->sent_at ?? $doc->created_at), $from);
                Poster::withDate($sent, fn () => $this->stock->transferSent($doc));
                if ($doc->status->value === 'received' && $doc->received_at && $this->d($doc->received_at) >= $from) {
                    Poster::withDate($this->d($doc->received_at), fn () => $this->stock->transferReceived($doc));
                }
                break;
            case 'expenses':
                $this->expenses->sync($doc); // dated by expense_date
                break;
        }
    }

    /**
     * Walk the documents of one type. $dry = count only (nothing written). Failures are isolated per document ('backfill.failed' audit row).
     *
     * @return array{created:int,skipped:int,failed:int,amount:string,missing:array<int,int>}
     */
    public function process(string $type, string $from, bool $dry, ?callable $progress = null, ?int $userId = null, int $sample = 20): array
    {
        $r = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'amount' => '0.00', 'missing' => []];
        $progress && $progress('start', $type, 0);
        foreach ($this->documents($type, $from) as $doc) {
            $progress && $progress('tick', $type, 1);
            try {
                if ($this->posted($type, $doc, $from)) {
                    $r['skipped']++;

                    continue;
                }
                if ($dry) {
                    $r['created']++;
                    $r['amount'] = Money::add($r['amount'], $this->amount($type, $doc));
                    count($r['missing']) < $sample && $r['missing'][] = $doc->id;

                    continue;
                }
                [$st, $sid] = $this->sourceOf($type, $doc);
                $before = JournalEntry::where('source_type', $st)->where('source_id', $sid)->count();
                $this->post($type, $doc, $from);
                if (JournalEntry::where('source_type', $st)->where('source_id', $sid)->count() > $before) {
                    $r['created']++;
                    $r['amount'] = Money::add($r['amount'], $this->amount($type, $doc));
                } else {
                    $r['skipped']++;
                }
            } catch (Throwable $e) {
                $r['failed']++;
                try {
                    AuditLog::create(['action' => 'backfill.failed', 'subject_type' => $type, 'subject_id' => $doc->id, 'user_id' => $userId,
                        'meta' => ['type' => $type, 'error' => $e->getMessage(), 'exception' => $e::class]]);
                } catch (Throwable $inner) {
                    report($inner);
                }
            }
        }
        $progress && $progress('end', $type, 0);

        return $r;
    }

    // ------------------------------------------------------------------ opening balances

    /**
     * Opening balance snapshot as of $from. $inventory=false: receivables/carrier/payables (pure business data); true: inventory per branch
     * (stock value now - what the ledger already holds, so inventory reconciles with the stock ledger; the movement-based estimate is audited).
     *
     * @return array<string,string> component => total (signed, debit positive)
     */
    public function opening(string $from, bool $dry, array &$counts, ?callable $progress, bool $inventory): array
    {
        $out = [];
        if ($inventory) {
            $out['inventory'] = $this->openingInventory($from, $dry, $counts);

            return $out;
        }
        $out['receivable'] = $this->openingReceivable($from, $dry, $counts);
        $out['payable'] = $this->openingPayable($from, $dry, $counts);

        return $out;
    }

    private function bump(array &$counts, string $part, bool $created, string $amount): void
    {
        $c = $counts['opening'] ??= ['created' => 0, 'skipped' => 0, 'failed' => 0, 'amount' => '0.00'];
        $created ? $c['created']++ : $c['skipped']++;
        $c['amount'] = Money::add($c['amount'], ltrim($amount, '-'));
        $counts['opening'] = $c;
    }

    private function openingKey(string $from, string $part): string
    {
        return 'opening:'.$from.':'.$part;
    }

    private function postOpening(string $from, string $part, string $desc, array $lines, ?int $branch = null): ?JournalEntry
    {
        return $this->journals->post(['entry_date' => $from, 'type' => 'opening', 'description' => $desc, 'idempotency_key' => $this->openingKey($from, $part),
            'source_type' => 'opening', 'source_id' => 0, 'branch_id' => $branch], $lines);
    }

    /** Receivable per customer/branch of orders confirmed before $from + carrier COD receivable of delivered, unsettled ones + per-order baselines. */
    private function openingReceivable(string $from, bool $dry, array &$counts): string
    {
        $d = $from.' 00:00:00';
        $groups = [];     // "branch|customer" => net due
        $carrier = [];    // branch => cod
        $baselines = [];
        $total = '0';
        $query = Order::where('status', '!=', 'cancelled')->whereRaw('('.self::confExpr().') < ?', [$d]);
        $query->clone()->chunkById(self::CHUNK, function ($orders) use (&$groups, &$carrier, &$baselines, $from) {
            $ids = $orders->pluck('id');
            $rets = OrderReturn::whereIn('order_id', $ids)->whereIn('status', ['approved', 'refunded'])->get()->groupBy('order_id');
            foreach ($orders as $o) {
                $due = Money::add(Money::sub((string) $o->total, (string) $o->paid_amount), (string) $o->refunded_amount);
                foreach ($rets->get($o->id, []) as $r) {
                    $due = Money::sub($due, (string) $r->return_value);
                    if (substr((string) ($r->approved_at ?? $r->created_at), 0, 10) >= $from) {
                        $due = Money::add($due, (string) $r->return_value); // posted later by ReturnPoster at its own date
                    }
                    if ($r->status->value === 'refunded' && substr((string) ($r->refunded_at ?? $r->approved_at ?? $r->created_at), 0, 10) >= $from) {
                        $due = Money::sub($due, (string) $r->refund_amount);
                    }
                }
                $k = ($o->branch_id ?? 0).'|'.($o->customer_id ?? 0);
                $groups[$k] = Money::add($groups[$k] ?? '0', $due);
                if (in_array($o->status->value, ['delivered', 'returned'], true) && $o->delivered_at !== null && $o->carrier_settled_at === null && Money::cmp((string) $o->cod_amount, '0') > 0) {
                    $carrier[$o->branch_id ?? 0] = Money::add($carrier[$o->branch_id ?? 0] ?? '0', (string) $o->cod_amount);
                }
                if (Money::cmp((string) $o->paid_amount, '0') > 0 || Money::cmp((string) $o->refunded_amount, '0') > 0) {
                    $baselines[] = ['order_id' => $o->id, 'paid_amount' => Money::round((string) $o->paid_amount), 'refunded_amount' => Money::round((string) $o->refunded_amount),
                        'created_at' => now(), 'updated_at' => now()];
                }
            }
        });

        $lines = [];
        $equity = [];
        foreach ($groups as $k => $net) {
            if (Money::cmp($net, '0') === 0) {
                continue;
            }
            [$b, $c] = array_map('intval', explode('|', $k));
            $abs = ltrim($net, '-');
            $lines[] = [(Money::cmp($net, '0') > 0 ? 'debit' : 'credit') => $abs, 'account' => 'receivable', 'branch_id' => $b ?: null, 'party_type' => $c ? 'customer' : null, 'party_id' => $c ?: null, 'memo' => __('erp.bf.opening_receivable')];
            $equity[$b] = Money::add($equity[$b] ?? '0', $net);
            $total = Money::add($total, $net);
        }
        $existing = $this->key($this->openingKey($from, 'receivable'));
        $this->bump($counts, 'receivable', ! $existing && ! $dry && $lines !== [], $total);
        if ($dry || $existing) {
            return $total;
        }
        DB::transaction(function () use ($from, $lines, $equity, $baselines, $carrier) {
            if ($lines) {
                foreach ($equity as $b => $net) {
                    $lines[] = [(Money::cmp($net, '0') > 0 ? 'credit' : 'debit') => ltrim($net, '-'), 'account' => 'opening_equity', 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_receivable')];
                }
                $this->postOpening($from, 'receivable', __('erp.bf.opening_receivable_desc', ['date' => $from]), $lines);
            }
            if ($carrier) {
                $cl = [];
                foreach ($carrier as $b => $amt) {
                    $cl[] = ['account' => 'carrier_receivable', 'debit' => $amt, 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_carrier')];
                    $cl[] = ['account' => 'opening_equity', 'credit' => $amt, 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_carrier')];
                }
                $this->postOpening($from, 'carrier', __('erp.bf.opening_carrier_desc', ['date' => $from]), $cl);
            }
            foreach (array_chunk($baselines, 500) as $chunk) {
                DB::table('erp_opening_orders')->insertOrIgnore($chunk);
            }
        });
        AuditLog::create(['action' => 'backfill.opening', 'subject_type' => 'receivable', 'subject_id' => 0, 'meta' => ['from' => $from, 'receivable' => $total, 'carrier' => array_reduce($carrier, fn ($c, $v) => Money::add($c, $v), '0'), 'orders' => count($baselines)]]);

        return $total;
    }

    /** Received purchases before $from are unpaid (no supplier payment records exist in the business tables): Cr Accounts payable per supplier. */
    private function openingPayable(string $from, bool $dry, array &$counts): string
    {
        $rows = PurchaseOrder::where('status', 'received')->where('received_at', '<', $from.' 00:00:00')->groupBy('branch_id', 'supplier_id')
            ->selectRaw('branch_id, supplier_id, SUM(total_cost) as t')->get();
        $lines = [];
        $total = '0';
        $equity = [];
        foreach ($rows as $r) {
            if (Money::cmp((string) $r->t, '0') <= 0) {
                continue;
            }
            $amt = Money::round((string) $r->t);
            $lines[] = ['account' => 'payable', 'credit' => $amt, 'branch_id' => $r->branch_id, 'party_type' => $r->supplier_id ? 'supplier' : null, 'party_id' => $r->supplier_id, 'memo' => __('erp.bf.opening_payable')];
            $equity[(int) $r->branch_id] = Money::add($equity[(int) $r->branch_id] ?? '0', $amt);
            $total = Money::add($total, $amt);
        }
        $existing = $this->key($this->openingKey($from, 'payable'));
        $this->bump($counts, 'payable', ! $existing && ! $dry && $lines !== [], '-'.$total);
        if ($dry || $existing || ! $lines) {
            return '-'.$total;
        }
        foreach ($equity as $b => $amt) {
            $lines[] = ['account' => 'opening_equity', 'debit' => $amt, 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_payable')];
        }
        $this->postOpening($from, 'payable', __('erp.bf.opening_payable_desc', ['date' => $from]), $lines);
        AuditLog::create(['action' => 'backfill.opening', 'subject_type' => 'payable', 'subject_id' => 0, 'meta' => ['from' => $from, 'payable' => $total]]);

        return '-'.$total;
    }

    /** Inventory per branch = stock_levels value now (less COGS already booked for confirmed-but-unshipped orders, whose stock is still on hand) minus the ledger inventory already booked (documents >= $from): the ledger then equals the stock valuation. */
    private function openingInventory(string $from, bool $dry, array &$counts): string
    {
        $fig = app(InventoryReport::class)->figures(null);
        $lines = [];
        $total = '0';
        foreach ($fig['rows'] as $b => $r) {
            $diff = bcsub($r['expected'], $r['ledger'], 4); // positive = ledger is short of the stock value
            if (bccomp(ltrim($diff, '-'), '0.005', 4) <= 0) {
                continue;
            }
            $abs = ltrim($diff, '-');
            $pos = bccomp($diff, '0', 4) > 0;
            $lines[] = ['account' => 'inventory', ($pos ? 'debit' : 'credit') => $abs, 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_inventory')];
            $lines[] = ['account' => 'opening_equity', ($pos ? 'credit' : 'debit') => $abs, 'branch_id' => $b ?: null, 'memo' => __('erp.bf.opening_inventory')];
            $total = bcadd($total, $diff, 4);
        }
        $existing = $this->key($this->openingKey($from, 'inventory'));
        $this->bump($counts, 'inventory', ! $existing && ! $dry && $lines !== [], $total);
        if ($dry || $existing || ! $lines) {
            return $total;
        }
        $moves = StockMovement::where('created_at', '>=', $from.' 00:00:00')->selectRaw('SUM(quantity * unit_cost) as v')->value('v') ?? '0';
        $this->postOpening($from, 'inventory', __('erp.bf.opening_inventory_desc', ['date' => $from]), $lines);
        AuditLog::create(['action' => 'backfill.opening', 'subject_type' => 'inventory', 'subject_id' => 0, 'meta' => ['from' => $from, 'stock_value_now' => $fig['stock'], 'ledger_before' => $fig['ledger'],
            'opening_inventory' => $total, 'stock_movements_since_from' => Money::round((string) $moves)]]);

        return $total;
    }

    // ------------------------------------------------------------------ missing documents

    /** Scope of the missing-journal scan: from the opening date, else the first journal's date, else the first order's date. */
    public function scanFrom(): string
    {
        $d = OpeningState::openingDate();
        if ($d) {
            return $d;
        }
        $first = JournalEntry::min('created_at');

        return substr((string) ($first ?? \App\Models\Order::withTrashed()->min('created_at') ?? now()), 0, 10);
    }

    /**
     * Source documents without journals (confirmed orders, received purchases, expenses, approved returns, stock adjustments/transfers, payments).
     * $post=true posts them through the same idempotent posters ("post missing").
     *
     * @return array<string,array{created:int,skipped:int,failed:int,amount:string,missing:array}>
     */
    public function missing(bool $post = false, ?int $userId = null, ?callable $progress = null): array
    {
        $from = $this->scanFrom();
        $out = [];
        foreach (['purchases', 'orders', 'payments', 'returns', 'stock', 'expenses'] as $type) {
            // posting runs in "repost" mode: a document whose auto entry was reversed by hand gets a corrected, versioned entry
            $out[$type] = $post ? Poster::reposting(fn () => $this->process($type, $from, false, $progress, $userId)) : $this->process($type, $from, true, $progress, $userId);
        }
        if ($post) {
            AuditLog::create(['action' => 'backfill.post_missing', 'subject_type' => 'erp', 'subject_id' => 0, 'user_id' => $userId, 'meta' => ['from' => $from, 'counts' => $out]]);
        }

        return $out;
    }
}
