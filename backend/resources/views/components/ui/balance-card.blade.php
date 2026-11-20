{{-- BalanceCard (Figma BalanceCard, affiliate): one hero number and one action. Accent hero card when something can be withdrawn (available), muted card when empty.
     amount is the formatted figure; payoutUrl posts via [data-post-action] (existing behaviour). --}}
@props(['amount', 'label', 'split' => null, 'payoutUrl' => null, 'payoutLabel' => null, 'paid' => null])
@php($on = (float) str_replace(',', '', $amount) > 0)
<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col rounded-2xl p-5 '.($on ? 'bg-gradient-to-br from-panel-accent to-panel-accent-deep text-panel-on-accent shadow-panel-md' : 'border border-panel-border bg-panel-surface-sunken text-panel-text')]) }} data-balance>
    <p class="text-panel-small font-semibold {{ $on ? 'opacity-90' : 'text-panel-text-muted' }}">{{ $label }}</p>
    <p class="mt-1 text-panel-stat font-bold tabular-nums [overflow-wrap:anywhere]" dir="auto">{{ $amount }}</p>
    @if ($split)<p class="mt-1 text-xs {{ $on ? 'opacity-90' : 'text-panel-text-subtle' }}">{{ $split }}</p>@endif
    @if ($paid)<p class="mt-1 text-xs {{ $on ? 'opacity-90' : 'text-panel-text-subtle' }}">{{ $paid }}</p>@endif
    @if ($on && $payoutUrl)<button type="button" data-post-action="{{ $payoutUrl }}" class="btn btn-sun mt-4 self-start">{{ $payoutLabel }}</button>@endif
</div>
