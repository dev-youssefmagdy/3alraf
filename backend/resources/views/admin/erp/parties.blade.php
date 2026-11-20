<x-layouts.admin :title="__('erp.cards.'.$type.'s')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.'.$type.'s')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $u = 'admin.erp.parties.'; @endphp
    <x-ui.card class="mb-5">
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <form method="GET" class="flex items-end gap-3"><label>{{ __('erp_ext.f.as_of') }}<input type="date" name="to" value="{{ $to }}" class="form-control mt-1 !w-auto"></label><x-ui.button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-ui.button></form>
            <a class="btn-sm btn-secondary" href="{{ route($u.'ageing', $type) }}">{{ __('erp_ext.party.ageing') }}</a>
            <form method="GET" class="flex items-end gap-2" onsubmit="if(!this.pid.value){return false}this.action='{{ url(route($u.'index', $type)) }}/'+this.pid.value">
                <label>{{ __('erp_ext.party.open_party') }}<select name="pid" class="form-control mt-1 !w-auto"><option value="">{{ __('erp_ext.party.search') }}</option>@foreach ($directory as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
                <x-ui.button type="submit" variant="secondary">{{ __('erp_ext.f.view') }}</x-ui.button>
            </form>
        </div>
    </x-ui.card>
    <x-ui.card class="mb-5" :title="__('erp_ext.party.recon')">
        <div class="grid gap-2 text-sm sm:grid-cols-4">
            <div><span class="text-panel-text-subtle">{{ __('erp_ext.party.recon_parties') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['parties']) }}</div></div>
            <div><span class="text-panel-text-subtle">{{ __('erp_ext.f.unattributed') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['unattributed']) }}</div></div>
            <div><span class="text-panel-text-subtle">{{ __('erp_ext.party.recon_control') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['control']) }}</div></div>
            <div class="{{ $recon['ok'] ? 'text-panel-success-fg' : 'text-panel-danger-fg' }} font-semibold">{{ $recon['ok'] ? __('erp_ext.party.recon_ok') : __('erp_ext.party.recon_bad') }}</div>
        </div>
    </x-ui.card>
    <x-ui.card>
        <x-ui.table><thead><tr><th>{{ __('erp_ext.f.name') }}</th><th class="text-end">{{ __('erp_ext.party.outstanding_'.$type) }}</th><th class="text-end">{{ __('erp_ext.party.advance') }}</th><th class="text-end">{{ __('erp_ext.party.net') }}</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr><td data-label="">{{ $r['name'] }}</td><td data-label="{{ __('erp_ext.party.outstanding_'.$type) }}" class="text-end tabular-nums">{{ $n($r['outstanding']) }}</td><td data-label="{{ __('erp_ext.party.advance') }}" class="text-end tabular-nums">{{ $n($r['advance']) }}</td><td data-label="{{ __('erp_ext.party.net') }}" class="text-end font-semibold tabular-nums">{{ $n($r['balance']) }}</td><td class="ui-table-actions text-end"><a class="text-panel-accent-text" href="{{ route($u.'show', [$type, $r['id']]) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="5">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody><tfoot><tr class="font-semibold"><td class="py-2">{{ __('erp_ext.f.total') }}</td><td colspan="2"></td><td class="text-end tabular-nums">{{ $n($total) }}</td><td></td></tr></tfoot></x-ui.table>
    </x-ui.card>
</x-layouts.admin>
