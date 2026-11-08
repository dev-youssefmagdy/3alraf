<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\BundleCatalog;

class BundleController extends Controller
{
    public function __construct(private BundleCatalog $bundles) {}

    public function index()
    {
        return view('storefront.bundles', ['bundles' => $this->bundles->listing()]);
    }

    public function show(string $locale, string $slug)
    {
        $bundle = $this->bundles->find($slug) ?? abort(404);

        return view('storefront.bundle', ['bundle' => $bundle]);
    }
}
