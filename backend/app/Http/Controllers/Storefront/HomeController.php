<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontData;

class HomeController extends Controller
{
    /** `/` -> default locale */
    public function root()
    {
        return redirect()->route('storefront.home', ['locale' => current_tenant()->default_locale]);
    }

    public function index(string $locale, StorefrontData $data)
    {
        return view('storefront.home', ['tenant' => current_tenant()] + $data->home());
    }
}
