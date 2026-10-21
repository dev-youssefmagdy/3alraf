<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** Public central site: the visitor CHOOSES a country (no geo/IP detection); the choice is remembered in a cookie. */
class LandingController extends Controller
{
    public function __construct(private TenantDirectory $directory) {}

    public function index(Request $request)
    {
        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : 'ar';
        app()->setLocale($lang);

        $tenants = $this->directory->active();
        $preferred = (string) $request->cookie('preferred_tenant');

        return view('central.landing', [
            'tenants' => $tenants,
            'preferred' => collect($tenants)->firstWhere('slug', $preferred)['slug'] ?? null,
            'lang' => $lang,
        ]);
    }

    public function choose(string $slug)
    {
        $tenant = $this->directory->findActive($slug);
        abort_unless($tenant, 404);

        return redirect()->away($tenant->url('/'.$tenant->default_locale))
            ->withCookie(Cookie::make('preferred_tenant', $tenant->slug, 60 * 24 * 365, '/', null, null, true, false, 'lax'));
    }
}
