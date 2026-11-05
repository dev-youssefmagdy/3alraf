<?php

namespace App\Services\Erp\Accounting;

use App\Models\Erp\Period;
use Illuminate\Validation\ValidationException;

/** User-entered documents are refused up-front when their date falls in a closed ERP period (ERP-016): no document without its journal. */
class PeriodGuard
{
    public static function assertOpen(string $date, string $field = 'date'): void
    {
        $p = Period::where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->where('status', '!=', 'open')->first();
        if ($p) {
            throw ValidationException::withMessages([$field => __('erp.errors.period_closed', ['period' => $p->name])]);
        }
    }
}
