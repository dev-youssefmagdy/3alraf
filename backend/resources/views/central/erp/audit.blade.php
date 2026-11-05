<x-layouts.admin guard="central" :title="__('erp_central.cards.audit')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.action') }}<select name="action" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($actions as $a)<option @selected(($q['action'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.user') }}<input name="user_id" value="{{ $q['user_id'] ?? '' }}" class="{{ $in }} w-20"></label>
            <label>{{ __('erp_central.f.from') }}<input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.to') }}<input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-audit>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.date') }}</th><th class="text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp_central.col.action') }}</th><th class="text-start">{{ __('erp_central.col.subject') }}</th><th class="text-start">{{ __('erp_central.col.user') }}</th><th class="text-start">{{ __('erp_central.col.detail') }}</th></tr></thead>
            <tbody>@forelse ($logs as $l)<tr class="border-b border-slate-100"><td class="py-1.5 whitespace-nowrap">{{ $l->created_at }}</td><td>{{ $l->tenant_name }}</td><td class="font-mono text-xs">{{ $l->action }}</td><td>{{ $l->subject_type }} {{ $l->subject_id ? '#'.$l->subject_id : '' }}</td><td>{{ $l->user_id ?? '-' }}</td><td class="max-w-xs truncate text-xs text-slate-500" title="{{ $l->meta }}">{{ $l->meta }}</td></tr>@empty<tr><td colspan="6" class="py-4 text-slate-500">{{ __('erp_central.empty') }}</td></tr>@endforelse</tbody>
        </table></div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </x-card>
</x-layouts.admin>
