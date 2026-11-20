{{-- Tabs (Figma Tabs): style underline (page sections) | pill (list filtering, with counts). tabs = [['label', 'href', 'count' => ?, 'key' => ?]], active = key (or label). Links, so they work without JS.
     panels=true: the tabs switch panels on the same page (no navigation): buttons carry data-ptab="key"; the panels are any element with data-ptab-panel="key" data-ptab-for="{id}"
     (they may sit on both sides of a <form>). The first tab (or #tab-key in the URL) is shown, the rest are hidden; a field error in a hidden panel switches to it (ajax-form.js). --}}
@props(['tabs' => [], 'active' => null, 'style' => 'underline', 'panels' => false, 'id' => null])
<nav {{ $attributes->merge(['class' => 'flex gap-1 overflow-x-auto [scrollbar-width:none] '.($style === 'pill' ? 'py-1' : 'border-b border-panel-border')]) }} role="tablist" @if ($panels) data-ptabs="{{ $id }}" @endif>
    @foreach ($tabs as $t)
        @php($on = $panels ? $loop->first : (string) ($t['key'] ?? $t['label']) === (string) $active)
        @php($cls = 'inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap text-sm font-semibold transition '.($style === 'pill'
                ? 'rounded-full px-4 '.($on ? 'bg-panel-accent text-panel-on-accent' : 'bg-panel-surface-sunken text-panel-text-muted hover:text-panel-text')
                : '-mb-px border-b-2 px-4 '.($on ? 'border-panel-accent text-panel-accent-text' : 'border-transparent text-panel-text-muted hover:text-panel-text')))
        @if ($panels)
            <button type="button" role="tab" data-ptab="{{ $t['key'] ?? $t['label'] }}" aria-selected="{{ $on ? 'true' : 'false' }}" class="{{ $cls }}">
                {{ $t['label'] }}@isset($t['count'])<span class="rounded-full bg-panel-surface-sunken px-2 text-xs text-panel-text-muted">{{ $t['count'] }}</span>@endisset
            </button>
        @else
            <a href="{{ $t['href'] ?? '#' }}" role="tab" aria-selected="{{ $on ? 'true' : 'false' }}" @if ($on) aria-current="page" @endif class="{{ $cls }}">
                {{ $t['label'] }}@isset($t['count'])<span class="rounded-full px-2 text-xs {{ $on && $style === 'pill' ? 'bg-white/25' : 'bg-panel-surface-sunken text-panel-text-muted' }}">{{ $t['count'] }}</span>@endisset
            </a>
        @endif
    @endforeach
</nav>
