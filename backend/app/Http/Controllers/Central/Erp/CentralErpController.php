<?php

namespace App\Http\Controllers\Central\Erp;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Central ERP monitoring is read-only and permission gated per card (erp_central.view hub, .reports financial pages, .audit anomalies + audit log). */
abstract class CentralErpController extends Controller
{
    protected function allow(string $perm): void
    {
        abort_unless(auth('central')->user()?->can($perm), 403);
    }

    protected function date(Request $r, string $key): ?string
    {
        try {
            return $r->filled($key) ? Carbon::parse((string) $r->input($key))->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function intOrNull(Request $r, string $key): ?int
    {
        return $r->filled($key) && ctype_digit((string) $r->input($key)) ? (int) $r->input($key) : null;
    }
}
