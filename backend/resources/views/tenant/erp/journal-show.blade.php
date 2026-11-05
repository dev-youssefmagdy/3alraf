<x-layouts.admin guard="tenant" :title="$entry->number">
    @include('tenant.erp._flash')
    @php $u = auth('tenant')->user(); @endphp
    <x-card :title="$entry->number.' - '.__('erp.statuses.'.$entry->status)">
        <x-slot:actions>
            @if ($entry->status === 'draft')
                @can('erp.journals.post')<form method="POST" action="{{ route('tenant.erp.journals.post', $entry->id) }}">@csrf<x-button type="submit">{{ __('erp.j.post') }}</x-button></form>@endcan
                @can('erp.journals.create')<form method="POST" action="{{ route('tenant.erp.journals.destroy', $entry->id) }}">@csrf @method('DELETE')<x-button type="submit" variant="danger">{{ __('erp.j.delete') }}</x-button></form>@endcan
            @endif
        </x-slot:actions>
        <dl class="mb-4 grid gap-2 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">{{ __('erp.f.date') }}</dt><dd>{{ $entry->entry_date->format('Y-m-d') }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp.f.type') }}</dt><dd>{{ __('erp.entry_types.'.$entry->type) }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp.f.period') }}</dt><dd>{{ $entry->period?->name ?? '-' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('erp.f.source') }}</dt><dd>{{ $entry->source_type ? $entry->source_type.' #'.$entry->source_id : '-' }}</dd></div>
            <div class="md:col-span-4"><dt class="text-slate-500">{{ __('erp.f.description') }}</dt><dd>{{ $entry->description }}</dd></div>
            @if ($entry->attachment)<div><dt class="text-slate-500">{{ __('erp_acc.attachment') }}</dt><dd><a class="inline-flex items-center gap-1 text-brand-600 hover:underline" href="{{ route('tenant.erp.journals.show', ['id' => $entry->id, 'download' => 'attachment']) }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.2 7.8l-6.4 6.4a2 2 0 102.8 2.8l6.4-6.4a4 4 0 00-5.6-5.6l-6.4 6.4a6 6 0 108.5 8.5L21 13.4"/></svg>{{ __('erp_acc.attachment_download') }}</a></dd></div>@endif
            @if ($entry->reverses)<div><dt class="text-slate-500">{{ __('erp.j.reverses') }}</dt><dd><a class="text-brand-600" href="{{ route('tenant.erp.journals.show', $entry->reverses->id) }}">{{ $entry->reverses->number }}</a></dd></div>@endif
        </dl>
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp.f.account') }}</th><th class="text-start">{{ __('erp.f.memo') }}</th><th class="text-end">{{ __('erp.f.debit') }}</th><th class="text-end">{{ __('erp.f.credit') }}</th></tr></thead>
        <tbody>@foreach ($entry->lines as $l)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $l->account->code }} - {{ $l->account->name }}</td><td>{{ $l->memo }}</td><td class="text-end tabular-nums">{{ (float) $l->debit ? number_format((float) $l->debit, 2) : '' }}</td><td class="text-end tabular-nums">{{ (float) $l->credit ? number_format((float) $l->credit, 2) : '' }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="font-semibold"><td colspan="2" class="py-2">{{ __('erp.f.total') }}</td><td class="text-end tabular-nums">{{ number_format((float) $entry->lines->sum('debit'), 2) }}</td><td class="text-end tabular-nums">{{ number_format((float) $entry->lines->sum('credit'), 2) }}</td></tr></tfoot></table>
        @if ($entry->status === 'posted' && \App\Services\Erp\Posting\DocumentPoster::isDocumentEntry($entry) && $u->can('erp.journals.reverse') && $u->can('erp.journals.post'))
            <form method="POST" action="{{ route('tenant.erp.journals.repost', $entry->id) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t pt-4 text-sm">@csrf
                <p class="w-full text-xs text-slate-500">{{ __('erp_acc.repost_hint') }}</p>
                <label>{{ __('erp.j.reverse_reason') }}<input name="reason" required class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>
                <x-button type="submit" variant="danger">{{ __('erp_acc.repost') }}</x-button>
            </form>
        @elseif ($entry->status === 'posted' && $entry->type !== 'reversal' && $u->can('erp.journals.reverse'))
            <form method="POST" action="{{ route('tenant.erp.journals.reverse', $entry->id) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t pt-4 text-sm">@csrf
                <label>{{ __('erp.j.reverse_reason') }}<input name="reason" required class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>
                <label>{{ __('erp.f.date') }}<input type="date" name="date" value="{{ now()->toDateString() }}" min="{{ \App\Support\ErpRules::MIN_DATE }}" max="{{ \App\Support\ErpRules::maxDate() }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>
                <x-button type="submit" variant="danger">{{ __('erp.j.reverse') }}</x-button>
            </form>
        @elseif ($entry->status !== 'draft')<p class="mt-4 text-xs text-slate-500">{{ __('erp.j.immutable') }}</p>@endif
    </x-card>
</x-layouts.admin>
