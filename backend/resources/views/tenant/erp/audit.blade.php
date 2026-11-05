<x-layouts.admin guard="tenant" :title="__('erp.cards.audit')[0]">
    @include('tenant.erp._flash')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_acc.audit.action') }}<select name="action" class="{{ $in }}"><option value="">{{ __('erp_acc.audit.all') }}</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected($q['action'] === $a)>{{ $a }}</option>@endforeach</select></label>
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $q['from'] }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $q['to'] }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp.f.filter') }}</x-button>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-audit>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_acc.audit.when') }}</th><th class="text-start">{{ __('erp_acc.audit.action') }}</th><th class="text-start">{{ __('erp_acc.audit.subject') }}</th><th class="text-start">{{ __('erp_acc.audit.user') }}</th><th class="text-start">{{ __('erp_acc.audit.detail') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                @php $meta = $l->meta ? json_encode($l->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''; @endphp
                <tr class="border-b border-slate-100 align-top">
                    <td class="py-1.5 whitespace-nowrap tabular-nums">{{ $l->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="font-mono text-xs">{{ $l->action }}</td>
                    <td>{{ $l->subject_type }} {{ $l->subject_id ? '#'.$l->subject_id : '' }}</td>
                    <td>{{ $l->user_id ? ($users[$l->user_id] ?? '#'.$l->user_id) : '-' }}</td>
                    <td class="max-w-md truncate text-xs text-slate-500" title="{{ $meta }}" dir="ltr">{{ $meta }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </x-card>
</x-layouts.admin>
