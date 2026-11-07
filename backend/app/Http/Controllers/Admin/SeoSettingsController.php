<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SeoSettingsRequest;
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
        $tenant = current_tenant();
        $tenantLocales = Language::activeList()->filter(fn ($l) => in_array($l->code, $tenant->locales, true))->values();

        return view('tenant.settings.seo', ['seo' => $this->service->get($tenant), 'languages' => $tenantLocales, 'tenant' => $tenant,
            'sitemapUrl' => route('storefront.sitemap'), 'robotsUrl' => url('/robots.txt')]);
    }

    public function update(SeoSettingsRequest $request): JsonResponse
    {
        $this->service->update(current_tenant(), $request->validated());

        return $this->ok(__('admin.saved'), route('tenant.seo.edit'));
    }

    public function validateForm(SeoSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }
}
