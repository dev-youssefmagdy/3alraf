<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"><head><meta charset="utf-8"><title>{{ __('erp_dist.statement_title') }} - {{ $owner->name }}</title>
<style>body{font-family:system-ui,sans-serif;margin:32px;color:#1e293b}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border-bottom:1px solid #e2e8f0;padding:6px 8px;text-align:start}td.n,th.n{text-align:end}@media print{.np{display:none}}</style></head>
<body>
<p class="np"><a href="#" onclick="window.print();return false">{{ __('erp_dist.print') }}</a></p>
<h2>{{ __('erp_dist.statement_title') }}</h2>
<p><b>{{ $owner->name }}</b> &middot; {{ rtrim(rtrim(number_format((float) $owner->ownership_percent, 4), '0'), '.') }}% &middot; {{ $tenant?->name }} &middot; {{ now()->toDateString() }}</p>
@php $n = fn ($v) => number_format((float) $v, 2); $acc = 0; $paid = 0; @endphp
<table><thead><tr><th>#</th><th>{{ __('erp.f.period') }}</th><th class="n">%</th><th class="n">{{ __('erp_ext.f.amount') }}</th><th>{{ __('erp_ext.f.status') }}</th></tr></thead><tbody>
@foreach ($lines as $l)@php if ($l->distribution->status === 'posted') { $acc += (float) $l->amount; $paid += $l->paid ? (float) $l->amount : 0; } @endphp
<tr><td>{{ $l->distribution_id }}</td><td>{{ $l->distribution->period_start->format('Y-m-d') }} → {{ $l->distribution->period_end->format('Y-m-d') }}</td><td class="n">{{ $n($l->percent) }}</td><td class="n">{{ $n($l->amount) }}</td>
<td>{{ __('erp_dist.status.'.$l->distribution->status) }}{{ $l->paid ? ' / '.__('erp_dist.paid_flag').' '.$l->paid_at?->format('Y-m-d') : '' }}</td></tr>@endforeach
<tr><th colspan="3">{{ __('erp_dist.accrued') }} / {{ __('erp_dist.paid_flag') }} / {{ __('erp_dist.outstanding') }}</th><th class="n" colspan="2">{{ $n($acc) }} / {{ $n($paid) }} / {{ $n($acc - $paid) }}</th></tr></tbody></table>
</body></html>
