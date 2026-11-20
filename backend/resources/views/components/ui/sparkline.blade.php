{{-- Sparkline (Figma Sparkline): tiny trend line, no axes. points = list of numbers; trend up|down colours it (never the only signal: pair it with a number). --}}
@props(['points' => [], 'trend' => 'up', 'width' => 96, 'height' => 28])
@php
    $pts = array_values(array_map('floatval', $points));
    $n = count($pts);
    $d = '';
    if ($n > 1) {
        [$min, $max] = [min($pts), max($pts)];
        $span = ($max - $min) ?: 1;
        $coords = [];
        foreach ($pts as $i => $v) { $coords[] = round($i / ($n - 1) * $width, 1).','.round($height - 3 - (($v - $min) / $span) * ($height - 6), 1); }
        $d = implode(' ', $coords);
    }
    $color = $trend === 'down' ? 'text-panel-danger-solid' : ($trend === 'flat' ? 'text-panel-text-subtle' : 'text-panel-success-solid');
@endphp
@if ($d)<svg {{ $attributes->merge(['class' => "$color shrink-0"]) }} width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" fill="none" aria-hidden="true" dir="ltr"><polyline points="{{ $d }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>@endif
