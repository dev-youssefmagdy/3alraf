<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeoSettingsRequest;
use App\Models\Language;
use App\Services\SeoSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SeoSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private SeoSettingsService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:settings.view', only: ['edit']), new Middleware('can:settings.edit', only: ['update', 'validateForm'])];
    }

    public function edit()
    {
        $locales = (array) setting('locales', []);
        $languages = Language::activeList()->filter(fn ($l) => in_array($l->code, $locales, true))->values();

        return view('admin.settings.seo', ['seo' => $this->service->get(), 'languages' => $languages, 'locales' => $locales, 'shopName' => (string) setting('name'),
            'sitemapUrl' => route('storefront.sitemap'), 'robotsUrl' => url('/robots.txt')]);
    }

    public function update(SeoSettingsRequest $request): JsonResponse
    {
        $this->service->update($request->validated());

        return $this->ok(__('admin.saved'), route('admin.seo.edit'));
    }

    public function validateForm(SeoSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }
}
