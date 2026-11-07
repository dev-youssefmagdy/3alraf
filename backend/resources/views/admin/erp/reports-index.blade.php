<x-layouts.admin :title="__('erp.cards.reports')[0]">
    <p class="mb-5 text-sm text-slate-500">{{ __('erp_rep.index_hint') }}</p>
    @foreach (['statements', 'parties', 'operations', 'control'] as $g)
        @continue(empty($groups[$g]))
        <h2 class="mb-2 mt-6 font-semibold text-slate-700">{{ __('erp_rep.groups.'.$g) }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($groups[$g] as $r)
                <a href="{{ $r['url'] }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-brand-500"><div class="font-semibold text-slate-800">{{ __('erp_rep.t.'.$r['key']) }}</div><p class="mt-1 text-sm text-slate-500">{{ __('erp_rep.d.'.$r['key']) }}</p></a>
            @endforeach
        </div>
    @endforeach
    @if ($external)
        <h2 class="mb-2 mt-6 font-semibold text-slate-700">{{ __('erp_rep.groups.other') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($external as $r)<a href="{{ $r['url'] }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-brand-500"><div class="font-semibold text-slate-800">{{ __('erp_rep.ext.'.$r['key']) }}</div></a>@endforeach
        </div>
    @endif
</x-layouts.admin>
