<x-layouts.admin guard="tenant" :title="__('admin.add_products')">
    <x-card>
        <x-slot:actions>
            <x-button variant="secondary" :href="route('tenant.catalog.index')">{{ __('admin.cancel') }}</x-button>
        </x-slot:actions>
        <form method="GET" class="mb-4 flex gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('admin.search') }}" class="form-control max-w-xs">
            <button class="btn btn-secondary">{{ __('admin.search') }}</button>
        </form>

        <form data-ajax-form data-validate-url="{{ route('tenant.catalog.bulk.validate') }}" action="{{ route('tenant.catalog.bulk.store') }}" method="POST" novalidate>
            @csrf
            <div class="mb-3 flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3" id="bulk-defaults">
                <span class="text-sm font-semibold">{{ __('admin.apply_to_selected') }}</span>
                <input type="number" step="0.01" min="0" data-default="price" placeholder="{{ __('admin.fields.price') }}" class="form-control !w-28">
                <input type="number" step="0.01" min="0" data-default="sale_price" placeholder="{{ __('admin.fields.sale_price') }}" class="form-control !w-28">
                <input type="number" step="0.01" min="0" data-default="cost" placeholder="{{ __('admin.fields.cost') }}" class="form-control !w-28">
                <button type="button" data-apply-defaults class="btn btn-secondary">{{ __('admin.apply') }}</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-start text-xs uppercase text-slate-500">
                        <th class="w-8"><input type="checkbox" data-check-all></th><th></th>
                        <th class="text-start">{{ __('admin.fields.name') }}</th><th class="text-start">{{ __('admin.fields.sku') }}</th>
                        <th class="text-start">{{ __('admin.fields.price') }}</th><th class="text-start">{{ __('admin.fields.sale_price') }}</th><th class="text-start">{{ __('admin.fields.cost') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($products as $p)
                            <tr class="border-t border-slate-100" data-row>
                                <td><input type="checkbox" name="rows[{{ $p->id }}][enabled]" value="1" data-row-check></td>
                                <td class="py-2">@if ($u = $p->primaryImageUrl())<img src="{{ $u }}" class="size-10 rounded object-cover" alt="">@endif</td>
                                <td>{{ $p->name }} @if ($p->isVariable())<span class="badge badge-muted">{{ __('admin.variants') }}</span>@endif</td>
                                <td>{{ $p->sku }}</td>
                                <td data-field><input type="number" step="0.01" min="0" name="rows[{{ $p->id }}][price]" class="form-control !w-28"></td>
                                <td data-field><input type="number" step="0.01" min="0" name="rows[{{ $p->id }}][sale_price]" class="form-control !w-28"></td>
                                <td data-field><input type="number" step="0.01" min="0" name="rows[{{ $p->id }}][cost]" class="form-control !w-28"></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-400">{{ __('admin.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $products->links() }}</div>
            <div class="mt-4 border-t border-slate-100 pt-4"><button type="submit" class="btn btn-primary">{{ __('admin.enable_selected') }}</button></div>
        </form>
    </x-card>
</x-layouts.admin>
