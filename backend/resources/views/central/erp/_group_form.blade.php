@php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; @endphp
<x-card class="mb-5">
    <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
        <label>{{ __('erp_central.f.from') }}<input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="{{ $in }}"></label>
        <label>{{ __('erp_central.f.to') }}<input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="{{ $in }}"></label>
        <label>{{ __('erp_central.f.convert_to') }}<select name="convert_to" class="{{ $in }}"><option value="">{{ __('erp_central.f.no_convert') }}</option>@foreach ($currencies as $c)<option @selected(strtoupper($q['convert_to'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></label>
        <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
    </form>
    <p class="mt-2 text-xs text-slate-500">{{ __('erp_central.convert_hint') }}</p>
</x-card>
