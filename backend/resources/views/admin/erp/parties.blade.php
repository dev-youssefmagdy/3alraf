<x-layouts.admin :title="__('erp.cards.'.$type.'s')[0]">
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $u = 'admin.erp.parties.'; @endphp
    <x-card class="mb-5">
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <form method="GET" class="flex items-end gap-3"><label>{{ __('erp_ext.f.as_of') }}<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-button></form>
            <a class="btn-sm btn-secondary" href="{{ route($u.'ageing', $type) }}">{{ __('erp_ext.party.ageing') }}</a>
            <form method="GET" class="flex items-end gap-2" onsubmit="if(!this.pid.value){return false}this.action='{{ url(route($u.'index', $type)) }}/'+this.pid.value">
                <label>{{ __('erp_ext.party.open_party') }}<select name="pid" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"><option value="">{{ __('erp_ext.party.search') }}</option>@foreach ($directory as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
                <x-button type="submit" variant="secondary">{{ __('erp_ext.f.view') }}</x-button>
            </form>
        </div>
    </x-card>
    <x-card class="mb-5" :title="__('erp_ext.party.recon')">
        <div class="grid gap-2 text-sm sm:grid-cols-4">
            <div><span class="text-slate-500">{{ __('erp_ext.party.recon_parties') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['parties']) }}</div></div>
            <div><span class="text-slate-500">{{ __('erp_ext.f.unattributed') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['unattributed']) }}</div></div>
            <div><span class="text-slate-500">{{ __('erp_ext.party.recon_control') }}</span><div class="tabular-nums font-semibold">{{ $n($recon['control']) }}</div></div>
            <div class="{{ $recon['ok'] ? 'text-emerald-700' : 'text-red-600' }} font-semibold">{{ $recon['ok'] ? __('erp_ext.party.recon_ok') : __('erp_ext.party.recon_bad') }}</div>
        </div>
    </x-card>
    <x-card>
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th><th class="text-end">{{ __('erp_ext.party.outstanding_'.$type) }}</th><th class="text-end">{{ __('erp_ext.party.advance') }}</th><th class="text-end">{{ __('erp_ext.party.net') }}</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr class="border-b border-slate-100"><td class="py-1.5">{{ $r['name'] }}</td><td class="text-end tabular-nums">{{ $n($r['outstanding']) }}</td><td class="text-end tabular-nums">{{ $n($r['advance']) }}</td><td class="text-end font-semibold tabular-nums">{{ $n($r['balance']) }}</td><td class="text-end"><a class="text-brand-600" href="{{ route($u.'show', [$type, $r['id']]) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="5" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody><tfoot><tr class="font-semibold"><td class="py-2">{{ __('erp_ext.f.total') }}</td><td colspan="2"></td><td class="text-end tabular-nums">{{ $n($total) }}</td><td></td></tr></tfoot></table>
    </x-card>
</x-layouts.admin>
