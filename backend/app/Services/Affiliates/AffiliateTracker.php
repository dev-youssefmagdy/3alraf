<?php

namespace App\Services\Affiliates;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** Click tracking (?utm_source=affiliate&aff={uuid}) with a 30 day last-click cookie, and order attribution. */
class AffiliateTracker
{
    public const COOKIE = 'aff';
    public const DAYS = 30;

    public function track(Request $r): ?Affiliate
    {
        $uuid = (string) $r->query('aff', '');
        if ($uuid === '' || $r->query('utm_source') !== 'affiliate') {
            return null;
        }
        $aff = Affiliate::where('uuid', $uuid)->where('status', AffiliateStatus::APPROVED->value)->first();
        if (! $aff) {
            return null;
        }
        $slug = $r->route('slug');
        AffiliateClick::create([
            'affiliate_id' => $aff->id, 'product_id' => $slug ? Product::where('slug', $slug)->value('id') : null,
            'ip' => $r->ip(), 'user_agent' => mb_substr((string) $r->userAgent(), 0, 250),
        ]);
        Cookie::queue(self::COOKIE, $aff->uuid, self::DAYS * 24 * 60);

        return $aff;
    }

    /** Affiliate id from the cookie; self-referral (same email) is ignored. */
    public function attributedId(Request $r, ?string ...$emails): ?int
    {
        $uuid = $r->cookie(self::COOKIE);
        if (! $uuid || ! is_string($uuid)) {
            return null;
        }
        $aff = Affiliate::where('uuid', $uuid)->where('status', AffiliateStatus::APPROVED->value)->first();
        if (! $aff) {
            return null;
        }
        foreach ($emails as $e) {
            if ($e && strcasecmp($e, $aff->email) === 0) {
                return null;
            }
        }

        return $aff->id;
    }

    /** Unique product link for the affiliate dashboard. */
    public function productUrl(Affiliate $a, Product $p, ?string $locale = null): string
    {
        return tenant_url(current_tenant(), '/'.($locale ?? app()->getLocale()).'/product/'.$p->slug.'?utm_source=affiliate&aff='.$a->uuid);
    }
}
