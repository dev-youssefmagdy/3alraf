<x-layouts.admin guard="tenant" :title="__('erp.cards.periods')[0]">
    @include('tenant.erp._flash')
    @php $u = auth('tenant')->user(); @endphp
    <x-card>
        @if ($fy)<div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm"><b>{{ $fy->name }}</b><span class="rounded-full px-2 py-0.5 text-xs {{ $fy->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">{{ __('erp.statuses.'.$fy->status) }}</span>
            @if ($fy->status === 'open' && $u->can('erp.periods.close'))<form method="POST" action="{{ route('tenant.erp.periods.year-close', $fy->id) }}" onsubmit="return confirm('{{ __('erp.p.year_close_confirm') }}')">@csrf<button class="btn-sm btn-secondary">{{ __('erp.p.year_close') }}</button></form>
            @elseif ($fy->status === 'closed' && $u->can('erp.periods.reopen'))<form method="POST" action="{{ route('tenant.erp.periods.year-reopen', $fy->id) }}" class="inline-flex gap-2">@csrf<input name="reason" required placeholder="{{ __('erp.p.reopen_reason') }}" class="rounded border border-slate-300 px-2 py-1 text-xs"><button class="btn-sm btn-danger">{{ __('erp.p.year_reopen') }}</button></form>@endif</div>@endif
        <form method="GET" class="mb-4 flex items-end gap-3 text-sm"><label>{{ __('erp.p.year') }}<input type="number" name="year" value="{{ $year }}" class="mt-1 block w-28 rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-button></form>
        <table class="w-full text-sm"><tbody>
        @foreach ($periods as $p)
            <tr class="border-b border-slate-100"><td class="py-2 font-mono">{{ $p->name }}</td><td>{{ $p->starts_on->format('Y-m-d') }} → {{ $p->ends_on->format('Y-m-d') }}</td>
                <td><span class="rounded-full px-2 py-0.5 text-xs {{ $p->isOpen() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">{{ __('erp.statuses.'.$p->status) }}</span></td>
                <td class="text-end">
                    @if ($p->isOpen() && $u->can('erp.periods.close'))<form method="POST" action="{{ route('tenant.erp.periods.close', $p->id) }}" class="inline">@csrf<button class="btn-sm btn-secondary">{{ __('erp.p.close') }}</button></form>
                    @elseif (! $p->isOpen() && $u->can('erp.periods.reopen'))<form method="POST" action="{{ route('tenant.erp.periods.reopen', $p->id) }}" class="inline-flex gap-2">@csrf<input name="reason" required placeholder="{{ __('erp.p.reopen_reason') }}" class="rounded border border-slate-300 px-2 py-1 text-xs"><button class="btn-sm btn-danger">{{ __('erp.p.reopen') }}</button></form>@endif
                </td></tr>
        @endforeach
        </tbody></table>
    </x-card>
    @if ($log->isNotEmpty())
    <x-card :title="__('erp.p.history')" class="mt-5"><ul class="space-y-1 text-sm text-slate-600">@foreach ($log as $l)<li>{{ $l->created_at->format('Y-m-d H:i') }} - {{ $l->action }} {{ $l->meta['name'] ?? '' }} {{ $l->meta['reason'] ?? '' }}</li>@endforeach</ul></x-card>
    @endif
</x-layouts.admin>
