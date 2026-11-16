{{-- Available balance (Figma Affiliate/Balance card): accent card + sun payout button when something can be withdrawn, muted card otherwise. --}}
@props(['amount', 'label', 'split' => null, 'payoutUrl' => null, 'payoutLabel' => null])
@php($on = (float) str_replace(',', '', $amount) > 0)
<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col rounded-3xl p-5 '.($on ? 'bg-panel text-white' : 'bg-slate-100 text-slate-900')]) }} data-balance>
    <p class="text-sm font-bold">{{ $label }}</p>
    <p class="mt-1 font-display text-[30px]/9 font-extrabold tabular-nums [overflow-wrap:anywhere]">{{ $amount }}</p>
    @if ($split)<p class="mt-1 text-xs {{ $on ? 'text-white/90' : 'text-slate-600' }}">{{ $split }}</p>@endif
    @if ($on && $payoutUrl)<button type="button" data-post-action="{{ $payoutUrl }}" class="btn-sun mt-3 self-start">{{ $payoutLabel }}</button>@endif
</div>
