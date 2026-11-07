<x-layouts.admin guard="tenant" :title="__('growth_hub.batch.title')">
    @php $b = fn ($k, $r = []) => __('growth_hub.batch.'.$k, $r); $opts = $products->mapWithKeys(fn ($tp) => [$tp->product_id => $tp->product?->name])->all(); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $b('hint') }}</p>
        @can('growth.manage')
            <x-card :title="$b('new')">
                <form data-ajax-form action="{{ route('tenant.growth.batch.store') }}" method="POST" class="grid gap-3 sm:grid-cols-3" novalidate>@csrf
                    <x-input name="name" :label="$b('name')" required /><x-select name="mode" :label="$b('mode')" :options="__('growth_hub.batch.modes')" /><x-select search name="product_id" :label="__('growth_hub.lab.product')" :options="$opts" :placeholder="'—'" />
                    <x-textarea name="description" :label="$b('description')" class="sm:col-span-3" /><x-input name="survey_question" :label="$b('question')" class="sm:col-span-3" />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="preorder" value="1"> {{ $b('preorder') }}</label>
                    <x-textarea name="preorder_terms" :label="$b('terms')" class="sm:col-span-3" />
                    <x-input name="limited_stock_qty" type="number" min="1" :label="$b('qty')" /><x-input name="target_signups" type="number" min="1" :label="$b('target')" value="30" />
                    <x-input name="starts_on" type="date" :label="$b('from')" /><x-input name="ends_on" type="date" :label="$b('to')" />
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            </x-card>
        @endcan
        @forelse ($rows as $t)
            @php $s = $t->stats; @endphp
            <x-card :title="$t->name">
                <p class="text-xs text-slate-500">{{ $b('modes.'.$t->mode) }} | {{ $b('status.'.$t->status) }} | {{ $b('signups') }}: {{ $s['signups'] }}/{{ $s['target'] }} | {{ $b('units') }}: {{ $s['units'] }} @if ($t->preorder)| {{ $b('preorder') }} @endif</p>
                @if ($t->status !== 'draft')<p class="text-sm">{{ $b('link') }}: <code>{{ route('storefront.launch.show', ['locale' => current_tenant()->default_locale, 'code' => $t->code]) }}</code></p>@endif
                <p class="text-sm"><b>{{ $b('gate') }}:</b> {{ $s['gate'] === 'ready' ? $b('ready') : $b('not_ready', ['what' => collect($s['reasons'])->map(fn ($r) => $b('reasons.'.$r))->implode(', ')]) }} @if ($t->decision) | {{ $b('decisions.'.$t->decision) }} @endif</p>
                @can('growth.manage')
                    @if ($t->status === 'draft')<form data-ajax-form action="{{ route('tenant.growth.batch.open', $t->id) }}" method="POST" class="mt-2">@csrf<x-button type="submit">{{ $b('open') }}</x-button></form>@endif
                    @if ($t->status === 'running')<form data-ajax-form action="{{ route('tenant.growth.batch.decide', $t->id) }}" method="POST" class="mt-2 flex flex-wrap items-end gap-2" novalidate>@csrf<x-select name="decision" :label="$b('decide')" :options="__('growth_hub.batch.decisions')" /><x-input name="note" :label="__('growth_hub.fields.note')" /><x-button type="submit">{{ $b('decide') }}</x-button></form>@endif
                @endcan
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ $b('none') }}</p>@endforelse
    </div>
</x-layouts.admin>
