<x-layouts.admin guard="tenant" :title="__('growth_hub.gift.title')">
    @php $g = fn ($k) => __('growth_hub.gift.'.$k); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $g('admin_hint') }}</p>
        <a href="{{ $url }}" target="_blank" class="text-sm text-brand-700 underline" dir="ltr">{{ $g('link') }}: {{ $url }}</a>
        <x-card :title="$g('title')">
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
                <div><dt class="text-slate-500">{{ $g('sessions') }}</dt><dd class="text-lg font-bold">{{ $stats['sessions'] }}</dd></div>
                <div><dt class="text-slate-500">{{ $g('completion') }}</dt><dd class="text-lg font-bold">{{ $stats['completion_rate'] === null ? '-' : $stats['completion_rate'].'%' }}</dd></div>
                <div><dt class="text-slate-500">{{ $g('cart_rate') }}</dt><dd class="text-lg font-bold">{{ $stats['cart_rate'] === null ? '-' : $stats['cart_rate'].'%' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('growth_gaps.gift_orders') }}</dt><dd class="text-lg font-bold">{{ $stats['orders'] }}</dd></div>
                <div><dt class="text-slate-500">{{ __('growth_gaps.gift_order_rate') }}</dt><dd class="text-lg font-bold">{{ $stats['order_rate'] === null ? '-' : $stats['order_rate'].'%' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('growth_gaps.gift_aov') }}</dt><dd class="text-lg font-bold">{{ $stats['orders_aov'] ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">{{ $g('avg_budget') }}</dt><dd class="text-lg font-bold">{{ $stats['avg_budget'] ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">{{ $g('no_result') }}</dt><dd class="text-lg font-bold">{{ $stats['no_result'] }}</dd></div>
            </dl>
            <p class="mt-3 text-sm text-slate-600">{{ $g('by_occasion') }}: @foreach ($stats['by_occasion'] as $o => $c){{ $o ? __('growth_hub.gift.occasions.'.$o) : '-' }} ({{ $c }}) @endforeach</p>
        </x-card>
    </div>
</x-layouts.admin>
