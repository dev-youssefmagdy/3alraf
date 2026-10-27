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

    public function show(\Illuminate\Http\Request $r, string $locale, string $slug)
    {
        $bundle = $this->bundles->find($slug) ?? abort(404);
        // Growth bundle A/B (feature `bundle_ab`): the visitor's variant overrides name / description / image for display only
        if ($v = app(\App\Services\Growth\BundleAbService::class)->expose($bundle['id'], \App\Services\Growth\BundleAbService::visitor($r))) {
            $bundle['name'] = $v->name[app()->getLocale()] ?? $bundle['name'];
            $bundle['description'] = $v->description[app()->getLocale()] ?? $bundle['description'];
            $bundle['image'] = $v->image ? media_url($v->image) : $bundle['image'];
        }

        return view('storefront.bundle', ['bundle' => $bundle]);
    }
}
