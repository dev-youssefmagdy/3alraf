@php $fmt = fn ($v, $t) => $v === '' || $v === null ? '' : ($t === 'money' ? number_format((float) $v, 2) : ($t === 'pct' ? number_format((float) $v, 2).'%' : ($t === 'int' ? number_format((float) $v) : $v))); $num = fn ($c) => in_array($c['type'], ['money', 'int', 'pct']); @endphp
@if (! empty($t['title']))<x-ui.heading :level="3" class="mb-2 mt-4">{{ $t['title'] }}</x-ui.heading>@endif
<x-ui.table>
    <thead><tr>@foreach ($t['columns'] as $c)<th class="{{ $num($c) ? '!text-end' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($t['rows'] as $row)
        <tr class="{{ ! empty($row['_head']) ? '[&>td]:!bg-panel-surface-sunken font-semibold' : '' }} {{ ! empty($row['_total']) ? 'font-semibold' : '' }}">
            @foreach ($t['columns'] as $c)<td data-label="{{ $loop->first ? '' : $c['label'] }}" class="{{ $num($c) ? 'text-end tabular-nums' : '' }}">{{ $fmt($row[$c['key']] ?? '', $c['type']) }}</td>@endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($t['columns']) }}" class="py-4 text-panel-text-subtle">{{ __('erp_rep.empty') }}</td></tr>
    @endforelse
    </tbody>
</x-ui.table>
