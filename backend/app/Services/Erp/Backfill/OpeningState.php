<?php

namespace App\Services\Erp\Backfill;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Erp\Equity\ErpSettings;
use Illuminate\Support\Facades\DB;

/**
 * What the opening-balance snapshot (erp:backfill --from=D) has already absorbed. Orders confirmed BEFORE D are carried by the opening
 * receivable / carrier entries, so the live posters must not recognise them again (no second revenue, no second payment): they only post
 * the movement AFTER the snapshot, measured against the per-order baseline stored in erp_opening_orders.
 */
class OpeningState
{
    public static function openingDate(): ?string
    {
        $d = app(ErpSettings::class)->get('opening_date');

        return $d ? (string) $d : null;
    }

    /** First time the order reached a sales status (confirmed..delivered); orders without history fall back to placed/created date. Null = never confirmed. */
    public static function confirmedAt(Order $o): ?string
    {
        $h = OrderStatusHistory::where('order_id', $o->id)->whereIn('to_status', self::salesValues())->min('created_at');
        if ($h) {
            return (string) $h;
        }
        if (in_array($o->status, [...OrderStatus::salesStatuses(), OrderStatus::RETURNED], true)) {
            return (string) ($o->placed_at ?? $o->created_at);
        }

        return null;
    }

    /** True when the order was confirmed before the opening date of this tenant. */
    public static function absorbed(Order $o): bool
    {
        $d = self::openingDate();
        if (! $d) {
            return false;
        }
        $c = self::confirmedAt($o);

        return $c !== null && substr($c, 0, 10) < $d;
    }

    /** @return array{paid:string,refunded:string} paid/refunded already inside the opening balances (0 when none) */
    public static function baseline(Order $o): array
    {
        $r = DB::table('erp_opening_orders')->where('tenant_id', $o->tenant_id)->where('order_id', $o->id)->first();

        return ['paid' => (string) ($r->paid_amount ?? '0'), 'refunded' => (string) ($r->refunded_amount ?? '0')];
    }

    /** @return array<int,string> */
    public static function salesValues(): array
    {
        return array_map(fn ($s) => $s->value, OrderStatus::salesStatuses());
    }
}
