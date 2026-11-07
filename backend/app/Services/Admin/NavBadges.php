<?php

namespace App\Services\Admin;

use App\Enums\AffiliateStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\ReturnStatus;
use App\Enums\ReviewStatus;
use App\Enums\StockRequestStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Review;
use App\Models\StockRequest;
use Illuminate\Support\Facades\Cache;

/** Counts of items waiting for the team, keyed by sidebar route name. Shared counts are cached briefly. */
class NavBadges
{
    /** @return array<string,int> */
    public function for(object $user): array
    {
        $shared = Cache::remember('nav_badges', 30, fn () => [
            // pending orders plus orders whose payment proof still needs a decision
            'admin.stock.low' => app(LowStockService::class)->rows()->count(),
            'admin.orders.index' => Order::query()->where(fn ($q) => $q->where('status', OrderStatus::PENDING->value)->orWhere(fn ($p) => $p->where('proof_status', 'pending')->where('status', '!=', OrderStatus::CANCELLED->value)))->count(),
            'admin.returns.index' => OrderReturn::query()->where('status', ReturnStatus::REQUESTED->value)->count(),
            'admin.stock-requests.index' => StockRequest::query()->where('status', StockRequestStatus::NEW->value)->count(),
            'admin.reviews.index' => Review::query()->where('status', ReviewStatus::PENDING->value)->count(),
            'admin.affiliates.index' => Affiliate::query()->where('status', AffiliateStatus::PENDING->value)->count(),
            'admin.affiliate-payouts.index' => AffiliatePayout::query()->where('status', PayoutStatus::REQUESTED->value)->count(),
        ]);

        // customers have no "waiting" state: badge = registered since this staff member last opened the customers page
        $key = "nav_seen:{$user->getAuthIdentifier()}:customers";
        if (request()->routeIs('admin.customers.*')) {
            Cache::forever($key, now()->timestamp);
        }
        $since = Cache::get($key);
        $shared['admin.customers.index'] = $since ? Customer::query()->where('created_at', '>', \Carbon\Carbon::createFromTimestamp($since))->count() : 0;

        return array_filter($shared);
    }
}
