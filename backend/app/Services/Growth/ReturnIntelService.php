<?php

namespace App\Services\Growth;

use App\Models\ImprovementTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Return Reason Intelligence (spec 16.3): structured reason codes on returns and cancellations, aggregation, a concrete fix task per
 * recurring (reason, product) pair, and before/after tracking of the problem count. Free-text reasons are never parsed.
 */
class ReturnIntelService
{
    public const CODES = ['color_differs', 'size_unclear', 'damaged_packaging', 'delivery_delay', 'description_inaccurate', 'quality', 'wrong_item', 'changed_mind', 'other'];

    /** Codes that point at something the store can fix (changed_mind / other produce no fix task). */
    public const FIXABLE = ['color_differs', 'size_unclear', 'damaged_packaging', 'delivery_delay', 'description_inaccurate', 'quality', 'wrong_item'];
    public const STATUSES = ['open', 'doing', 'done', 'dismissed'];

    public function __construct(private ActionLogger $log) {}

    public function validCode(?string $c): bool
    {
        return $c === null || $c === '' || in_array($c, self::CODES, true);
    }

    public function tagCancellation(int $orderId, string $code, ?int $adminId): Order
    {
        if (! in_array($code, self::CODES, true)) {
            throw ValidationException::withMessages(['code' => __('growth_capital.ret.bad_code')]);
        }
        $o = Order::findOrFail($orderId);
        if ($o->status->value !== 'cancelled') {
            throw ValidationException::withMessages(['order_id' => __('growth_capital.ret.not_cancelled')]);
        }
        $o->update(['cancel_reason_code' => $code]);
        $this->log->log('returns.cancel_tagged', 'admin', $adminId, null, $o, 'ok', ['code' => $code]);

        return $o;
    }

    public function tagReturn(int $returnId, string $code, ?int $adminId): OrderReturn
    {
        if (! in_array($code, self::CODES, true)) {
            throw ValidationException::withMessages(['code' => __('growth_capital.ret.bad_code')]);
        }
        $r = OrderReturn::findOrFail($returnId);
        $r->update(['reason_code' => $code]);
        $this->log->log('returns.return_tagged', 'admin', $adminId, null, $r, 'ok', ['code' => $code]);

        return $r;
    }

    /** Untagged cancellations / returns that need a structured code. */
    public function untagged(int $days = 60): array
    {
        $from = now()->subDays($days);

        return ['cancellations' => Order::where('status', 'cancelled')->whereNull('cancel_reason_code')->where('cancelled_at', '>=', $from)->orderByDesc('id')->limit(50)->get(['id', 'number', 'cancelled_at']),
            'returns' => OrderReturn::whereNull('reason_code')->where('created_at', '>=', $from)->orderByDesc('id')->limit(50)->get(['id', 'number', 'reason', 'created_at'])];
    }

    /** @return array<string,array{returns:int,cancellations:int,value:float}> keyed by code ('unspecified' for untagged) */
    public function aggregate(int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();
        $out = [];
        foreach (OrderReturn::where('created_at', '>=', $from)->selectRaw("COALESCE(reason_code,'unspecified') c, COUNT(*) n, SUM(return_value) v")->groupBy('c')->get() as $r) {
            $out[$r->c] = ['returns' => (int) $r->n, 'cancellations' => 0, 'value' => round((float) $r->v, 2)];
        }
        foreach (Order::where('status', 'cancelled')->where('cancelled_at', '>=', $from)->selectRaw("COALESCE(cancel_reason_code,'unspecified') c, COUNT(*) n, SUM(total) v")->groupBy('c')->get() as $r) {
            $o = $out[$r->c] ?? ['returns' => 0, 'cancellations' => 0, 'value' => 0.0];
            $out[$r->c] = ['returns' => $o['returns'], 'cancellations' => (int) $r->n, 'value' => round($o['value'] + (float) $r->v, 2)];
        }
        uasort($out, fn ($a, $b) => ($b['returns'] + $b['cancellations']) <=> ($a['returns'] + $a['cancellations']));

        return ['days' => $days, 'orders_placed' => Order::where('placed_at', '>=', $from)->count(), 'by_code' => $out];
    }

