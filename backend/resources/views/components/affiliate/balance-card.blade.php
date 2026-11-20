{{-- Kept for existing pages: the implementation is <x-ui.balance-card>. --}}
@props(['amount', 'label', 'split' => null, 'payoutUrl' => null, 'payoutLabel' => null])
<x-ui.balance-card :amount="$amount" :label="$label" :split="$split" :payoutUrl="$payoutUrl" :payoutLabel="$payoutLabel" {{ $attributes }} />
