{{-- BottomNav (Figma BottomNav): below 768. items = [['label','href','icon','active','badge','fab' => bool (centre quick action), 'menu' => bool (opens the drawer)]].
     Staff: 5 slots incl. the centre quick action; affiliate: 4 tabs. 64px tall, 44px+ touch targets. --}}
@props(['items' => [], 'label' => ''])
<nav class="fixed inset-x-0 bottom-0 z-30 grid h-[calc(var(--size-bottom-nav)+env(safe-area-inset-bottom))] border-t border-panel-border bg-panel-surface/95 px-1 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))" aria-label="{{ $label }}" data-bottom-nav>
    @foreach ($items as $n)
        @if ($n['fab'] ?? false)
            <a href="{{ $n['href'] }}" class="relative flex flex-col items-center justify-center gap-0.5 text-[11px] font-semibold text-panel-text-muted" aria-label="{{ $n['label'] }}"><span class="-mt-6 grid size-14 place-items-center rounded-full bg-panel-accent text-panel-on-accent shadow-panel-md ring-4 ring-panel-surface"><x-ui.icon :name="$n['icon']" class="size-6" /></span>{{ $n['label'] }}</a>
        @else
            @php($tag = ($n['menu'] ?? false) ? 'button' : 'a')
            <{{ $tag }} @if ($tag === 'a') href="{{ $n['href'] }}" @else type="button" data-sidebar-toggle @endif class="relative flex min-h-11 flex-col items-center justify-center gap-0.5 text-[11px] {{ ($n['active'] ?? false) ? 'font-bold text-panel-accent-text' : 'font-medium text-panel-text-muted' }}" @if ($n['active'] ?? false) aria-current="page" @endif>
                <span class="relative grid h-7 w-14 place-items-center rounded-full {{ ($n['active'] ?? false) ? 'bg-panel-accent-soft' : '' }}"><x-ui.icon :name="$n['icon']" class="size-[22px]" />@if (($n['badge'] ?? 0) > 0)<span class="absolute end-2 -top-0.5 min-w-4 rounded-full bg-panel-danger-solid px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $n['badge'] > 99 ? '99+' : $n['badge'] }}</span>@endif</span>
                <span class="max-w-full truncate px-1">{{ $n['label'] }}</span>
            </{{ $tag }}>
        @endif
    @endforeach
</nav>
