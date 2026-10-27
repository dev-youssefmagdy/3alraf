<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\GiftFinderService;

class GiftFinderAdminController extends Controller
{
    public function index(GiftFinderService $svc)
    {
        return view('tenant.growth.gift-finder', ['stats' => $svc->stats(30), 'url' => route('storefront.gift-finder.show', ['locale' => current_tenant()->default_locale])]);
    }
}
