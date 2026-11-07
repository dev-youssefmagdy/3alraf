<x-layouts.admin guard="tenant" :title="__('erp.cards.cash_bank')[0]">
    @include('tenant.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; $tok = fn () => \Illuminate\Support\Str::uuid(); @endphp
    <x-card :title="__('erp_acc.cb.balances')" class="mb-5">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($balances as $role => $bal)
                <div class="rounded border border-slate-200 p-3"><div class="text-xs text-slate-500">{{ __('erp_acc.roles.'.$role) }}</div>
                    <div class="text-lg font-semibold tabular-nums" data-balance="{{ $role }}">{{ $n(in_array($role, ['vat_payable', 'shipping_payable', 'affiliate_payable'], true) ? -$bal : $bal) }}</div></div>
            @endforeach
        </div>
    </x-card>
    @if ($canPost)
    <div class="grid gap-4 lg:grid-cols-3">
        @foreach (['vat', 'carrier', 'payout'] as $kind)
        <x-card :title="__('erp_acc.cb.'.$kind)">
            <form method="POST" action="{{ route('tenant.erp.cash-bank.'.$kind) }}" class="grid gap-3">@csrf<input type="hidden" name="ref" value="{{ $tok() }}">
                @if ($kind === 'payout')
                    <label class="text-sm">{{ __('erp_acc.cb.source') }}<select name="source" class="{{ $in }}">@foreach (\App\Services\Erp\Cash\SettlementService::PAYOUT_SOURCES as $s)<option value="{{ $s }}">{{ __('erp_acc.roles.'.$s) }}</option>@endforeach</select></label>
                @endif
                <label class="text-sm">{{ $kind === 'payout' ? __('erp_acc.cb.gross') : __('erp_ext.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
                @if ($kind === 'payout')<label class="text-sm">{{ __('erp_acc.cb.fee') }}<input name="fee" type="number" step="0.01" min="0" value="0" class="{{ $in }}"></label>@endif
                <label class="text-sm">{{ $kind === 'payout' ? __('erp_acc.cb.into') : __('erp_acc.cb.from') }}<select name="account" class="{{ $in }}">@foreach (\App\Services\Erp\Cash\SettlementService::PAY_FROM as $a)<option value="{{ $a }}">{{ __('erp_acc.roles.'.$a) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">-</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.note') }}<input name="note" maxlength="300" class="{{ $in }}"></label>
                <p class="text-xs text-slate-500">{{ __('erp_acc.cb.'.$kind.'_hint') }}</p>
                <div><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div>
            </form>
        </x-card>
        @endforeach
    </div>
    @endif
</x-layouts.admin>
