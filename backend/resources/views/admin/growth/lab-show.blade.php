<x-layouts.admin guard="tenant" :title="$e->name">
    @php $l = fn ($k, $r = []) => __('growth_hub.lab.'.$k, $r); $rec = $m['recommendation']; $v = fn ($x) => $x ?? '—'; @endphp
    <div class="space-y-4">
        <x-card :title="$e->name">
            <p class="text-sm">{{ $e->hypothesis }}</p>
            <p class="text-sm text-slate-600">{{ $l('audience') }}: {{ $e->audience }} | {{ $l('platform') }}: {{ $e->platform ?: '—' }} | {{ $l('budget') }}: {{ $e->budget_cap }} | {{ $l('status') }}: {{ $e->status }}</p>
            <p class="mt-1 text-sm"><b>{{ $l('angle') }}:</b> {{ $e->angle }}</p>
            @if ($angles)<details class="text-sm"><summary class="cursor-pointer">{{ $l('angles_from_content') }}</summary><ul class="list-disc ps-5">@foreach ($angles as $a)<li>{{ $a->title ?: \Illuminate\Support\Str::limit($a->body, 100) }}</li>@endforeach</ul></details>@endif
            <p class="mt-2 text-sm">{{ $e->content_approved_at ? $l('content_ok') : $l('content_pending') }}</p>
            @can('growth.manage')
                <div class="mt-2 flex flex-wrap gap-2">
                    @unless ($e->content_approved_at)<form data-ajax-form action="{{ route('tenant.growth.lab.content', $e->id) }}" method="POST">@csrf<x-button type="submit">{{ $l('approve_content') }}</x-button></form>@endunless
                    @if ($e->content_approved_at && $e->status === 'draft')<form data-ajax-form action="{{ route('tenant.growth.lab.start', $e->id) }}" method="POST">@csrf<x-button type="submit">{{ $l('start') }}</x-button></form>@endif
                </div>
            @endcan
        </x-card>
        <x-card :title="$l('metrics')">
            <div class="grid gap-2 text-sm sm:grid-cols-4">
                @foreach (['spend' => $m['spend'], 'impressions' => $m['impressions'], 'clicks' => $m['clicks'], 'ctr' => $m['ctr'], 'cvr' => $m['cvr'], 'orders' => $m['orders_placed'], 'completed' => $m['orders_completed'], 'cac' => $m['cac'], 'cpa' => $m['cpa'], 'aov' => $m['aov'], 'cm' => $m['contribution_margin']['value'], 'cm_after' => $m['cm_after_ads']['value'], 'cancel' => $m['cancel_rate'], 'return' => $m['return_rate'], 'roas' => $m['roas'], 'days' => $m['days_with_data']] as $k => $x)
                    <div><span class="text-slate-500">{{ $l($k) }}</span><br><b>{{ $v($x) }}</b></div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ __('growth_hub.experiments.roas_note') }}</p>
            <p class="mt-3 text-sm"><b>{{ $l('rec') }}:</b> @if ($rec['action']) {{ $l('actions.'.$rec['action']) }} @elseif ($rec['status'] === 'collecting') {{ $l('collecting', ['what' => collect($rec['reasons'])->map(fn ($r) => $l('reasons.'.$r))->implode(', ')]) }} @else {{ $l('insufficient_data') }} @endif</p>
        </x-card>
        <x-card :title="$l('fatigue')">
            @forelse ($fatigue as $f)<p class="text-sm">{{ $l('fatigue_row', ['c' => $f['creative'], 'b' => $f['baseline_ctr'], 'r' => $f['recent_ctr'], 'd' => $f['ctr_drop_percent']]) }}</p>@empty<p class="text-sm text-slate-500">{{ $l('fatigue_none') }}</p>@endforelse
        </x-card>
        @can('growth.manage')
            <x-card :title="$l('import')">
                <p class="mb-2 text-xs text-slate-500">{{ $l('import_hint') }}</p>
                <form data-ajax-form action="{{ route('tenant.growth.lab.ads', $e->id) }}" method="POST" class="grid gap-3 sm:grid-cols-6" novalidate>@csrf
                    <x-input name="date" type="date" :label="$l('date')" :value="now()->toDateString()" /><x-input name="spend" type="number" step="0.01" min="0" :label="$l('spend')" /><x-input name="impressions" type="number" min="0" :label="$l('impressions')" />
                    <x-input name="clicks" type="number" min="0" :label="$l('clicks')" /><x-input name="creative" :label="$l('creative')" /><div class="self-end"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            </x-card>
            @if ($rec['action'] === 'scale_candidate')
                <x-card :title="$l('budget_req')"><form data-ajax-form action="{{ route('tenant.growth.lab.budget', $e->id) }}" method="POST" class="flex flex-wrap items-end gap-3" novalidate>@csrf
                    <x-input name="budget_cap" type="number" step="0.01" min="0" :label="$l('budget')" /><x-input name="reason" :label="__('growth_hub.fields.note')" /><x-button type="submit">{{ $l('budget_req') }}</x-button></form></x-card>
            @endif
        @endcan
        <x-card :title="$l('links')">
            @foreach ($links as $lk)<p class="text-sm"><code>{{ $lk->short }}</code> | {{ $lk->channel }} | {{ $lk->stats['clicks'] }} / {{ $lk->stats['orders'] }}</p>@endforeach
            @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.lab.links', $e->id) }}" method="POST" class="mt-2 flex items-end gap-3" novalidate>@csrf<x-input name="channel" :label="__('growth_hub.amb.channel')" /><x-button type="submit">{{ $l('new_link') }}</x-button></form>@endcan
        </x-card>
    </div>
</x-layouts.admin>
