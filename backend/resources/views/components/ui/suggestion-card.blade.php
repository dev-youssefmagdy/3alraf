{{-- SuggestionCard (Figma SuggestionCard, marketer): AI suggestion with status + one action. suggestion = model or array: title, type, summary, created_at; status draft | pending | approved. --}}
@props(['suggestion', 'status' => null, 'href' => null, 'action' => null])
@php
    $status ??= data_get($suggestion, 'status'); $status = is_object($status) ? ($status->value ?? (string) $status) : (string) $status;
    $tone = ['approved' => 'success', 'applied' => 'success', 'pending' => 'warning', 'pending_review' => 'warning', 'rejected' => 'danger'][$status] ?? 'neutral';
@endphp
<article {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs']) }}>
    <div class="flex items-start justify-between gap-2"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-panel-accent-soft text-panel-accent-text"><x-ui.icon name="sparkles" /></span><x-ui.badge :tone="$tone" dot>{{ $status ? str_replace('_', ' ', $status) : '' }}</x-ui.badge></div>
    <div class="min-w-0"><h3 class="text-sm font-bold text-panel-text">{{ data_get($suggestion, 'title') }}</h3>@if (data_get($suggestion, 'summary'))<p class="mt-1 line-clamp-2 text-sm text-panel-text-muted">{{ data_get($suggestion, 'summary') }}</p>@endif</div>
    @if ($href && $action)<x-ui.button :href="$href" variant="secondary" size="sm" class="self-start">{{ $action }}</x-ui.button>@endif
    {{ $slot }}
</article>
