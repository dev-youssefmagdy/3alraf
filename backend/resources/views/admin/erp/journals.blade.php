<x-layouts.admin :title="__('erp.cards.journals')[0]">
    @include('admin.erp._flash')
    <x-card>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
            <input name="q" value="{{ $flt['q'] }}" placeholder="{{ __('erp.f.search') }}" class="rounded border border-slate-300 px-2 py-1.5">
            <select name="status" class="rounded border border-slate-300 px-2 py-1.5"><option value="">{{ __('erp.f.all') }}</option>@foreach (['draft', 'posted', 'reversed'] as $s)<option value="{{ $s }}" @selected($flt['status'] === $s)>{{ __('erp.statuses.'.$s) }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ $flt['from'] }}" class="rounded border border-slate-300 px-2 py-1.5"><input type="date" name="to" value="{{ $flt['to'] }}" class="rounded border border-slate-300 px-2 py-1.5">
            <x-button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-button>
            @can('erp.journals.create')<x-button :href="route('admin.erp.journals.create')" class="ms-auto">{{ __('erp.j.new') }}</x-button>@endcan
        </form>
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp.f.number') }}</th><th class="text-start">{{ __('erp.f.date') }}</th><th class="text-start">{{ __('erp.f.description') }}</th><th class="text-start">{{ __('erp.f.type') }}</th><th class="text-start">{{ __('erp.f.status') }}</th><th class="text-end">{{ __('erp.f.total') }}</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr class="border-b border-slate-100"><td class="py-1.5">@include('admin.erp._jlink', ['id' => $e->id, 'no' => $e->number])</td><td>{{ $e->entry_date->format('Y-m-d') }}</td><td>{{ \Illuminate\Support\Str::limit($e->description, 70) }}</td><td>{{ __('erp.entry_types.'.$e->type) }}</td><td>{{ __('erp.statuses.'.$e->status) }}</td><td class="text-end tabular-nums">{{ number_format((float) $e->total, 2) }}</td></tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('erp.j.none') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-layouts.admin>
