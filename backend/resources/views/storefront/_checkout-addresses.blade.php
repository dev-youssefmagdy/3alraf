@php $pick = $selectedAddress ?? ($addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id); @endphp
{{-- Address card (design 21:1878): radio address_id + data-city, pin, label, Default badge, name · phone, city - address, Edit --}}
@forelse ($addresses as $a)
    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-slate-200 bg-white p-3.5 text-sm transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45">
        <input type="radio" name="address_id" value="{{ $a->id }}" data-city="{{ $a->city_id }}" class="mt-0.5 size-[22px] shrink-0 accent-brand-600" @checked($pick === $a->id)>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <svg class="size-4 shrink-0 text-pop-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <b class="text-base text-slate-950">{{ $a->label ?: $a->name }}</b>
                @if ($a->is_default)<span class="badge bg-brand-600 text-white">{{ __('store.default') }}</span>@endif
            </span>
            <span class="mt-1 block text-slate-600">{{ $a->name }} · <bdi dir="ltr">{{ $a->phone_display ?: $a->phone }}</bdi></span>
            <span class="block text-slate-600">{{ $a->city?->name }} - {{ $a->address }}</span>
        </span>
        <button type="button" class="btn-sm inline-flex shrink-0 items-center gap-1 border border-slate-200 bg-white text-slate-950 hover:border-brand-500 hover:text-brand-600" data-address-edit data-address="{{ json_encode(['id' => $a->id, 'label' => $a->label, 'name' => $a->name, 'phone' => $a->phone, 'city_id' => $a->city_id, 'address' => $a->address, 'is_default' => (bool) $a->is_default, 'url' => route('storefront.account.addresses.update', ['locale' => app()->getLocale(), 'id' => $a->id])]) }}">
            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/></svg>{{ __('store.edit') }}
        </button>
    </label>
@empty
    {{-- not sent (disabled): anchors the address_id error under the list; the inline-address field errors (no address yet) land in a hidden box instead of a toast each --}}
    <input type="hidden" name="address_id" value="" disabled>
    <div class="hidden">@foreach (['name', 'phone', 'city_id', 'address'] as $anchor)<div data-field><input type="hidden" name="{{ $anchor }}" value="" disabled></div>@endforeach</div>
    <p class="rounded-2xl bg-slate-100 px-4 py-4 text-center text-sm text-slate-600">{{ __('store.no_addresses_hint') }}</p>
@endforelse
