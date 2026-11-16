<?php

namespace App\Http\Controllers\Marketer\Tools;

use App\Http\Controllers\Admin\SeoSettingsController as Base;
use Illuminate\Routing\Controllers\Middleware;

/** Marketer panel SEO settings (owner decision Q1). Admin gates SEO with settings.*; the marketer guard has its own seo.view / seo.edit. */
class SeoSettingsController extends Base
{
    public static function middleware(): array
    {
        return [new Middleware('can:seo.view', only: ['edit']), new Middleware('can:seo.edit', only: ['update', 'validateForm'])];
    }
}
