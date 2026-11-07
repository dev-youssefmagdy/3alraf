<x-layouts.admin :title="$rep['title']">
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $has = fn ($k) => in_array($k, $filters); @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            @if ($has('from'))<label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $f->from }}" class="{{ $in }}"></label>@endif
            @if ($has('to'))<label>{{ $has('from') ? __('erp.f.to') : __('erp.r.as_of') }}<input type="date" name="to" value="{{ $f->to }}" class="{{ $in }}"></label>@endif
            @if ($has('branch') && $canBranch)<label>{{ __('erp.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($f->branchId == $b->id)>{{ $b->name }}</option>@endforeach</select></label>@endif
            @if ($has('account'))<label>{{ __('erp.f.account') }}<select name="account_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($f->accountId == $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>@endif
            @if ($has('type'))<label>{{ __('erp_rep.col.type') }}<select name="type" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach (array_merge(\App\Services\Erp\Reports\ErpReportFilter::ENTRY_TYPES, ['order', 'order_return', 'purchase_order', 'expense', 'stock_transfer', 'stock_adjustment']) as $ty)<option value="{{ $ty }}" @selected($f->type === $ty)>{{ __('erp_rep.types.'.$ty) }}</option>@endforeach</select></label>@endif
            @if ($has('customer'))<label>{{ __('erp_rep.col.customer') }}<select name="customer_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($f->customerId == $c->id)>{{ $c->name }}</option>@endforeach</select></label>@endif
            @if ($has('supplier'))<label>{{ __('erp_rep.col.supplier') }}<select name="supplier_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($suppliers as $c)<option value="{{ $c->id }}" @selected($f->supplierId == $c->id)>{{ $c->name }}</option>@endforeach</select></label>@endif
            @if ($has('payment'))<label>{{ __('erp_rep.col.payment') }}<select name="payment_method_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($methods as $c)<option value="{{ $c->id }}" @selected($f->paymentMethodId == $c->id)>{{ $c->name }}</option>@endforeach</select></label>@endif
            @if ($has('currency'))<label>{{ __('erp_rep.col.currency') }}<input name="currency" maxlength="3" value="{{ $f->currency }}" class="{{ $in }} w-20 uppercase"></label>@endif
            <x-button type="submit">{{ __('erp.f.filter') }}</x-button>
        </form>
    </x-card>
    @include('admin.erp._export')
    @if ($rep['cards'])
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($rep['cards'] as $c)<div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">{{ $c['label'] }}</div><div class="mt-1 text-xl font-bold tabular-nums">{{ $c['type'] === 'int' ? number_format((float) $c['value']) : number_format((float) $c['value'], 2) }}</div></div>@endforeach
        </div>
    @endif
    <x-card>
        @foreach ($rep['tables'] as $t)@include('admin.erp._rtable', ['t' => $t])@endforeach
        @foreach ($rep['notes'] as $n)<p class="mt-3 text-sm text-amber-700">{{ $n }}</p>@endforeach
    </x-card>
</x-layouts.admin>
