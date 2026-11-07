<x-layouts.admin guard="tenant" :title="__('growth_capital.ceo.title')">
    @php $k = fn ($x) => __('growth_capital.ceo.'.$x); $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $k('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.ceo.generate') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $k('generate') }}</x-button></form>@endcan
        @forelse ($rows as $c)
            <x-card :title="$c->question">
                <p class="text-xs text-slate-500">{{ __('growth_capital.common.week') }} {{ $c->week }} | {{ $k('statuses')[$c->status] }} | {{ $k('review') }}: {{ $c->review_on->format('Y-m-d') }} | {{ $k('duration') }}: {{ $c->duration_days }}</p>
                <p class="text-sm"><b>{{ $k('hypothesis') }}:</b> {{ $c->hypothesis }}</p>
                <p class="text-sm"><b>{{ $k('experiment') }}:</b> {{ $c->experiment }}</p>
                <p class="text-sm"><b>{{ $k('metric') }}:</b> {{ $c->metric }}</p>
                <p class="text-xs text-slate-500">{{ __('growth_capital.common.evidence') }}: @foreach ($c->evidence as $e){{ $e['metric'] }} = {{ $e['value'] ?? '?' }}@unless ($loop->last), @endunless @endforeach</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.ceo.review', $c->id) }}" method="POST" class="mt-2 flex flex-wrap gap-2" novalidate>@csrf @method('PUT')
                        <select name="status" class="{{ $inp }}">@foreach ($k('statuses') as $s => $l)<option value="{{ $s }}" @selected($c->status === $s)>{{ $l }}</option>@endforeach</select>
                        <input name="result_note" value="{{ $c->result_note }}" class="{{ $inp }}" placeholder="{{ $k('result') }}"><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                    @unless ($c->experiment_id)<form data-ajax-form action="{{ route('tenant.growth.ceo.experiment', $c->id) }}" method="POST" class="mt-1" novalidate>@csrf<x-button type="submit">{{ $k('to_exp') }}</x-button></form>@endunless
                @endcan
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ __('growth_capital.common.none') }}</p>@endforelse
    </div>
</x-layouts.admin>
