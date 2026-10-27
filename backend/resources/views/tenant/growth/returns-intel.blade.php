<x-layouts.admin guard="tenant" :title="__('growth_capital.ret.title')">
    @php $r = fn ($k, $p = []) => __('growth_capital.ret.'.$k, $p); $c = fn ($k) => __('growth_capital.common.'.$k); $codes = __('growth_capital.ret.codes'); $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $r('hint') }}</p>
        <x-card :title="$r('title')">
            @forelse ($agg['by_code'] as $code => $a)<p class="text-sm">{{ $codes[$code] ?? $r('unspecified') }}: {{ $r('returns') }} {{ $a['returns'] }} | {{ $r('cancellations') }} {{ $a['cancellations'] }} | {{ $r('value') }} {{ number_format($a['value'], 2) }}</p>@empty<p class="text-sm text-slate-500">{{ $c('none') }}</p>@endforelse
        </x-card>
        @can('growth.manage')
            <x-card :title="$r('untagged')">
                @foreach ($untagged['returns'] as $x)
                    <form data-ajax-form action="{{ route('tenant.growth.returns-intel.reason') }}" method="POST" class="mb-1 flex flex-wrap items-center gap-2" novalidate>@csrf<input type="hidden" name="kind" value="return"><input type="hidden" name="id" value="{{ $x->id }}">
                        <span class="text-xs">{{ $c('return') }} {{ $x->number }} {{ $x->reason }}</span><select name="code" class="{{ $inp }}">@foreach ($codes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                @endforeach
                @foreach ($untagged['cancellations'] as $x)
                    <form data-ajax-form action="{{ route('tenant.growth.returns-intel.reason') }}" method="POST" class="mb-1 flex flex-wrap items-center gap-2" novalidate>@csrf<input type="hidden" name="kind" value="cancellation"><input type="hidden" name="id" value="{{ $x->id }}">
                        <span class="text-xs">{{ $c('cancellation') }} {{ $x->number }}</span><select name="code" class="{{ $inp }}">@foreach ($codes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                @endforeach
            </x-card>
            <form data-ajax-form action="{{ route('tenant.growth.returns-intel.generate') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $r('generate') }}</x-button></form>
        @endcan
        @foreach ($tasks as $t)
            <x-card :title="$t->title">
                <p class="text-xs text-slate-500">{{ $codes[substr($t->topic, 4)] ?? '' }} | {{ $c('status') }}: {{ $t->status }} | {{ $c('before') }}: {{ $t->before_value }} | {{ $c('after') }}: {{ $t->after_value ?? '-' }}</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.returns-intel.task', $t->id) }}" method="POST" class="mt-1 flex flex-wrap gap-2" novalidate>@csrf @method('PUT')
                        <select name="status" class="{{ $inp }}">@foreach (\App\Services\Growth\ReturnIntelService::STATUSES as $s)<option value="{{ $s }}" @selected($t->status === $s)>{{ $s }}</option>@endforeach</select>
                        <input name="owner" value="{{ $t->owner }}" class="{{ $inp }}" placeholder="{{ $c('owner') }}"><input type="date" name="due_on" value="{{ $t->due_on?->format('Y-m-d') }}" class="{{ $inp }}"><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                @endcan
            </x-card>
        @endforeach
    </div>
</x-layouts.admin>
