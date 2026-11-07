<x-layouts.admin guard="tenant" :title="__('growth_hub.rep.title')">
    @php $r = fn ($k) => __('growth_hub.rep.'.$k); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $r('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.reputation.scan') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $r('scan') }}</x-button></form>@endcan
        <x-card :title="$r('open')">
            @forelse ($open as $a)
                <div class="border-t py-2 text-sm"><b>#{{ $a->product_id }}</b> {{ $a->topic ? __('growth_hub.inbox.categories.'.$a->topic) : '' }} - {{ $r('rate') }}: {{ $a->rate }}% ({{ $r('baseline') }} {{ $a->baseline_rate }}%, z {{ $a->z_score }}, {{ $r('sample') }} {{ $a->sample }})
                    @if ($a->pause_auto_reply)<span class="badge badge-warning">{{ $r('paused') }}</span>@endif
                    @foreach ((array) $a->examples as $e)<p class="text-xs text-slate-500">{{ $e['count'] }}x {{ $e['text'] }}</p>@endforeach
                    @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.reputation.resolve', $a->id) }}" method="POST" class="mt-1" novalidate>@csrf<x-button type="submit">{{ $r('resolve') }}</x-button></form>@endcan
                </div>
            @empty<p class="text-sm text-slate-500">{{ $r('none') }}</p>@endforelse
        </x-card>
        <x-card :title="$r('resolved')">@forelse ($closed as $a)<p class="text-sm">#{{ $a->product_id }} {{ $a->detected_on->format('Y-m-d') }} z {{ $a->z_score }}</p>@empty<p class="text-sm text-slate-500">{{ $r('none') }}</p>@endforelse</x-card>
    </div>
</x-layouts.admin>
