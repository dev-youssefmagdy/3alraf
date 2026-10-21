<x-layouts.admin guard="central" :title="__('admin.categories')">
    <x-card>
        <x-slot:actions>
            @can('categories.create')<x-button :href="route('central.categories.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        @if ($tree->isEmpty())
            <p class="text-sm text-slate-500">{{ __('admin.no_data') }}</p>
        @endif
        <ul class="divide-y divide-slate-100" id="category-tree">
            @foreach ($tree as $c)
                <li class="flex items-center gap-3 py-2" style="padding-inline-start: {{ $c->depth * 1.75 }}rem" data-category="{{ $c->id }}">
                    <span class="text-slate-300">{{ $c->depth ? '└' : '▪' }}</span>
                    @if ($c->image_url)<img src="{{ $c->image_url }}" class="size-8 rounded object-cover" alt="">@endif
                    <span class="font-medium {{ $c->is_active ? '' : 'text-slate-400 line-through' }}">{{ $c->name }}</span>
                    <span class="badge badge-muted">{{ $c->products_count }}</span>
                    <span class="ms-auto flex items-center gap-2">
                        @can('categories.create')<a href="{{ route('central.categories.create', ['parent' => $c->id]) }}" class="btn-sm btn-secondary">+ {{ __('admin.subcategory') }}</a>@endcan
                        @can('categories.edit')<a href="{{ route('central.categories.edit', $c) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                        @can('categories.delete')<button type="button" data-delete="{{ route('central.categories.destroy', $c) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan
                    </span>
                </li>
            @endforeach
        </ul>
    </x-card>
</x-layouts.admin>
