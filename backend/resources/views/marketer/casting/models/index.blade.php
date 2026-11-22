@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $tone = ['approved' => 'success', 'draft' => 'neutral', 'archived' => 'warning'];
@endphp
<x-layouts.marketer :title="$t('models.title')">
    <x-slot:header>
        <x-ui.page-header :title="$t('models.title')" :breadcrumbs="[[$t('title'), route('marketer.casting.index')]]">
            @can('ai_casting.create')<a class="btn btn-primary btn-md" href="{{ route('marketer.casting.models.create') }}">{{ $t('models.new') }}</a>@endcan
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2 text-sm">
            <a class="btn btn-sm {{ $status ? 'btn-secondary' : 'btn-primary' }}" href="{{ route('marketer.casting.models.index') }}">{{ $t('models.all') }}</a>
            @foreach (['approved', 'draft', 'archived'] as $st)<a class="btn btn-sm {{ $status === $st ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('marketer.casting.models.index', ['status' => $st]) }}">{{ $t('status.'.$st) }}</a>@endforeach
        </div>
        @if ($models->isEmpty())
            <x-ui.empty-state :title="$t('models.title')" :description="$t('models.empty')" />
        @else
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($models as $m)
                    @php($face = $m->references->first())
                    <a href="{{ route('marketer.casting.models.show', $m->id) }}" class="overflow-hidden rounded-2xl border border-panel-border bg-panel-surface shadow-panel-xs transition hover:border-panel-accent">
                        <div class="aspect-[3/4] bg-panel-surface-sunken">@if ($face)<img loading="lazy" class="size-full object-cover" alt="{{ $m->name }}" src="{{ route('marketer.casting.models.references.image', [$m->id, $face->id]) }}">@endif</div>
                        <div class="p-3"><p class="truncate font-semibold text-panel-text">{{ $m->name }}</p><div class="mt-1 flex items-center justify-between"><x-ui.badge :tone="$tone[$m->status] ?? 'neutral'" dot size="sm">{{ $t('status.'.$m->status) }}</x-ui.badge><span class="text-xs text-panel-text-subtle">{{ $m->usages_count }}×</span></div></div>
                    </a>
                @endforeach
            </div>
            {{ $models->links() }}
        @endif
    </div>
</x-layouts.marketer>
