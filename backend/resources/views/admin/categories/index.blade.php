<x-layouts.admin :title="__('admin.categories')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.categories')">
            @can('categories.create')<x-ui.button icon="plus" :href="route('admin.categories.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    <x-ui.card>
        @if ($tree->isEmpty())
            <p class="text-sm text-panel-text-subtle">{{ __('admin.no_data') }}</p>
        @endif
        <ul class="divide-y divide-panel-border" id="category-tree">
            @foreach ($tree as $c)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2" style="padding-inline-start: {{ $c->depth * 1.75 }}rem" data-category="{{ $c->id }}">
                    <span class="text-panel-text-subtle">{{ $c->depth ? '└' : '▪' }}</span>
                    @if ($c->image_url)<img src="{{ $c->image_url }}" class="size-8 rounded object-cover" alt="">@endif
                    <span class="min-w-0 break-words font-medium {{ $c->is_active ? '' : 'text-panel-text-subtle line-through' }}">{{ $c->name }}</span>
                    @if ($c->is_draft)<span class="badge badge-warn">{{ __('admin.drafts.badge') }}</span>@endif
                    <span class="badge badge-muted">{{ $c->products_count }}</span>
                    <span class="ms-auto flex flex-wrap items-center gap-2">
                        @can('categories.create')<a href="{{ route('admin.categories.create', ['parent' => $c->id]) }}" class="btn-sm btn-secondary">+ {{ __('admin.subcategory') }}</a>@endcan
                        @can('categories.edit')<a href="{{ route('admin.categories.edit', $c) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                        @can('categories.delete')<button type="button" data-delete="{{ route('admin.categories.destroy', $c) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan
                    </span>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</x-layouts.admin>
