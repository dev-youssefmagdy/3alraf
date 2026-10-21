<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HandlesReports;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;

class ReportController extends Controller implements HasMiddleware
{
    use HandlesReports;

    protected static string $guard = 'tenant';
}
