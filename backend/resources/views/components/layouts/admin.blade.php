{{-- Admin shell (guard `admin`): <x-panel.shell panel="admin">. Menu data: App\Support\Panels\AdminNav (4 shortcuts + 10 groups, spec section 6). --}}
@props(['title' => '', 'header' => null])
@php
    $user = auth('admin')->user();
    $nav = \App\Support\Panels\AdminNav::build($user);
    $flat = collect($nav)->flatMap(fn ($n) => ($n['group'] ?? false) ? $n['items'] : [$n])->keyBy('route');
    $pick = fn ($r, $icon, $extra = []) => $flat->has($r) ? ['label' => $flat[$r]['label'], 'href' => $flat[$r]['href'], 'icon' => $icon, 'active' => $flat[$r]['active'], 'badge' => $flat[$r]['badge']] + $extra : null;
    // bottom nav (Figma BottomNav, staff): 5 slots, centre = quick action (POS when allowed, else Orders)
    $bottom = array_values(array_filter([
        $pick('admin.dashboard', 'home'), $pick('admin.orders.index', 'cart'),
        $pick('admin.pos.index', 'pos', ['fab' => true]) ?? $pick('admin.reports.index', 'chart', ['fab' => true]),
        $pick('admin.products.index', 'box'),
        ['label' => __('admin.menu'), 'href' => '#', 'icon' => 'menu', 'active' => false, 'menu' => true],
    ]));
    $quick = array_values(array_filter([
        $flat->has('admin.products.index') && $user?->can('products.create') ? ['label' => __('admin.products').' +', 'href' => route('admin.products.create'), 'icon' => 'plus'] : null,
        $flat->has('admin.pos.index') ? ['label' => __('pos.title'), 'href' => route('admin.pos.index'), 'icon' => 'pos'] : null,
    ]));
    $locales = \App\Models\Language::activeList()->reject(fn ($l) => $l->code === app()->getLocale())
        ->map(fn ($l) => ['code' => $l->code, 'name' => $l->native_name, 'href' => route('admin.locale', $l->code)])->values()->all();
@endphp
<x-panel.shell panel="admin" :title="$title" :header="$header" :panelName="__('admin.admin_panel')" :home="route('admin.dashboard')" :logout="route('admin.logout')"
    :nav="$nav" :locales="$locales" :user="$user" :search="__('admin.cmdk_placeholder')" :bottom="$bottom" :quick="$quick">
    <x-slot:headerEnd>
                    {{-- notification bell: polls (60s when realtime is on, else 15s) + Echo push (resources/js/lib/orders-form.js) --}}
                    <div class="relative">
                        <x-ui.icon-button icon="bell" :label="__('admin.notifications')" data-dropdown-toggle="notif-menu" aria-haspopup="true">
                        </x-ui.icon-button>
                        <span id="notif-count" class="pointer-events-none absolute end-1 top-1 hidden min-w-4 rounded-full bg-panel-danger-solid px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
                        <div id="notif-menu" data-dropdown class="fixed inset-x-3 top-16 z-50 hidden rounded-2xl border border-panel-border bg-panel-surface-raised shadow-panel-lg md:absolute md:inset-x-auto md:end-0 md:top-full md:mt-2 md:w-80"
                            data-url="{{ route('admin.notifications.index') }}" data-read-url="{{ route('admin.notifications.read', '__ID__') }}" data-read-all-url="{{ route('admin.notifications.read-all') }}"
                            @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
                                data-channel="App.Models.Admin.{{ $user->id }}" data-auth-url="{{ route('admin.broadcasting.auth') }}"
                                data-ws-key="{{ config('broadcasting.connections.reverb.key') }}" data-ws-host="{{ config('broadcasting.connections.reverb.options.host') }}"
                                data-ws-port="{{ config('broadcasting.connections.reverb.options.port') }}" data-ws-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
                            @endif
                            data-empty="{{ __('admin.no_notifications') }}">
                            <div class="flex items-center justify-between border-b border-panel-border px-3 py-2 text-sm font-semibold text-panel-text">{{ __('admin.notifications') }}
                                <a id="notif-read-all" href="#" class="text-xs font-semibold text-panel-accent-text">{{ __('orders.mark_all_read') }}</a>
                            </div>
                            <div id="notif-list" class="max-h-80 overflow-y-auto"><p class="p-3 text-sm text-panel-text-subtle">{{ __('admin.no_notifications') }}</p></div>
                        </div>
                    </div>
    </x-slot:headerEnd>
    {{ $slot }}
</x-panel.shell>
