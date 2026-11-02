<x-layouts.admin guard="tenant" :title="__('erp.cards.settings')[0]">
    @include('tenant.erp._flash')
    @php $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    <x-card>
        <form method="POST" action="{{ route('tenant.erp.settings.update') }}" class="grid gap-4 sm:grid-cols-2 text-sm">@csrf @method('PUT')
            <label>{{ __('erp_dist.s.reserve_percent') }}<input name="reserve_percent" type="number" step="0.01" min="0" max="100" required value="{{ old('reserve_percent', $s['reserve_percent']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.buffer') }}<input name="distribution_min_cash_buffer" type="number" step="0.01" min="0" required value="{{ old('distribution_min_cash_buffer', $s['distribution_min_cash_buffer']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.tax_rate') }}<input name="tax_rate" type="number" step="0.01" min="0" max="100" required value="{{ old('tax_rate', $s['tax_rate']) }}" class="{{ $in }}"></label>
            <label>{{ __('erp_dist.s.tax_account') }}<select name="tax_account_id" class="{{ $in }}"><option value="">-</option>@foreach ($expenseAccounts as $a)<option value="{{ $a->id }}" @selected((int) $s['tax_account_id'] === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>
            <label class="sm:col-span-2"><input type="checkbox" name="distribution_requires_second_approver" value="1" @checked($s['distribution_requires_second_approver'])> {{ __('erp_dist.s.second') }}</label>
            <label class="sm:col-span-2"><input type="checkbox" name="distribute_prior_retained" value="1" @checked($s['distribute_prior_retained'])> {{ __('erp_dist.s.prior') }}</label>
            <label class="sm:col-span-2">{{ __('erp_dist.s.costing') }}<textarea name="costing_policy" rows="2" maxlength="1000" class="{{ $in }}">{{ old('costing_policy', $s['costing_policy']) }}</textarea></label>
            <label class="sm:col-span-2">{{ __('erp_dist.s.revenue') }}<textarea name="revenue_policy" rows="2" maxlength="1000" class="{{ $in }}">{{ old('revenue_policy', $s['revenue_policy']) }}</textarea></label>
            <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div>
        </form>
    </x-card>
    <x-card :title="__('erp_central.rates.title')" class="mt-5">
        <p class="mb-3 text-xs text-slate-500">{{ __('erp_central.rates.hint') }}</p>
        <form method="POST" action="{{ route('tenant.erp.settings.rates.store') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">@csrf
            <label>{{ __('erp_central.rates.from') }}<input name="from_currency" maxlength="3" required value="{{ current_tenant()->currency_code }}" class="{{ $in }} w-24 uppercase"></label>
            <label>{{ __('erp_central.rates.to') }}<input name="to_currency" maxlength="3" required class="{{ $in }} w-24 uppercase"></label>
            <label>{{ __('erp_central.rates.rate') }}<input name="rate" type="number" step="0.00000001" min="0" required class="{{ $in }} w-36"></label>
            <label>{{ __('erp_central.rates.date') }}<input name="effective_date" type="date" required value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp_ext.f.save') }}</x-button>
        </form>
        <table class="w-full text-sm"><tbody>@foreach ($rates as $rt)<tr class="border-b border-slate-100"><td class="py-1">1 {{ $rt->from_currency }} = {{ rtrim(rtrim((string) $rt->rate, '0'), '.') }} {{ $rt->to_currency }}</td><td>{{ $rt->effective_date->format('Y-m-d') }}</td><td class="text-end"><form method="POST" action="{{ route('tenant.erp.settings.rates.destroy', $rt->id) }}">@csrf @method('DELETE')<button class="text-red-600">&times;</button></form></td></tr>@endforeach</tbody></table>
    </x-card>
</x-layouts.admin>
