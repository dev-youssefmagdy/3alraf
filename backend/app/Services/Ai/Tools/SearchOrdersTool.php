<?php

namespace App\Services\Ai\Tools;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Ai\AiContext;

class SearchOrdersTool extends AbstractTool
{
    public function name(): string { return 'search_orders'; }

    public function description(): string { return 'Find orders by number, status, date range or minimum total. Customer names are masked; no phone/e-mail/address. Max 25 rows, newest first.'; }

    public function schema(): array
    {
        return $this->obj($this->periodSchema() + [
            'number' => ['type' => 'string', 'description' => 'Order number (partial ok)'], 'status' => ['type' => 'string', 'enum' => array_map(fn ($s) => $s->value, OrderStatus::cases())],
            'min_total' => ['type' => 'number'], 'limit' => ['type' => 'integer'],
        ]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $q = Order::query()->orderByDesc('id');
        if (! empty($a['number'])) {
            $q->where('number', 'like', '%'.addcslashes((string) $a['number'], '%_\\').'%');
        } elseif (isset($a['period']) || isset($a['from']) || isset($a['to'])) {
            [$from, $to] = $this->range($a, $ctx);
            $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }
        if (! empty($a['status']) && OrderStatus::tryFrom((string) $a['status'])) {
            $q->where('status', $a['status']);
        }
        if (isset($a['min_total'])) {
            $q->where('total', '>=', (float) $a['min_total']);
        }
        $orders = $q->withCount('items')->limit($this->limit($a))->get();

        return ['count' => $orders->count(), 'currency' => $ctx->currency, 'orders' => $orders->map(fn ($o) => [
            'number' => $o->number, 'status' => $o->status?->value, 'payment_status' => $o->payment_status?->value, 'total' => $o->total, 'items' => $o->items_count,
            'customer' => $this->maskName($o->customer_name), 'placed_at' => ($o->placed_at ?? $o->created_at)?->toDateTimeString(),
        ])->all()];
    }
}
