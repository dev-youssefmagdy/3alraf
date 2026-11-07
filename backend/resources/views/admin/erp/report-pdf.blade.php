<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
    h1 { font-size: 16px; margin: 0 0 4px; } h3 { font-size: 12px; margin: 12px 0 4px; }
    table { width: 100%; border-collapse: collapse; } th, td { padding: 3px 5px; border-bottom: 1px solid #ddd; text-align: {{ $rtl ? 'right' : 'left' }}; }
    th { background: #f1f5f9; } .n { text-align: {{ $rtl ? 'left' : 'right' }}; } .b { font-weight: bold; } .muted { color: #666; }
</style></head>
<body>
<h1>{{ $t($report['title']) }}</h1>
<div class="muted">{{ $t($tenant->name ?? '') }} @if ($f) - {{ $f->from }} / {{ $f->to }} @endif</div>
@foreach ($report['cards'] as $c)<div>{{ $t($c['label']) }}: <b>{{ $c['type'] === 'int' ? number_format((float) $c['value']) : number_format((float) $c['value'], 2) }}</b></div>@endforeach
@foreach ($report['tables'] as $tb)
    @if (! empty($tb['title']))<h3>{{ $t($tb['title']) }}</h3>@endif
    <table><thead><tr>@foreach ($tb['columns'] as $c)<th class="{{ in_array($c['type'], ['money', 'int', 'pct']) ? 'n' : '' }}">{{ $t($c['label']) }}</th>@endforeach</tr></thead><tbody>
    @foreach ($tb['rows'] as $row)
        <tr class="{{ ! empty($row['_total']) || ! empty($row['_head']) ? 'b' : '' }}">@foreach ($tb['columns'] as $c)@php $v = $row[$c['key']] ?? ''; @endphp<td class="{{ in_array($c['type'], ['money', 'int', 'pct']) ? 'n' : '' }}">{{ $v === '' ? '' : ($c['type'] === 'money' ? number_format((float) $v, 2) : ($c['type'] === 'pct' ? number_format((float) $v, 2).'%' : $t($v))) }}</td>@endforeach</tr>
    @endforeach
    </tbody></table>
@endforeach
@foreach ($report['notes'] as $n)<p class="muted">{{ $t($n) }}</p>@endforeach
</body></html>
