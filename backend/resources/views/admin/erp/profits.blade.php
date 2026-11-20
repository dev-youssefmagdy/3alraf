<x-layouts.admin :title="__('erp.cards.distribution')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.distribution')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'form-control mt-1'; $c = $calc;
        $row = fn ($label, $v, $bold = false) => '<tr class="border-b border-panel-border '.($bold ? 'font-semibold' : '').'"><td class="py-1.5">'.e($label).'</td><td class="text-end tabular-nums">'.number_format((float) $v, 2).'</td></tr>'; @endphp
    <div class="mb-4 flex flex-wrap gap-4 text-sm"><a class="text-panel-accent-text" href="{{ route('admin.erp.profits.report') }}">{{ __('erp_dist.report') }}</a></div>
    <x-ui.card :title="__('erp_dist.calc')" class="mb-5">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="form-control mt-1 !w-auto"></label><label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="form-control mt-1 !w-auto"></label><x-ui.button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-ui.button></form>
        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.table :mobile="false"><tbody>
                {!! $row(__('erp_dist.l.gross_revenue'), $c['gross_revenue']) !!}{!! $row('- '.__('erp_dist.l.returns'), $c['returns_discounts']) !!}{!! $row(__('erp_dist.l.net_revenue'), $c['net_revenue'], true) !!}
                {!! $row('- '.__('erp_dist.l.cogs'), $c['cogs']) !!}{!! $row(__('erp_dist.l.gross_profit'), $c['gross_profit'], true) !!}
                @foreach ($c['opex_rows'] as $x){!! $row('- '.$x['account']->name, $x['amount']) !!}@endforeach
                {!! $row('- '.__('erp_dist.l.opex'), $c['opex']) !!}{!! $row(__('erp_dist.l.operating_profit'), $c['operating_profit'], true) !!}
                {!! $row('+ '.__('erp_dist.l.other_income'), $c['other_income']) !!}{!! $row('- '.__('erp_dist.l.other_expense'), $c['other_expense']) !!}
                {!! $row(__('erp_dist.l.before_tax'), $c['profit_before_tax'], true) !!}{!! $row('- '.__('erp_dist.l.tax'), $c['tax']) !!}{!! $row(__('erp_dist.l.net_profit'), $c['net_profit'], true) !!}
                {!! $row(__('erp_dist.l.prior_retained'), $c['prior_retained']) !!}{!! $row(__('erp_dist.l.after_losses'), $c['profit_after_losses']) !!}
                {!! $row('- '.__('erp_dist.l.reserve').' ('.$c['reserve_percent'].'%)', $c['reserve_amount']) !!}{!! $row(__('erp_dist.l.distributable_profit'), $c['distributable_profit'], true) !!}
            </tbody></x-ui.table>
            <x-ui.table :mobile="false"><tbody>
                @foreach ($c['cash_rows'] as $role => $v){!! $row(__('erp_ext.roles.'.$role), $v) !!}@endforeach
                {!! $row(__('erp_dist.l.cash_total'), $c['cash_total'], true) !!}
                @foreach ($c['liability_rows'] as $role => $v){!! $row('- '.__('erp_dist.l.liab.'.$role), $v) !!}@endforeach
                {!! $row('- '.__('erp_dist.l.tax_provision'), $c['tax_estimated']) !!}{!! $row('- '.__('erp_dist.l.reserves_balance'), $c['reserves_balance']) !!}{!! $row('- '.__('erp_dist.l.reserve'), $c['reserve_amount']) !!}{!! $row('- '.__('erp_dist.l.buffer'), $c['cash_buffer']) !!}
                {!! $row(__('erp_dist.l.distributable_cash'), $c['distributable_cash'], true) !!}
                {!! $row(__('erp_dist.l.distributable'), $c['distributable'], true) !!}
            </tbody></x-ui.table>
        @if ($c['cash_limited'])<p class="mt-3 rounded bg-panel-warning-bg px-3 py-2 text-sm text-panel-warning-fg">{{ __('erp_dist.cash_limited') }}</p>@endif
        @if ($ready)<p class="mt-3 rounded bg-panel-surface-sunken px-3 py-2 text-sm text-panel-text">{{ __('erp_dist.not_ready') }} {{ $ready }}</p>@endif
    </x-ui.card>
    @if ($canDistribute)
    <x-ui.card :title="__('erp_dist.new')" class="mb-5">
        <form method="POST" action="{{ route('admin.erp.profits.store') }}" class="grid gap-3 sm:grid-cols-3 text-sm">@csrf
            <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
            <label>{{ __('erp_dist.retain_percent') }}<input name="retain_percent" type="number" step="0.01" min="0" max="100" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.retain_amount') }}<input name="retain_amount" type="number" step="0.01" min="0" class="{{ $in }}"></label>
            <label>{{ __('erp.f.description') }}<input name="note" maxlength="300" class="{{ $in }}"></label>
            <div class="sm:col-span-3"><p class="mb-1 text-panel-text-subtle">{{ __('erp_dist.override_hint') }}</p><div class="grid gap-2 sm:grid-cols-3">@foreach ($owners as $o)<label>{{ $o->name }} ({{ rtrim(rtrim(number_format((float) $o->ownership_percent, 4), '0'), '.') }}%)<input name="overrides[{{ $o->id }}]" type="number" step="0.01" min="0" class="{{ $in }}"></label>@endforeach</div></div>
            <label class="sm:col-span-3"><input type="checkbox" name="acknowledge_open" value="1"> {{ __('erp_dist.ack_open') }}</label>
            <div class="sm:col-span-3"><x-ui.button type="submit">{{ __('erp_dist.create') }}</x-ui.button></div>
        </form>
    </x-ui.card>
    @endif
    <x-ui.card :title="__('erp_dist.runs')">
        <x-ui.table><thead><tr><th>#</th><th>{{ __('erp.f.period') }}</th><th class="text-end">{{ __('erp_dist.l.net_profit') }}</th><th class="text-end">{{ __('erp_dist.distributed') }}</th><th>{{ __('erp.f.status') }}</th><th></th></tr></thead><tbody>
        @forelse ($runs as $r)<tr><td data-label="">{{ $r->id }}</td><td data-label="{{ __('erp.f.period') }}">{{ $r->period_start->format('Y-m-d') }} → {{ $r->period_end->format('Y-m-d') }}</td><td data-label="{{ __('erp_dist.l.net_profit') }}" class="text-end tabular-nums">{{ $n($r->net_profit) }}</td><td data-label="{{ __('erp_dist.distributed') }}" class="text-end tabular-nums">{{ $n($r->distributed_amount) }}</td><td data-label="{{ __('erp.f.status') }}">{{ __('erp_dist.status.'.$r->status) }}</td><td class="ui-table-actions text-end"><a class="text-panel-accent-text" href="{{ route('admin.erp.profits.show', $r->id) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="6">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody></x-ui.table>
    </x-ui.card>
</x-layouts.admin>
