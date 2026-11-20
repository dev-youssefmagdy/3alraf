<x-layouts.admin :title="__('erp.cards.journals')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.journals')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    <x-ui.card>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
            <input name="q" value="{{ $flt['q'] }}" placeholder="{{ __('erp.f.search') }}" class="form-control">
            <select name="status" class="form-control"><option value="">{{ __('erp.f.all') }}</option>@foreach (['draft', 'posted', 'reversed'] as $s)<option value="{{ $s }}" @selected($flt['status'] === $s)>{{ __('erp.statuses.'.$s) }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ $flt['from'] }}" class="form-control"><input type="date" name="to" value="{{ $flt['to'] }}" class="form-control">
            <x-ui.button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-ui.button>
            @can('erp.journals.create')<x-ui.button :href="route('admin.erp.journals.create')" class="ms-auto">{{ __('erp.j.new') }}</x-ui.button>@endcan
        </form>
        <x-ui.table>
            <thead><tr><th>{{ __('erp.f.number') }}</th><th>{{ __('erp.f.date') }}</th><th>{{ __('erp.f.description') }}</th><th>{{ __('erp.f.type') }}</th><th>{{ __('erp.f.status') }}</th><th class="text-end">{{ __('erp.f.total') }}</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr><td data-label="">@include('admin.erp._jlink', ['id' => $e->id, 'no' => $e->number])</td><td data-label="{{ __('erp.f.date') }}">{{ $e->entry_date->format('Y-m-d') }}</td><td data-label="{{ __('erp.f.description') }}">{{ \Illuminate\Support\Str::limit($e->description, 70) }}</td><td data-label="{{ __('erp.f.type') }}">{{ __('erp.entry_types.'.$e->type) }}</td><td data-label="{{ __('erp.f.status') }}">{{ __('erp.statuses.'.$e->status) }}</td><td data-label="{{ __('erp.f.total') }}" class="text-end tabular-nums">{{ number_format((float) $e->total, 2) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center">{{ __('erp.j.none') }}</td></tr>
            @endforelse
            </tbody>
        </x-ui.table>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
