<x-layouts.marketer :title="__('gift_finder.title')">
    @php $g = fn ($k) => __('gift_finder.'.$k); @endphp
    <x-slot:header>
        <x-ui.page-header :title="$g('title')" :subtitle="$g('admin_hint')">
            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-secondary btn-md" dir="ltr">{{ $g('link') }}</a>
            <a href="{{ route('marketer.gift-finder.tags') }}" class="btn btn-primary btn-md">{{ $g('tags_title') }}</a>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4">
        @unless ($enabled)<x-ui.alert tone="warning">{{ $g('disabled_hint') }}</x-ui.alert>@endunless
        <x-ui.card :title="$g('tags_title')">
            <p class="text-sm text-panel-text-muted">{{ $g('tags_progress', ['n' => $tagged, 'total' => $total]) }}
                @if ($tagged === 0)<span class="ms-2 font-semibold text-panel-warning-fg">{{ $g('tags_none') }}</span>@endif</p>
        </x-ui.card>
        <x-ui.card :title="$g('title')">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ([
                    'sessions' => [$g('sessions'), $stats['sessions']], 'completion' => [$g('completion'), $stats['completion_rate'] === null ? '-' : $stats['completion_rate'].'%'],
                    'cart_rate' => [$g('cart_rate'), $stats['cart_rate'] === null ? '-' : $stats['cart_rate'].'%'], 'orders' => [__('gift_finder.orders'), $stats['orders']],
                    'order_rate' => [__('gift_finder.order_rate'), $stats['order_rate'] === null ? '-' : $stats['order_rate'].'%'], 'aov' => [__('gift_finder.aov'), $stats['orders_aov'] ?? '-'],
                    'avg_budget' => [$g('avg_budget'), $stats['avg_budget'] ?? '-'], 'no_result' => [$g('no_result'), $stats['no_result']],
                    'bundle_sessions' => [$g('bundle_sessions'), $stats['bundle_sessions']], 'bundle_carts' => [$g('bundle_carts'), $stats['bundle_carts']], 'bundle_orders' => [$g('bundle_orders'), $stats['bundle_orders']],
                ] as $k => [$lbl, $val])
                    <x-ui.stat-card size="compact" :label="$lbl" :value="$val" data-stat="{{ $k }}" />
                @endforeach
            </div>
            <p class="mt-3 text-sm text-panel-text-muted">{{ $g('by_occasion') }}: @foreach ($stats['by_occasion'] as $o => $c){{ $o ? __('gift_finder.occasions.'.$o) : '-' }} ({{ $c }}) @endforeach</p>
        </x-ui.card>

        <x-ui.card :title="$g('untagged_bundles').' ('.$untagged['counts']['bundles'].' / '.$untagged['counts']['active_bundles'].')'">
            <p class="mb-2 text-sm text-panel-text-muted">{{ $g('untagged_bundles_hint') }} <span class="text-panel-text-muted">{{ $g('untagged_products', ['n' => $untagged['counts']['products']]) }}</span></p>
            @if ($untagged['bundles'])
                <ul class="divide-y divide-panel-border text-sm">
                    @foreach ($untagged['bundles'] as $b)
                        <li class="flex items-center justify-between gap-2 py-2"><span>{{ $b['name'] }} <span class="text-panel-text-muted">({{ $g('items_count') }}: {{ $b['items'] }})</span></span></li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-panel-success-fg">{{ $g('untagged_bundles_none') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="$g('history')">
            <x-ui.table>
                <thead><tr>
                    <th>{{ $g('when') }}</th><th>{{ $g('fields.occasion') }}</th><th>{{ $g('fields.recipient') }}</th>
                    <th>{{ $g('fields.budget') }}</th><th>{{ $g('fields.category_id') }}</th><th>{{ $g('fields.restrictions') }}</th>
                    <th>{{ $g('results') }}</th><th>{{ $g('in_cart') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($sessions as $s)
                    <tr>
                        <td data-label="" class="whitespace-nowrap">{{ $s->created_at->format('Y-m-d H:i') }}</td>
                        <td data-label="{{ $g('fields.occasion') }}">{{ $s->occasion ? $g('occasions.'.$s->occasion) : '-' }}</td>
                        <td data-label="{{ $g('fields.recipient') }}">{{ $s->recipient ? $g('recipients.'.$s->recipient) : '-' }}</td>
                        <td data-label="{{ $g('fields.budget') }}">{{ $s->budget }}</td>
                        <td data-label="{{ $g('fields.category_id') }}">{{ $cats[$s->category_id]->name ?? '-' }}</td>
                        <td data-label="{{ $g('fields.restrictions') }}">{{ $s->restrictions ?: '-' }}</td>
                        <td data-label="{{ $g('results') }}">{{ $s->results_count }}</td>
                        <td data-label="{{ $g('in_cart') }}">{{ count((array) $s->cart_ids) }}</td>
                        <td class="ui-table-actions"><button type="button" class="btn-sm btn-secondary" data-modal-open="gift-results-modal" data-gift-session="{{ $s->id }}">{{ $g('view_results') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-4 text-center text-panel-text-muted">{{ $g('no_history') }}</td></tr>
                @endforelse
                </tbody>
            </x-ui.table>
            <div class="mt-3">{{ $sessions->links() }}</div>
        </x-ui.card>
    </div>

    <x-ui.modal id="gift-results-modal" :title="$g('results')">
        <ul id="gift-results-list" class="space-y-2 text-sm"></ul>
    </x-ui.modal>
    <script>
    (() => {
        const data = @json($details), list = document.getElementById('gift-results-list'), inCart = @json($g('in_cart')), none = @json($g('none'));
        document.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-gift-session]');
            if (!b) return;
            const rows = data[b.dataset.giftSession] || [];
            list.replaceChildren(...(rows.length ? rows : [null]).map((r) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-2 rounded-lg border px-3 py-2' + (r && r.in_cart ? ' border-panel-success-solid bg-panel-success-bg' : '');
                li.textContent = r ? r.name + (r.sku ? ' (' + r.sku + ')' : '') : none;
                if (r && r.in_cart) { const t = document.createElement('span'); t.className = 'text-xs font-semibold text-panel-success-fg'; t.textContent = '✓ ' + inCart; li.append(t); }
                return li;
            }));
        });
    })();
    </script>
</x-layouts.marketer>
