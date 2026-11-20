{{-- Audit timeline: $activities (Spatie Activity rows) --}}
<x-ui.card :title="__('orders.activity')">
    <ol class="space-y-2 text-sm">
        @forelse ($activities as $a)
            @php
                $p = $a->properties;
                $attrs = (array) ($p['attributes'] ?? []);
                $old = (array) ($p['old'] ?? []);
            @endphp
            <li class="border-s-2 border-panel-border ps-3">
                <div class="font-medium">{{ $a->log_name }} &middot; {{ $a->description }}
                    <span class="text-xs font-normal text-panel-text-subtle">{{ class_basename((string) $a->subject_type) }} #{{ $a->subject_id }}</span></div>
                <div class="text-xs text-panel-text-subtle">{{ $a->created_at?->format('Y-m-d H:i:s') }} - {{ $a->causer?->name ?? __('orders.activity_system') }}</div>
                @if ($a->log_name === 'notification')
                    <div class="text-xs text-panel-text-muted">{{ $p['audience'] ?? '' }} &rarr; {{ $p['to'] ?? '' }} ({{ $p['channel'] ?? '' }})</div>
                @else
                    @foreach (array_slice($attrs, 0, 6, true) as $k => $v)
                        <div class="text-xs text-panel-text-muted">{{ $k }}: @isset($old[$k]){{ is_scalar($old[$k]) ? $old[$k] : json_encode($old[$k], JSON_UNESCAPED_UNICODE) }} &rarr; @endisset{{ is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</div>
                    @endforeach
                @endif
            </li>
        @empty
            <li class="text-panel-text-subtle">-</li>
        @endforelse
    </ol>
</x-ui.card>
