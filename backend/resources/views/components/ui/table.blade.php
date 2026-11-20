{{-- Table (static, server-rendered rows; the DataTables skin without the plugin). Slot = <thead>/<tbody>. Below 768 each row becomes a card: give every <td> a data-label
     (column title); a td with data-label="" spans the card (title cell) and a td.ui-table-actions sits at the card foot. mobile=false keeps a sideways-scrolling table. --}}
@props(['mobile' => true])
<div {{ $attributes->merge(['class' => 'min-w-0 overflow-x-auto']) }}>
    <table class="ui-table w-full text-sm" @if ($mobile) data-mobile-card @endif>{{ $slot }}</table>
</div>
