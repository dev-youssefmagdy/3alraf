<?php

namespace App\Http\Controllers\Marketer;

use App\DataTables\MarketingApiRequestDataTable;
use App\Http\Controllers\Controller;

/** Marketing internal API request log (perm marketing.audit.view): token, method, route, status, duration. No body / IP / PII is ever stored. */
class ApiLogController extends Controller
{
    public function index(MarketingApiRequestDataTable $table)
    {
        return view('marketer.api-log.index', ['table' => $table, 'days' => (int) config('marketing.request_log_days', 30)]);
    }

    public function datatable(MarketingApiRequestDataTable $table)
    {
        return $table->ajax();
    }
}
