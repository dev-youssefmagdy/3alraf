<x-layouts.admin :title="__('erp.cards.distribution')[0]">
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; $c = $calc;
        $row = fn ($label, $v, $bold = false) => '<tr class="border-b border-slate-100 '.($bold ? 'font-semibold' : '').'"><td class="py-1.5">'.e($label).'</td><td class="text-end tabular-nums">'.number_format((float) $v, 2).'</td></tr>'; @endphp
    <div class="mb-4 flex flex-wrap gap-4 text-sm"><a class="text-brand-600" href="{{ route('admin.erp.profits.report') }}">{{ __('erp_dist.report') }}</a></div>
    <x-card :title="__('erp_dist.calc')" class="mb-5">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-button></form>
        <div class="grid gap-6 lg:grid-cols-2">
            <table class="w-full text-sm"><tbody>
                {!! $row(__('erp_dist.l.gross_revenue'), $c['gross_revenue']) !!}{!! $row('- '.__('erp_dist.l.returns'), $c['returns_discounts']) !!}{!! $row(__('erp_dist.l.net_revenue'), $c['net_revenue'], true) !!}
                {!! $row('- '.__('erp_dist.l.cogs'), $c['cogs']) !!}{!! $row(__('erp_dist.l.gross_profit'), $c['gross_profit'], true) !!}
                @foreach ($c['opex_rows'] as $x){!! $row('- '.$x['account']->name, $x['amount']) !!}@endforeach
                {!! $row('- '.__('erp_dist.l.opex'), $c['opex']) !!}{!! $row(__('erp_dist.l.operating_profit'), $c['operating_profit'], true) !!}
                {!! $row('+ '.__('erp_dist.l.other_income'), $c['other_income']) !!}{!! $row('- '.__('erp_dist.l.other_expense'), $c['other_expense']) !!}
                {!! $row(__('erp_dist.l.before_tax'), $c['profit_before_tax'], true) !!}{!! $row('- '.__('erp_dist.l.tax'), $c['tax']) !!}{!! $row(__('erp_dist.l.net_profit'), $c['net_profit'], true) !!}
                {!! $row(__('erp_dist.l.prior_retained'), $c['prior_retained']) !!}{!! $row(__('erp_dist.l.after_losses'), $c['profit_after_losses']) !!}
                {!! $row('- '.__('erp_dist.l.reserve').' ('.$c['reserve_percent'].'%)', $c['reserve_amount']) !!}{!! $row(__('erp_dist.l.distributable_profit'), $c['distributable_profit'], true) !!}
            </tbody></table>
            <table class="w-full text-sm"><tbody>
                @foreach ($c['cash_rows'] as $role => $v){!! $row(__('erp_ext.roles.'.$role), $v) !!}@endforeach
                {!! $row(__('erp_dist.l.cash_total'), $c['cash_total'], true) !!}
                @foreach ($c['liability_rows'] as $role => $v){!! $row('- '.__('erp_dist.l.liab.'.$role), $v) !!}@endforeach
                {!! $row('- '.__('erp_dist.l.tax_provision'), $c['tax_estimated']) !!}{!! $row('- '.__('erp_dist.l.reserves_balance'), $c['reserves_balance']) !!}{!! $row('- '.__('erp_dist.l.reserve'), $c['reserve_amount']) !!}{!! $row('- '.__('erp_dist.l.buffer'), $c['cash_buffer']) !!}
                {!! $row(__('erp_dist.l.distributable_cash'), $c['distributable_cash'], true) !!}
                {!! $row(__('erp_dist.l.distributable'), $c['distributable'], true) !!}
            </tbody></table>
        </div>
        @if ($c['cash_limited'])<p class="mt-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('erp_dist.cash_limited') }}</p>@endif
        @if ($ready)<p class="mt-3 rounded bg-slate-100 px-3 py-2 text-sm text-slate-700">{{ __('erp_dist.not_ready') }} {{ $ready }}</p>@endif
    </x-card>
    @if ($canDistribute)
    <x-card :title="__('erp_dist.new')" class="mb-5">
        <form method="POST" action="{{ route('admin.erp.profits.store') }}" class="grid gap-3 sm:grid-cols-3 text-sm">@csrf
            <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
            <label>{{ __('erp_dist.retain_percent') }}<input name="retain_percent" type="number" step="0.01" min="0" max="100" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.retain_amount') }}<input name="retain_amount" type="number" step="0.01" min="0" class="{{ $in }}"></label>
            <label>{{ __('erp.f.description') }}<input name="note" maxlength="300" class="{{ $in }}"></label>
            <div class="sm:col-span-3"><p class="mb-1 text-slate-500">{{ __('erp_dist.override_hint') }}</p><div class="grid gap-2 sm:grid-cols-3">@foreach ($owners as $o)<label>{{ $o->name }} ({{ rtrim(rtrim(number_format((float) $o->ownership_percent, 4), '0'), '.') }}%)<input name="overrides[{{ $o->id }}]" type="number" step="0.01" min="0" class="{{ $in }}"></label>@endforeach</div></div>
            <label class="sm:col-span-3"><input type="checkbox" name="acknowledge_open" value="1"> {{ __('erp_dist.ack_open') }}</label>
            <div class="sm:col-span-3"><x-button type="submit">{{ __('erp_dist.create') }}</x-button></div>
        </form>
    </x-card>
    @endif
    <x-card :title="__('erp_dist.runs')">
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">#</th><th class="text-start">{{ __('erp.f.period') }}</th><th class="text-end">{{ __('erp_dist.l.net_profit') }}</th><th class="text-end">{{ __('erp_dist.distributed') }}</th><th class="text-start">{{ __('erp.f.status') }}</th><th></th></tr></thead><tbody>
        @forelse ($runs as $r)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $r->id }}</td><td>{{ $r->period_start->format('Y-m-d') }} → {{ $r->period_end->format('Y-m-d') }}</td><td class="text-end tabular-nums">{{ $n($r->net_profit) }}</td><td class="text-end tabular-nums">{{ $n($r->distributed_amount) }}</td><td>{{ __('erp_dist.status.'.$r->status) }}</td><td class="text-end"><a class="text-brand-600" href="{{ route('admin.erp.profits.show', $r->id) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="6" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody></table></div>
    </x-card>
</x-layouts.admin>
