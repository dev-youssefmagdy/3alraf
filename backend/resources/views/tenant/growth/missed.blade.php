<x-layouts.admin guard="tenant" :title="__('growth_hub.missed.title')">
    @php $m = fn ($k) => __('growth_hub.missed.'.$k); $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $m('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.missed.scan') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $m('scan') }}</x-button></form>@endcan
        @forelse ($rows as $o)
            <x-card :title="$o->title">
                <p class="text-xs text-slate-500">{{ $m('confidence') }}: {{ $o->confidence }} | {{ $m('statuses')[$o->status] }}</p>
                <p class="mt-1 text-sm"><b>{{ $m('evidence') }}:</b> @foreach ($o->evidence as $e){{ $e['metric'] }} = {{ $e['value'] }} ({{ $e['period'] }})@unless ($loop->last), @endunless @endforeach</p>
                <p class="text-sm"><b>{{ $m('cause') }}:</b> {{ $o->probable_cause }}</p>
                <p class="text-sm"><b>{{ $m('experiment') }}:</b> {{ $o->suggestion }}</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.missed.update', $o->id) }}" method="POST" class="mt-2 flex flex-wrap items-center gap-2" novalidate>@csrf @method('PUT')
                        <select name="status" class="{{ $inp }}">@foreach ($m('statuses') as $k => $l)<option value="{{ $k }}" @selected($o->status === $k)>{{ $l }}</option>@endforeach</select>
                        <input name="owner" value="{{ $o->owner }}" class="{{ $inp }}" placeholder="{{ $m('owner') }}"><input type="date" name="due_on" value="{{ $o->due_on?->format('Y-m-d') }}" class="{{ $inp }}">
                        <input name="result_note" value="{{ $o->result_note }}" class="{{ $inp }}" placeholder="{{ $m('result') }}"><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                @endcan
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ $m('none') }}</p>@endforelse
    </div>
</x-layouts.admin>
