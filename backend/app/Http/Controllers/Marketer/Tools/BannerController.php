<?php

namespace App\Http\Controllers\Marketer\Tools;

use App\Http\Controllers\Admin\BannerController as Base;

/** Marketer panel copy of the admin module (owner decision Q1: marketing tools in both panels). Same request, DTO, service and views; own routes and guard. */
class BannerController extends Base
{
    protected static string $routeBase = 'marketer.banners';
}
