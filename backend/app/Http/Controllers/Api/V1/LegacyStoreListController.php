<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;

/**
 * DEPRECATED store list kept at its old URI for old app builds (their country picker). Returns the single shop as a one-item list;
 * the `slug` (config `mobile.legacy_store_slug`, = the slug old builds saved) is echoed back by them in a header that the API ignores.
 * Shape = the old `Tenant.fromJson` of the Flutter app (slug, name, country, flag, currency, default_locale, locales, languages, logo_url, base_url).
 * New builds read the shop from `GET /api/v1/app-config` (`store`) or `GET /api/v1/store`.
 */
class LegacyStoreListController extends Controller
{
    /** DEPRECATED (old app builds): the single shop as a one-item list in the old store-picker shape. Response header `Deprecation: true`. */
    public function index(): JsonResponse
    {
        $locales = array_values((array) setting('locales', [config('app.locale')]));
        $langs = Language::query()->active()->get()->keyBy('code');
        $logo = trim((string) setting('seo.org_logo', ''));

        $data = [[
            'slug' => (string) config('mobile.legacy_store_slug', 'egy'),
            'name' => (string) setting('name', config('app.name')),
            'country' => setting('country'),
            'flag' => country_flag(setting('country')),
            'currency' => (string) setting('currency_code'),
            'default_locale' => (string) setting('default_locale', config('app.locale')),
            'locales' => $locales,
            'languages' => collect($locales)->map(fn ($c) => $langs[$c]->native_name ?? $c)->values()->all(),
            'logo_url' => $logo !== '' ? (preg_match('#^https?://#i', $logo) ? $logo : url($logo)) : asset('logo.png'),
            'base_url' => url('/'),
        ]];

        return response()->json(['success' => true, 'message' => null, 'data' => $data, 'meta' => (object) []])
            ->header('Deprecation', 'true');
    }
}
