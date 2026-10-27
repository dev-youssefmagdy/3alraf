<x-layouts.admin guard="tenant" :title="__('growth_capital.inv.title')">
    @php $i = fn ($k, $r = []) => __('growth_capital.inv.'.$k, $r); $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs'; $inv = $summary['inventory']; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $i('hint') }}</p>
        <p class="text-xs text-slate-500">{{ $i('assume', ['u' => config('growth.capital.promo_uplift'), 'b' => config('growth.capital.promo_baseline_sell_through')]) }}</p>
        <div class="grid gap-3 sm:grid-cols-4">
            <x-card :title="$i('cash_tied')"><p class="text-lg font-bold">{{ number_format($summary['cash_tied'], 2) }}</p></x-card>
            <x-card :title="$i('slow_value')"><p class="text-lg font-bold">{{ number_format($summary['slow_moving_value'], 2) }}</p></x-card>
            <x-card :title="$i('aged_value', ['days' => $summary['aged_days']])"><p class="text-lg font-bold">{{ number_format($summary['aged_value'], 2) }}</p></x-card>
            <x-card :title="$i('turnover')"><p class="text-lg font-bold">{{ $inv['inventory_turnover']['value'] ?? $i('unknown') }}</p></x-card>
        </div>
        <x-card :title="$i('title')">
            <div class="overflow-x-auto"><table class="w-full text-xs">
                <thead><tr class="text-start text-slate-500"><th>{{ __('growth_capital.common.product') }}</th><th>{{ $i('abc') }}</th><th>{{ $i('cover') }}</th><th>{{ $i('velocity') }}</th><th>{{ $i('age') }}</th><th>{{ $i('margin') }}</th><th>{{ $i('action') }}</th><th>{{ $i('impact') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr class="border-t align-top">
                        <td>{{ $r['name'] }}@if ($r['missing'])<div class="text-amber-600">{{ $i('unknown') }}: {{ collect($r['missing'])->map(fn ($m) => $i('missing.'.$m))->implode(', ') }}</div>@endif</td>
                        <td>{{ $r['abc'] }}</td><td>{{ $r['days_of_cover'] ?? '-' }}</td><td>{{ $r['velocity'] }}</td><td>{{ $r['age_days'] ?? '-' }}@if ($r['weighted_age_days'] !== null) <span class="text-xs text-slate-500">({{ $r['weighted_age_days'] }})</span>@endif</td><td>{{ $r['margin_percent'] ?? '-' }}</td>
                        <td><b>{{ $i('actions.'.$r['action']) }}</b>@foreach ($r['reasons'] as $why)<div class="text-slate-500">{{ $i('reasons.'.$why) }}</div>@endforeach
                            @if ($r['action'] === 'reorder')<div>{{ $i('qty') }}: {{ $r['scenarios']['reorder']['qty'] }} | {{ $i('cash') }}: {{ $r['scenarios']['reorder']['cash'] }}</div>@endif
                            @if ($r['action'] === 'promo')<div>-{{ $r['scenarios']['promo']['discount_percent'] }}% | {{ $i('cash') }}: {{ $r['scenarios']['promo']['cash_released'] }}</div>@endif</td>
                        <td>{{ number_format($r['impact'], 2) }}</td>
                        <td>@can('growth.manage')@if ($r['action'] === 'reorder')
                            <form data-ajax-form action="{{ route('tenant.growth.inventory.reorder') }}" method="POST" class="flex flex-wrap gap-1" novalidate>@csrf<input type="hidden" name="product_id" value="{{ $r['product_id'] }}">
                                <select name="supplier_id" class="{{ $inp }}"><option value="">{{ $i('supplier') }}</option>@foreach ($suppliers as $sid => $sn)<option value="{{ $sid }}" @selected($r['supplier_id'] == $sid)>{{ $sn }}</option>@endforeach</select>
                                <select name="branch_id" class="{{ $inp }}">@foreach ($branches as $bid => $bn)<option value="{{ $bid }}">{{ $bn }}</option>@endforeach</select>
                                <x-button type="submit">{{ $i('reorder') }}</x-button></form>@endif @endcan</td>
                    </tr>
                @endforeach
                </tbody></table></div>
        </x-card>
        @can('growth.manage')
            <x-card :title="$i('terms')">
                <form data-ajax-form action="{{ route('tenant.growth.inventory.terms') }}" method="POST" class="grid gap-2 sm:grid-cols-6" novalidate>@csrf
                    <select name="product_id" class="{{ $inp }}">@foreach ($rows as $r)<option value="{{ $r['product_id'] }}">{{ $r['name'] }} ({{ $r['lead_time_days'] }}d / {{ $r['moq'] }})</option>@endforeach</select>
                    <select name="supplier_id" class="{{ $inp }}"><option value="">{{ $i('supplier') }}</option>@foreach ($suppliers as $sid => $sn)<option value="{{ $sid }}">{{ $sn }}</option>@endforeach</select>
                    <input name="lead_time_days" type="number" min="0" value="14" class="{{ $inp }}" placeholder="{{ $i('lead') }}"><input name="moq" type="number" min="1" value="1" class="{{ $inp }}" placeholder="{{ $i('moq') }}">
                    <input name="shelf_life_days" type="number" min="1" class="{{ $inp }}" placeholder="{{ $i('shelf') }}"><x-button type="submit">{{ __('admin.save') }}</x-button>
                </form>
            </x-card>
            <form data-ajax-form action="{{ route('tenant.growth.inventory.story') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ __('growth_capital.story.scan') }}</x-button></form>
        @endcan
        <x-card :title="$i('stories')">
            @forelse ($tasks as $t)<p class="text-sm">{{ $t->title }} <span class="text-xs text-slate-500">({{ $t->status }})</span></p>@empty<p class="text-sm text-slate-500">{{ __('growth_capital.common.none') }}</p>@endforelse
        </x-card>
    </div>
</x-layouts.admin>
