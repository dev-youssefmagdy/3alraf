<x-layouts.admin guard="tenant" :title="__('growth_hub.gift.title')">
    @php $g = fn ($k) => __('growth_hub.gift.'.$k); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $g('admin_hint') }}</p>
        <a href="{{ $url }}" target="_blank" class="text-sm text-brand-700 underline" dir="ltr">{{ $g('link') }}: {{ $url }}</a>
        <p class="text-sm"><a href="{{ route('tenant.growth.gift-finder.tags') }}" class="btn btn-secondary">{{ $g('tags_title') }}</a>
            <span class="ms-2 text-slate-600">{{ $g('tags_progress', ['n' => $tagged, 'total' => $total]) }}</span>
            @if ($tagged === 0)<span class="ms-2 text-amber-700">{{ $g('tags_none') }}</span>@endif</p>
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

        <x-card :title="$g('history')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-start text-slate-500">
                        <th class="p-2 text-start">{{ $g('when') }}</th><th class="p-2 text-start">{{ $g('fields.occasion') }}</th><th class="p-2 text-start">{{ $g('fields.recipient') }}</th>
                        <th class="p-2 text-start">{{ $g('fields.budget') }}</th><th class="p-2 text-start">{{ $g('fields.category_id') }}</th><th class="p-2 text-start">{{ $g('fields.restrictions') }}</th>
                        <th class="p-2 text-start">{{ $g('results') }}</th><th class="p-2 text-start">{{ $g('in_cart') }}</th><th></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($sessions as $s)
                        <tr class="border-b">
                            <td class="p-2 whitespace-nowrap">{{ $s->created_at->format('Y-m-d H:i') }}</td>
                            <td class="p-2">{{ $s->occasion ? $g('occasions.'.$s->occasion) : '-' }}</td>
                            <td class="p-2">{{ $s->recipient ? $g('recipients.'.$s->recipient) : '-' }}</td>
                            <td class="p-2">{{ $s->budget }}</td>
                            <td class="p-2">{{ $cats[$s->category_id]->name ?? '-' }}</td>
                            <td class="p-2">{{ $s->restrictions ?: '-' }}</td>
                            <td class="p-2">{{ $s->results_count }}</td>
                            <td class="p-2">{{ count((array) $s->cart_ids) }}</td>
                            <td class="p-2"><button type="button" class="btn-sm btn-secondary" data-modal-open="gift-results-modal" data-gift-session="{{ $s->id }}">{{ $g('view_results') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-4 text-center text-slate-500">{{ $g('no_history') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $sessions->links() }}</div>
        </x-card>
    </div>

    <x-modal id="gift-results-modal" :title="$g('results')">
        <ul id="gift-results-list" class="space-y-2 text-sm"></ul>
    </x-modal>
    <script>
    (() => {
        const data = @json($details), list = document.getElementById('gift-results-list'), inCart = @json($g('in_cart')), none = @json($g('none'));
        document.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-gift-session]');
            if (!b) return;
            const rows = data[b.dataset.giftSession] || [];
            list.replaceChildren(...(rows.length ? rows : [null]).map((r) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-2 rounded-lg border px-3 py-2' + (r && r.in_cart ? ' border-green-300 bg-green-50' : '');
                li.textContent = r ? r.name + (r.sku ? ' (' + r.sku + ')' : '') : none;
                if (r && r.in_cart) { const t = document.createElement('span'); t.className = 'text-xs font-semibold text-green-700'; t.textContent = '✓ ' + inCart; li.append(t); }
                return li;
            }));
        });
    })();
    </script>
</x-layouts.admin>
