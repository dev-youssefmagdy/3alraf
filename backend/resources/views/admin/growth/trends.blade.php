<x-layouts.admin guard="tenant" :title="__('growth_capital.trend.title')">
    @php $t = fn ($k, $p = []) => __('growth_capital.trend.'.$k, $p); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $t('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.trends.scan') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ __('growth_capital.common.scan') }}</x-button></form>@endcan
        @forelse ($rows as $c)
            <x-card :title="$c->name">
                <p class="text-xs text-slate-500">{{ $t('score') }}: <b>{{ $c->score }}</b> | {{ $t('confidence') }}: {{ $c->confidence }} | {{ $t('source') }}: {{ $t('sources.'.$c->source) }} | {{ $c->status }}</p>
                <p class="text-sm">@foreach ($c->components as $k => $v){{ $t('components.'.$k) }}: {{ $v ?? '?' }}@unless ($loop->last) | @endunless @endforeach</p>
                @if ($c->uncertainty)<p class="text-xs text-amber-600">{{ $t('uncertain') }}: {{ collect($c->uncertainty)->map(fn ($u) => $t('components.'.$u))->implode(', ') }}</p>@endif
                <p class="text-xs text-slate-500">{{ __('growth_capital.common.evidence') }}: @foreach ($c->evidence as $e){{ $e['metric'] }} = {{ is_array($e['value'] ?? null) ? '' : ($e['value'] ?? '?') }}@unless ($loop->last), @endunless @endforeach</p>
                @can('growth.manage')@if ($c->status === 'new')
                    <div class="mt-2 flex gap-2">
                        <form data-ajax-form action="{{ route('tenant.growth.trends.test', $c->id) }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $t('test') }}</x-button></form>
                        <form data-ajax-form action="{{ route('tenant.growth.trends.dismiss', $c->id) }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $t('dismiss') }}</x-button></form>
                    </div>@endif @endcan
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ __('growth_capital.common.none') }}</p>@endforelse
    </div>
</x-layouts.admin>
