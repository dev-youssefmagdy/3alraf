{{-- Marketer panel shell (/marketer, guard `marketer`): <x-panel.shell panel="marketer"> (violet). Standalone: no link to /admin.
     Nav: App\Support\Marketing\MarketerNav (permission- and route-filtered); one-item groups render as a plain link. --}}
@props(['title' => '', 'header' => null])
@php
    $user = auth('marketer')->user();
    $m = fn ($k) => __('marketing.'.$k);
    $isActive = fn ($i) => $i[4] ? request()->routeIs(...(array) $i[4]) : request()->routeIs($i[0]);
    $link = fn ($i, $g) => ['route' => $i[0], 'label' => $m($i[1]), 'href' => route($i[0]), 'icon' => $i[3] ?? $g['icon'], 'active' => $isActive($i), 'badge' => 0];
    $nav = collect(\App\Support\Marketing\MarketerNav::visible($user))->map(fn ($g) => count($g['items']) === 1
        ? $link($g['items'][0], $g)
        : ['group' => true, 'label' => $m('groups.'.$g['key']), 'icon' => $g['icon'], 'items' => collect($g['items'])->map(fn ($i) => $link($i, $g))->all()])->all();
    $flat = collect($nav)->flatMap(fn ($n) => ($n['group'] ?? false) ? $n['items'] : [$n])->keyBy('route');
    $pick = fn ($r, $icon, $extra = []) => $flat->has($r) ? ['label' => $flat[$r]['label'], 'href' => $flat[$r]['href'], 'icon' => $icon, 'active' => $flat[$r]['active'], 'badge' => 0] + $extra : null;
    $bottom = array_values(array_filter([
        $pick('marketer.dashboard', 'home', ['label' => $m('groups.overview')]), $pick('marketer.analytics.index', 'chart'),
        $pick('marketer.suggestions.index', 'sparkles', ['fab' => true, 'label' => $m('groups.suggestions')]),
        $pick('marketer.banners.index', 'image') ?? $pick('marketer.media.index', 'image'),
        ['label' => __('admin.menu'), 'href' => '#', 'icon' => 'menu', 'active' => false, 'menu' => true],
    ]));
    $quick = array_values(array_filter([$pick('marketer.suggestions.index', 'sparkles'), $pick('marketer.discount-codes.index', 'percent')]));
    $locales = \App\Models\Language::activeList()->reject(fn ($l) => $l->code === app()->getLocale())
        ->map(fn ($l) => ['code' => $l->code, 'name' => $l->native_name, 'href' => route('marketer.locale', $l->code)])->values()->all();
@endphp
<x-panel.shell panel="marketer" :title="$title" :header="$header" :panelName="$m('panel')" :home="route('marketer.dashboard')" :logout="route('marketer.logout')"
    :nav="$nav" :locales="$locales" :user="$user" :search="__('admin.cmdk_placeholder')" :bottom="$bottom" :quick="$quick">
    {{ $slot }}
</x-panel.shell>
