<x-layouts.admin :title="__('erp_dist.report')">
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-card>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-button></form>
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th><th class="text-end">{{ __('erp_dist.accrued') }}</th><th class="text-end">{{ __('erp_dist.paid_flag') }}</th><th class="text-end">{{ __('erp_dist.outstanding') }}</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $r['owner']->name }}</td><td class="text-end tabular-nums">{{ $n($r['accrued']) }}</td><td class="text-end tabular-nums">{{ $n($r['paid']) }}</td><td class="text-end tabular-nums">{{ $n($r['outstanding']) }}</td><td class="text-end"><a class="text-brand-600" href="{{ route('admin.erp.profits.statement', $r['owner']->id) }}">{{ __('erp_dist.statement') }}</a></td></tr>
        @empty<tr><td colspan="5" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse</tbody></table>
    </x-card>
</x-layouts.admin>
