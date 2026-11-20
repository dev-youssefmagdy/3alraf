@php
    $icons = ['brand' => 'wallet', 'mint' => 'check-circle', 'sun' => 'receipt', 'violet' => 'chart', 'slate' => 'box', 'emerald' => 'wallet', 'panel' => 'chart'];
@endphp
<div class="mb-4 grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
    @foreach ($cards as $c)
        <x-ui.stat-card :label="$c['label']" :value="$c['value']" :tone="$c['tone']" :icon="$icons[$c['tone']] ?? 'chart'" :hint="$c['hint'] ?? null" />
    @endforeach
</div>
