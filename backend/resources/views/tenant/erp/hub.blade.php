<x-layouts.admin guard="tenant" :title="__('erp.title')">
    <p class="mb-5 text-sm text-slate-500">{{ __('erp.hub_hint') }}</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($cards as $c)
            @php $t = __('erp.cards.'.$c['key']); @endphp
            @if ($c['url'])
                <a href="{{ $c['url'] }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-500 hover:shadow-md">
            @else
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 opacity-70">
            @endif
                <div class="mb-3 flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/></svg>
                </div>
                <h3 class="font-semibold text-slate-800">{{ $t[0] }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $t[1] }}</p>
                @unless ($c['url'])<span class="mt-2 inline-block rounded-full bg-slate-200 px-2 py-0.5 text-[11px] text-slate-600">{{ __('erp.soon') }}</span>@endunless
            @if ($c['url']) </a> @else </div> @endif
        @endforeach
    </div>
    <x-card :title="__('erp_central.alerts.title')" class="mt-6" data-alerts>
        @if ($alerts->isEmpty())<p class="text-sm text-emerald-700">{{ __('erp_central.alerts.none') }}</p>@else @include('central.erp._alerts', ['alerts' => $alerts]) @endif
    </x-card>
</x-layouts.admin>