    /** Events (returns + cancellations) with this code for the product inside [from, to]. */
    public function count(string $code, int $productId, CarbonInterface $from, CarbonInterface $to): int
    {
        $ret = OrderReturn::where('reason_code', $code)->whereBetween('created_at', [$from, $to])
            ->whereHas('items.orderItem', fn ($q) => $q->where('product_id', $productId))->count();
        $can = Order::where('status', 'cancelled')->where('cancel_reason_code', $code)->whereBetween('cancelled_at', [$from, $to])
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))->count();

        return $ret + $can;
    }

    /** Fix task per recurring (code, product). @return int created */
    public function generateTasks(): int
    {
        $n = 0;
        $days = (int) config('growth.returns.window_days', 30);
        $min = (int) config('growth.returns.recurring_min', 3);
        $from = now()->subDays($days)->startOfDay();
        $pairs = [];
        $ri = OrderReturn::where('created_at', '>=', $from)->whereIn('reason_code', self::FIXABLE)->with('items.orderItem:id,product_id')->get(['id', 'reason_code']);
        foreach ($ri as $r) {
            foreach ($r->items->pluck('orderItem.product_id')->filter()->unique() as $pid) {
                $pairs[$r->reason_code.':'.$pid] = true;
            }
        }
        foreach (Order::where('status', 'cancelled')->where('cancelled_at', '>=', $from)->whereIn('cancel_reason_code', self::FIXABLE)->with('items:id,order_id,product_id')->get(['id', 'cancel_reason_code']) as $o) {
            foreach ($o->items->pluck('product_id')->unique() as $pid) {
                $pairs[$o->cancel_reason_code.':'.$pid] = true;
            }
        }
        foreach (array_keys($pairs) as $k) {
            [$code, $pid] = explode(':', $k);
            $pid = (int) $pid;
            $c = $this->count($code, $pid, $from, now());
            $topic = 'ret_'.$code;
            if ($c < $min || ImprovementTask::where('product_id', $pid)->where('topic', $topic)->whereIn('status', ['open', 'doing'])->exists()) {
                continue;
            }
            $name = Product::whereKey($pid)->first()?->getTranslation('name', app()->getLocale(), true) ?? '#'.$pid;
            ImprovementTask::create(['product_id' => $pid, 'topic' => $topic, 'rule' => 'return_reason', 'title' => mb_substr(__('growth_capital.ret.fix.'.$code, ['product' => $name]), 0, 200), 'due_on' => now()->addDays(14)->toDateString(), 'status' => 'open',
                'evidence' => [['source' => 'returns_cancellations', 'metric' => 'events_'.$days.'d', 'value' => $c, 'period' => $days.'d', 'reason_code' => $code]], 'before_value' => $c]);
            $this->log->log('returns.task_created', 'system', null, null, null, 'ok', ['code' => $code, 'product_id' => $pid]);
            $n++;
        }

        return $n;
    }

    public function tasks()
    {
        return ImprovementTask::where('rule', 'return_reason')->orderByRaw("FIELD(status,'open','doing','done','dismissed')")->latest('id')->limit(100)->get();
    }

    /** Done = after value measured as events since creation scaled to the same window (estimated, needs time to mean anything). */
    public function updateTask(int $id, array $d, ?int $adminId): ImprovementTask
    {
        $t = ImprovementTask::where('rule', 'return_reason')->findOrFail($id);
        if (isset($d['status']) && ! in_array($d['status'], self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('growth_hub.voc.bad_status')]);
        }
        $upd = array_intersect_key($d, array_flip(['status', 'owner', 'due_on']));
        if (($d['status'] ?? null) === 'done' && $t->status !== 'done') {
            $code = substr($t->topic, 4);
            $window = (int) config('growth.returns.window_days', 30);
            $elapsed = max(1, (int) now()->diffInDays($t->created_at, true));
            $upd['after_value'] = round($this->count($code, (int) $t->product_id, $t->created_at, now()) * $window / $elapsed, 1);
        }
        $t->update($upd);
        $this->log->log('returns.task_updated', 'admin', $adminId, null, $t, 'ok', ['status' => $t->status]);

        return $t;
    }

    public function report(): array
    {
        return ['type' => 'return_reasons', 'aggregate' => $this->aggregate(), 'tasks' => $this->tasks()->map(fn ($t) => ['id' => $t->id, 'product_id' => $t->product_id, 'code' => substr($t->topic, 4), 'title' => $t->title, 'status' => $t->status, 'before' => $t->before_value, 'after' => $t->after_value])->all()];
    }
}
