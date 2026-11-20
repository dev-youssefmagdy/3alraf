<x-layouts.admin :title="__('erp.cards.audit')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.audit')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    @php $in = 'form-control mt-1 !w-auto'; @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_acc.audit.action') }}<select name="action" class="{{ $in }}"><option value="">{{ __('erp_acc.audit.all') }}</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected($q['action'] === $a)>{{ $a }}</option>@endforeach</select></label>
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $q['from'] }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $q['to'] }}" class="{{ $in }}"></label>
            <x-ui.button type="submit">{{ __('erp.f.filter') }}</x-ui.button>
        </form>
    </x-ui.card>
    <x-ui.card>
        <x-ui.table data-audit>
            <thead><tr><th>{{ __('erp_acc.audit.when') }}</th><th>{{ __('erp_acc.audit.action') }}</th><th>{{ __('erp_acc.audit.subject') }}</th><th>{{ __('erp_acc.audit.user') }}</th><th>{{ __('erp_acc.audit.detail') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                @php $meta = $l->meta ? json_encode($l->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''; @endphp
                <tr class="align-top">
                    <td data-label="" class="whitespace-nowrap tabular-nums">{{ $l->created_at?->format('Y-m-d H:i') }}</td>
                    <td data-label="{{ __('erp_acc.audit.action') }}" class="font-mono">{{ $l->action }}</td>
                    <td data-label="{{ __('erp_acc.audit.subject') }}">{{ $l->subject_type }} {{ $l->subject_id ? '#'.$l->subject_id : '' }}</td>
                    <td data-label="{{ __('erp_acc.audit.user') }}">{{ $l->user_id ? ($users[$l->user_id] ?? '#'.$l->user_id) : '-' }}</td>
                    <td data-label="{{ __('erp_acc.audit.detail') }}" class="max-w-md truncate" title="{{ $meta }}" dir="ltr">{{ $meta }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('erp_ext.f.none') }}</td></tr>
            @endforelse
            </tbody>
        </x-ui.table>
        <div class="mt-4">{{ $logs->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
