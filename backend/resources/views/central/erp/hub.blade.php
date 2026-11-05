<x-layouts.admin guard="central" :title="__('erp_central.title')">
    <p class="mb-5 text-sm text-slate-500">{{ __('erp_central.hub_hint') }}</p>
    @if ($cards->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center" data-empty="hub">
            <svg class="mx-auto mb-3 size-10 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <h3 class="font-semibold text-slate-800">{{ __('erp_central.hub_empty_title') }}</h3>
            <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500">{{ __('erp_central.hub_empty') }}</p>
        </div>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($cards as $c)
            @php $t = __('erp_central.cards.'.$c['key']); @endphp
            <a href="{{ $c['url'] }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-500 hover:shadow-md" data-card="{{ $c['key'] }}">
                <div class="mb-3 flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/></svg>
                </div>
                <h3 class="font-semibold text-slate-800">{{ $t[0] }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $t[1] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
