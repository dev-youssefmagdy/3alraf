<x-layouts.admin :title="__('erp.title')">
    <x-slot:header><x-ui.page-header :title="__('erp.title')" /></x-slot:header>
    <p class="mb-5 text-sm text-panel-text-subtle">{{ __('erp.hub_hint') }}</p>
    @if ($cards->isEmpty())
        <x-ui.empty-state data-empty="hub" :title="__('erp_audit.hub_empty_title')" :message="__('erp_acc.hub_empty')" />
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($cards as $c)
            @php $t = __('erp.cards.'.$c['key']); @endphp
            @if ($c['url'])
                <a href="{{ $c['url'] }}" class="group rounded-2xl border border-panel-border bg-panel-surface p-5 shadow-panel-xs transition hover:border-panel-accent hover:shadow-panel-md">
            @else
                <div class="rounded-xl border border-dashed border-panel-border bg-panel-surface-sunken p-5 opacity-70">
            @endif
                <div class="mb-3 flex size-10 items-center justify-center rounded-lg bg-panel-accent-soft text-panel-accent-text">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/></svg>
                </div>
                <h3 class="font-semibold text-panel-text">{{ $t[0] }}</h3>
                <p class="mt-1 text-sm text-panel-text-subtle">{{ $t[1] }}</p>
                @unless ($c['url'])<span class="mt-2 inline-block rounded-full bg-panel-border px-2 py-0.5 text-[11px] text-panel-text-muted">{{ __('erp.soon') }}</span>@endunless
            @if ($c['url']) </a> @else </div> @endif
        @endforeach
    </div>
    @if ($alerts !== null)
    <x-ui.card :title="__('erp_audit.alerts.title')" class="mt-6" data-alerts>
        @if ($alerts->isEmpty())<p class="text-sm text-panel-success-fg">{{ __('erp_audit.alerts.none') }}</p>@else @include('admin.erp._alerts', ['alerts' => $alerts]) @endif
    </x-ui.card>
    @endif
</x-layouts.admin>
