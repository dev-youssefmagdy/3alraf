<x-layouts.admin guard="tenant" :title="$a->number">
    <div class="space-y-4">
        <x-card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-slate-500">{{ __('ops.fields.branch') }}</div><div class="font-semibold">{{ $a->branch?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.type') }}</div><div class="font-semibold">{{ $a->type->label() }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.reason') }}</div><div class="font-semibold">{{ $a->reason->label() }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ $a->created_at->format('Y-m-d H:i') }}</div></div>
            </div>
            @if ($a->note)<p class="mt-3 text-sm text-slate-600">{{ $a->note }}</p>@endif
        </x-card>
        <x-card :title="__('ops.items_section')">
            <table class="w-full text-sm">
                <thead class="text-slate-500"><tr><th class="py-2 text-start">{{ __('ops.fields.item') }}</th><th class="text-start">{{ __('ops.fields.quantity') }}</th><th class="text-start">{{ __('ops.fields.unit_cost') }}</th></tr></thead>
                <tbody>
                    @foreach ($a->items as $i)
                        <tr class="border-t border-slate-100"><td class="py-2">{{ $i->label() }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->unit_cost, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>
</x-layouts.admin>
