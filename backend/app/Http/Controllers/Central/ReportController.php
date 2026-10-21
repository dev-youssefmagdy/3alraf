<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Concerns\HandlesReports;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Central manager reports: all tenants (currency-normalized) or the tenant picked in the header switcher. */
class ReportController extends Controller implements HasMiddleware
{
    use HandlesReports;

    protected static string $guard = 'central';
}
