<x-layouts.admin guard="tenant" :title="__('growth_hub.lab.title')">
    @php $l = fn ($k, $r = []) => __('growth_hub.lab.'.$k, $r); $opts = $products->mapWithKeys(fn ($tp) => [$tp->product_id => $tp->product?->name])->all(); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $l('hint') }}</p>
        @can('growth.manage')
            <x-card :title="$l('new')">
                <form data-ajax-form action="{{ route('tenant.growth.lab.store') }}" method="POST" class="grid gap-3 sm:grid-cols-3" novalidate>@csrf
                    <x-input name="name" :label="__('growth_hub.fields.name')" required class="sm:col-span-2" />
                    <x-select search name="product_id" :label="$l('product')" :options="$opts" />
                    <x-textarea name="hypothesis" :label="$l('hypothesis')" class="sm:col-span-3" />
                    <x-input name="audience" :label="$l('audience')" required /><x-input name="platform" :label="$l('platform')" /><x-input name="budget_cap" type="number" step="0.01" min="0" :label="$l('budget')" required />
                    <x-textarea name="angle" :label="$l('angle')" class="sm:col-span-3" />
                    <x-input name="starts_on" type="date" :label="__('growth_hub.fields.starts_on')" /><x-input name="ends_on" type="date" :label="__('growth_hub.fields.ends_on')" />
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            </x-card>
        @endcan
        @forelse ($rows as $e)
            @php $m = $e->lab; $rec = $m['recommendation']; @endphp
            <x-card :title="$e->name">
                <p class="text-xs text-slate-500">{{ $e->status }} | {{ $l('spend') }}: {{ $m['spend'] }} | {{ $l('orders') }}: {{ $m['orders_placed'] }} | {{ $l('roas') }}: {{ $m['roas'] ?? '—' }} | {{ $l('cm_after') }}: {{ $m['cm_after_ads']['value'] ?? '—' }}</p>
                <p class="mt-1 text-sm"><b>{{ $l('rec') }}:</b> @if ($rec['action']) {{ $l('actions.'.$rec['action']) }} @elseif ($rec['status'] === 'collecting') {{ $l('collecting', ['what' => collect($rec['reasons'])->map(fn ($r) => $l('reasons.'.$r))->implode(', ')]) }} @else {{ $l('insufficient_data') }} @endif</p>
                <a class="text-sm text-brand-600" href="{{ route('tenant.growth.lab.show', $e->id) }}">{{ $l('open') }}</a>
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ $l('none') }}</p>@endforelse
    </div>
</x-layouts.admin>
