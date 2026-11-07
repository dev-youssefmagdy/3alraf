@php $fmt = fn ($v, $t) => $v === '' || $v === null ? '' : ($t === 'money' ? number_format((float) $v, 2) : ($t === 'pct' ? number_format((float) $v, 2).'%' : ($t === 'int' ? number_format((float) $v) : $v))); @endphp
@if (! empty($t['title']))<h3 class="mb-2 mt-4 font-semibold">{{ $t['title'] }}</h3>@endif
<div class="overflow-x-auto"><table class="w-full text-sm">
    <thead><tr class="border-b text-slate-500">@foreach ($t['columns'] as $c)<th class="py-2 {{ in_array($c['type'], ['money', 'int', 'pct']) ? 'text-end' : 'text-start' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($t['rows'] as $row)
        <tr class="border-b border-slate-100 {{ ! empty($row['_head']) ? 'bg-slate-50 font-semibold' : '' }} {{ ! empty($row['_total']) ? 'font-semibold' : '' }}">
            @foreach ($t['columns'] as $c)<td class="py-1.5 {{ in_array($c['type'], ['money', 'int', 'pct']) ? 'text-end tabular-nums' : '' }}">{{ $fmt($row[$c['key']] ?? '', $c['type']) }}</td>@endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($t['columns']) }}" class="py-4 text-slate-500">{{ __('erp_rep.empty') }}</td></tr>
    @endforelse
    </tbody>
</table></div>
