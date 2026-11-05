<x-layouts.admin guard="central" :title="$e->number.' - '.$e->tenant_name">
    <a href="{{ route('central.erp.transactions', ['tenant_id' => $e->tenant_id]) }}" class="mb-4 inline-block text-sm text-brand-600">&larr; {{ __('erp_central.cards.transactions')[0] }}</a>
    @php $n = fn ($v) => (float) $v ? number_format((float) $v, 2) : ''; @endphp
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><strong>{{ $e->tenant_name }}</strong> - {{ __('erp_central.tenant_only') }} <strong>{{ $e->tenant_currency }}</strong> ({{ __('erp_central.read_only') }})
        - <a class="text-brand-600" href="{{ route('central.erp.tenant', $e->tenant_id) }}">{{ __('erp_central.drill') }}</a></div>
    <x-card :title="$e->number.' - '.$e->status">
        <dl class="mb-4 grid gap-2 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">{{ __('erp_central.col.date') }}</dt><dd>{{ \Illuminate\Support\Str::of($e->entry_date)->substr(0, 10) }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp_central.col.type') }}</dt><dd>{{ $e->type }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp_central.col.source') }}</dt><dd>{{ $e->source_type ? $e->source_type.' #'.$e->source_id : '-' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp_central.f.branch') }}</dt><dd>{{ $branch ?? '-' }}</dd></div>
            <div class="md:col-span-4"><dt class="text-slate-500">{{ __('erp_central.col.description') }}</dt><dd>{{ $e->description }}</dd></div>
        </dl>
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.f.account') }}</th><th class="text-start">{{ __('erp_central.col.memo') }}</th><th class="text-end">{{ __('erp_central.col.debit') }}</th><th class="text-end">{{ __('erp_central.col.credit') }}</th></tr></thead>
        <tbody>@foreach ($lines as $l)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $l->account_code }} - {{ $l->account_label }}</td><td>{{ $l->memo }}</td><td class="text-end tabular-nums">{{ $n($l->debit) }}</td><td class="text-end tabular-nums">{{ $n($l->credit) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="font-semibold"><td colspan="2" class="py-2">{{ __('erp_central.col.total') }} ({{ $e->tenant_currency }})</td><td class="text-end tabular-nums">{{ number_format((float) $lines->sum('debit'), 2) }}</td><td class="text-end tabular-nums">{{ number_format((float) $lines->sum('credit'), 2) }}</td></tr></tfoot></table>
    </x-card>
</x-layouts.admin>
