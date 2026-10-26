<x-layouts.admin guard="tenant" :title="$customer->name">
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('orders.fields.customer')">
            <div class="space-y-1 text-sm"><div>{{ $customer->email }}</div><div>{{ $customer->phone }}</div><div>{!! $customer->is_active ? '<span class="badge badge-success">'.e(__('admin.active')).'</span>' : '<span class="badge badge-muted">'.e(__('admin.inactive')).'</span>' !!}</div></div>
            <h3 class="mt-4 text-sm font-semibold">{{ __('orders.addresses') }}</h3>
            @forelse ($customer->addresses as $a)
                <div class="mt-2 rounded-lg border border-slate-200 p-2 text-sm">{{ $a->name }} - {{ $a->phone }}<br>{{ $a->city?->name }} - {{ $a->address }}@if ($a->is_default) <span class="badge badge-success">{{ __('admin.default') }}</span>@endif</div>
            @empty
                <p class="mt-2 text-sm text-slate-500">{{ __('admin.no_data') }}</p>
            @endforelse
        </x-card>
        <x-card class="lg:col-span-2" :title="__('orders.orders')">
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($customer->orders as $o)
                        <tr class="border-t border-slate-100 first:border-0"><td class="py-2"><a class="font-semibold text-brand-600" href="{{ route('tenant.orders.show', $o->id) }}">{{ $o->number }}</a></td><td>{{ $o->placed_at?->format('Y-m-d') }}</td><td>{{ number_format($o->total, 2) }}</td><td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span></td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
        @isset($activities)<div class="lg:col-span-3">@include('tenant._activity', ['activities' => $activities])</div>@endisset
    </div>
</x-layouts.admin>
