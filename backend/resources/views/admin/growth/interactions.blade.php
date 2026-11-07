<x-layouts.admin guard="tenant" :title="__('growth_hub.inbox.title')">
    @php $g = fn ($k) => __('growth_hub.inbox.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $g('hint') }}</p>
        <x-card :title="$g('level')">
            @can('growth.manage')
                <form data-ajax-form action="{{ route('tenant.growth.interactions.level') }}" method="POST" class="flex flex-wrap items-end gap-2" novalidate>@csrf
                    <select name="level" class="{{ $inp }} max-w-xs">@foreach ($g('levels') as $k => $v)<option value="{{ $k }}" @selected($level === $k)>{{ $v }}</option>@endforeach</select>
                    <x-button type="submit">{{ __('admin.save') }}</x-button>
                </form>
            @else
                <p class="text-sm">{{ $g('levels')[$level] }}</p>
            @endcan
            <p class="mt-2 text-xs text-slate-500">{{ $g('l3_hint') }}</p>
            <p class="mt-1 text-sm">{{ $g('unanswered') }}: <b>{{ $unanswered }}</b></p>
        </x-card>
        <x-card :title="$g('title')">
            <x-datatable :table="$table" :url="route('tenant.growth.interactions.datatable')" />
        </x-card>
        @growthFeature('comment_to_cart')
            @php $l = fn ($k) => __('growth_hub.links.'.$k); @endphp
            <x-card :title="$l('title')">
                <p class="text-sm text-slate-600">{{ $l('hint') }}</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.interactions.links') }}" method="POST" class="mt-2 grid gap-2 sm:grid-cols-4" novalidate>@csrf
                        <label class="text-xs text-slate-600">{{ $l('product') }}<input type="number" name="product_id" class="{{ $inp }}"></label>
                        <label class="text-xs text-slate-600">{{ $l('channel') }}<select name="channel" class="{{ $inp }}">@foreach (['facebook', 'instagram', 'whatsapp'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label>
                        <label class="text-xs text-slate-600">{{ $l('campaign') }}<input name="campaign" class="{{ $inp }}"></label>
                        <div class="flex items-end"><x-button type="submit">{{ $l('create') }}</x-button></div>
                    </form>
                @endcan
                <table class="mt-3 w-full text-sm"><tbody>@foreach ($links as $k)
                    <tr class="border-t"><td class="p-2" dir="ltr">{{ $k->short }}</td><td class="p-2">#{{ $k->product_id }}</td><td class="p-2">{{ $l('clicks') }}: {{ $k->stats['clicks'] }}</td><td class="p-2">{{ $l('orders') }}: {{ $k->stats['orders'] }}</td><td class="p-2">{{ $l('revenue') }}: {{ $k->stats['revenue'] }}</td></tr>
                @endforeach</tbody></table>
            </x-card>
        @endgrowthFeature
    </div>
    @include('tenant.growth._actions')
</x-layouts.admin>
