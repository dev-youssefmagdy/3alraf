@php $pick = $selectedAddress ?? ($addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id); @endphp
@forelse ($addresses as $a)
    <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-white p-3 text-sm transition has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
        <input type="radio" name="address_id" value="{{ $a->id }}" data-city="{{ $a->city_id }}" class="mt-1 accent-brand-600" @checked($pick === $a->id)>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2"><b>{{ $a->label ?: $a->name }}</b> @if ($a->is_default)<span class="badge badge-success">{{ __('store.default') }}</span>@endif</span>
            <span class="block text-slate-600">{{ $a->name }} · <bdi dir="ltr">{{ $a->phone }}</bdi></span>
            <span class="block text-slate-500">{{ $a->city?->name }} - {{ $a->address }}</span>
        </span>
        <button type="button" class="btn-sm shrink-0 bg-slate-100 text-slate-700 hover:bg-slate-200" data-address-edit data-address="{{ json_encode(['id' => $a->id, 'label' => $a->label, 'name' => $a->name, 'phone' => $a->phone, 'city_id' => $a->city_id, 'address' => $a->address, 'is_default' => (bool) $a->is_default, 'url' => route('storefront.account.addresses.update', ['locale' => app()->getLocale(), 'id' => $a->id])]) }}">{{ __('store.edit') }}</button>
    </label>
@empty
    <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">{{ __('store.no_addresses_hint') }}</p>
@endforelse
