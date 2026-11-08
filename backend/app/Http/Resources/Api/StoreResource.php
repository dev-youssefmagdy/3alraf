<?php

namespace App\Http\Resources\Api;

use App\Models\Setting;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\SeoSettingsService;
use App\Services\Storefront\StorefrontData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The shop (General settings), built from setting(); construct with `new StoreResource(null)`. */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seo = app(SeoSettingsService::class)->get();
        $footer = app(StorefrontData::class)->footer();
        $settings = Setting::map();
        $abs = fn (?string $u) => $u ? (preg_match('#^https?://#i', $u) ? $u : url($u)) : null;
        $logo = $abs($seo['org_logo'] ?: null) ?? asset('logo.png');

        return [
            'slug' => (string) config('mobile.legacy_store_slug', 'egy'), 'name' => setting('name'), 'country' => setting('country'),
            'currency' => ['code' => setting('currency_code'), 'symbol' => setting('currency_symbol')], 'timezone' => setting('timezone'),
            'default_locale' => setting('default_locale'), 'locales' => collect((array) setting('locales', []))->map(fn ($l) => ['code' => $l, 'rtl' => locale_is_rtl($l)])->values(),
            'cod_collect_shipping_upfront' => (bool) setting('cod_collect_shipping_upfront', config('payments.cod_upfront_default', true)),
            'prices_include_tax' => (bool) ($settings['prices_include_tax'] ?? false),
            'logo_url' => $logo,
            'favicon_url' => asset('logo-192.png'),
            'colors' => array_merge(config('mobile.brand_colors'), array_filter((array) ($settings['branding']['colors'] ?? []), fn ($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v))),
            'contact' => [
                'email' => $seo['org_email'] ?: ($settings['support_email'] ?? null) ?: null,
                'phone' => $seo['org_phone'] ?: null,
                'whatsapp' => $this->whatsappNumber($settings, $seo),
            ],
            'social' => (object) array_filter($seo['social']),
            'app_links' => ['ios' => $seo['app_links']['ios'] ?: null, 'android' => $seo['app_links']['android'] ?: null],
            'footer' => [
                'pages' => $footer['pages'],
                'has_blog' => (bool) ($footer['blog'] ?? false),
                'payment_methods' => $footer['payments'],
            ],
            // Sections the app shows/hides. small_batch_lab / ambassadors were removed with the Growth system (P2) and stay `false` for older app builds.
            'features' => ['gift_finder' => app(GiftFinderSettings::class)->enabled(), 'small_batch_lab' => false, 'ambassadors' => false],
        ];
    }

    /** Digits-only international number for wa.me links: settings.contact.whatsapp, else the organisation phone. */
    private function whatsappNumber(array $settings, array $seo): ?string
    {
        $raw = (string) (($settings['contact']['whatsapp'] ?? '') ?: $seo['org_phone']);
        $digits = preg_replace('/\D+/', '', $raw);

        return $digits !== '' ? $digits : null;
    }
}
