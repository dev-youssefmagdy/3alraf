<x-layouts.admin guard="tenant" :title="__('growth_hub.voc.title')">
    @php $v = fn ($k) => __('growth_hub.voc.'.$k); $cat = fn ($c) => __('growth_hub.inbox.categories.'.$c); $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $v('hint') }}</p>
        <div class="grid gap-4 lg:grid-cols-3">
            <x-card :title="$v('by_category')">@forelse ($data['by_category'] as $k => $n)<p class="text-sm">{{ $cat($k) }}: <b>{{ $n }}</b></p>@empty<p class="text-sm text-slate-500">{{ $v('none') }}</p>@endforelse</x-card>
            <x-card :title="$v('by_channel')">@forelse ($data['by_channel'] as $k => $n)<p class="text-sm">{{ __('growth_hub.inbox.channels.'.$k) }}: <b>{{ $n }}</b></p>@empty<p class="text-sm text-slate-500">{{ $v('none') }}</p>@endforelse</x-card>
            <x-card :title="$v('by_product')">@forelse ($data['by_product'] as $p)<p class="text-sm">{{ $p['name'] }}: <b>{{ $p['count'] }}</b> ({{ $p['negative'] }} -)</p>@empty<p class="text-sm text-slate-500">{{ $v('none') }}</p>@endforelse</x-card>
        </div>
        <x-card :title="$v('recurring')">@forelse ($data['recurring_objections'] as $r)<p class="text-sm">#{{ $r['product_id'] }} - {{ $cat($r['category']) }}: {{ $r['count'] }} {{ $v('mentions') }}</p>@empty<p class="text-sm text-slate-500">{{ $v('none') }}</p>@endforelse
            @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.voc.generate') }}" method="POST" class="mt-2" novalidate>@csrf<x-button type="submit">{{ $v('generate') }}</x-button></form>@endcan</x-card>
        <x-card :title="$v('tasks')">
            @forelse ($tasks as $t)
                <div class="border-t py-2 text-sm"><b>{{ $t->title }}</b>
                    <span class="text-xs text-slate-500">{{ $v('before') }}: {{ $t->before_value ?? '-' }} / {{ $v('after') }}: {{ $t->after_value ?? '-' }} / {{ $v('due') }}: {{ $t->due_on?->format('Y-m-d') ?? '-' }} / {{ $v('owner') }}: {{ $t->owner ?? '-' }}</span>
                    @can('growth.manage')
                        <form data-ajax-form action="{{ route('tenant.growth.voc.tasks.update', $t->id) }}" method="POST" class="mt-1 flex flex-wrap items-center gap-2" novalidate>@csrf @method('PUT')
                            <select name="status" class="{{ $inp }}">@foreach ($v('statuses') as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ $l }}</option>@endforeach</select>
                            <input name="owner" value="{{ $t->owner }}" class="{{ $inp }}" placeholder="{{ $v('owner') }}"><input type="date" name="due_on" value="{{ $t->due_on?->format('Y-m-d') }}" class="{{ $inp }}">
                            <x-button type="submit">{{ __('admin.save') }}</x-button></form>
                    @else <span class="badge">{{ $v('statuses')[$t->status] }}</span> @endcan
                </div>
            @empty<p class="text-sm text-slate-500">{{ $v('none') }}</p>@endforelse
        </x-card>
    </div>
</x-layouts.admin>
