<x-layouts.admin :title="__('erp.cards.settings')[0]">
    @include('admin.erp._flash')
    @php $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    <x-card>
        <form method="POST" action="{{ route('admin.erp.settings.update') }}" class="grid gap-4 sm:grid-cols-2 text-sm">@csrf @method('PUT')
            <label>{{ __('erp_dist.s.reserve_percent') }}<input name="reserve_percent" type="number" step="0.01" min="0" max="100" required value="{{ old('reserve_percent', $s['reserve_percent']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.buffer') }}<input name="distribution_min_cash_buffer" type="number" step="0.01" min="0" required value="{{ old('distribution_min_cash_buffer', $s['distribution_min_cash_buffer']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.tax_rate') }}<input name="tax_rate" type="number" step="0.01" min="0" max="100" required value="{{ old('tax_rate', $s['tax_rate']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.tax_account') }}<select name="tax_account_id" class="{{ $in }}"><option value="">-</option>@foreach ($expenseAccounts as $a)<option value="{{ $a->id }}" @selected((int) $s['tax_account_id'] === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>
            <label class="sm:col-span-2"><input type="checkbox" name="distribution_requires_second_approver" value="1" @checked($s['distribution_requires_second_approver'])> {{ __('erp_dist.s.second') }}</label>
            <label class="sm:col-span-2"><input type="checkbox" name="distribute_prior_retained" value="1" @checked($s['distribute_prior_retained'])> {{ __('erp_dist.s.prior') }}</label>
            <label>{{ __('erp_acc.s.recognition') }}<select name="revenue_recognition" class="{{ $in }}">@foreach (\App\Services\Erp\Posting\RevenueRecognition::POLICIES as $p)<option value="{{ $p }}" @selected(($s['revenue_recognition'] ?? 'shipped') === $p)>{{ __('erp_acc.s.rec.'.$p) }}</option>@endforeach</select></label>
            <label>{{ __('erp_acc.s.landed') }}<select name="landed_costs" class="{{ $in }}">@foreach (['expense', 'capitalise'] as $p)<option value="{{ $p }}" @selected(($s['landed_costs'] ?? 'expense') === $p)>{{ __('erp_acc.s.land.'.$p) }}</option>@endforeach</select></label>
            <label class="sm:col-span-2"><input type="checkbox" name="journal_requires_second_poster" value="1" @checked($s['journal_requires_second_poster'] ?? false)> {{ __('erp_acc.s.second_poster') }}</label>
            <label class="sm:col-span-2">{{ __('erp_dist.s.costing') }}<textarea name="costing_policy" rows="2" maxlength="1000" class="{{ $in }}">{{ old('costing_policy', $s['costing_policy']) }}</textarea></label>
            <label class="sm:col-span-2">{{ __('erp_dist.s.revenue') }}<textarea name="revenue_policy" rows="2" maxlength="1000" class="{{ $in }}">{{ old('revenue_policy', $s['revenue_policy']) }}</textarea></label>
            <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div>
        </form>
    </x-card>
    <x-card :title="__('erp_audit.rates.title')" class="mt-5">
        <p class="mb-3 text-xs text-slate-500">{{ __('erp_audit.rates.hint') }}</p>
        <form method="POST" action="{{ route('admin.erp.settings.rates.store') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">@csrf
            <label>{{ __('erp_audit.rates.from') }}<input name="from_currency" maxlength="3" required value="{{ old('from_currency', setting('currency_code')) }}" class="{{ $in }} w-24 uppercase"></label>
            <label>{{ __('erp_audit.rates.to') }}<input name="to_currency" maxlength="3" required value="{{ old('to_currency') }}" class="{{ $in }} w-24 uppercase"></label>
            <label>{{ __('erp_audit.rates.rate') }}<input name="rate" type="number" step="0.00000001" min="0" required value="{{ old('rate') }}" class="{{ $in }} w-36"></label>
            <label>{{ __('erp_audit.rates.date') }}<input name="effective_date" type="date" required value="{{ old('effective_date', now()->toDateString()) }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp_ext.f.save') }}</x-button>
        </form>
        @if ($errors->hasAny(['from_currency', 'to_currency', 'rate', 'effective_date']))<p class="mb-3 text-xs text-red-600">{{ $errors->first('from_currency') ?: $errors->first('to_currency') ?: $errors->first('rate') ?: $errors->first('effective_date') }}</p>@endif
        <table class="w-full text-sm"><tbody>@forelse ($rates as $rt)<tr class="border-b border-slate-100"><td class="py-1">1 {{ $rt->from_currency }} = {{ rtrim(rtrim((string) $rt->rate, '0'), '.') }} {{ $rt->to_currency }}</td><td>{{ $rt->effective_date->format('Y-m-d') }}</td><td class="text-end"><form method="POST" action="{{ route('admin.erp.settings.rates.destroy', $rt->id) }}">@csrf @method('DELETE')<button class="text-red-600" aria-label="{{ __('admin.delete') }}">&times;</button></form></td></tr>@empty<tr><td class="py-1 text-slate-400">{{ __('admin.no_data') }}</td></tr>@endforelse</tbody></table>
    </x-card>
</x-layouts.admin>
