<x-layouts.storefront :title="__('growth_hub.gift.title')">
    @php $g = fn ($k) => __('growth_hub.gift.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2'; @endphp
    @php
        $cfg = ['symbol' => current_tenant()->currency_symbol, 'searchUrl' => route('storefront.gift-finder.search', ['locale' => app()->getLocale()]),
            'productUrl' => url(app()->getLocale().'/product'), 'bundleUrl' => url(app()->getLocale().'/bundle'),
            't' => collect(['results', 'none', 'near', 'bundles', 'loading', 'error', 'over', 'choose_options', 'unavailable_option'])->mapWithKeys(fn ($k) => [$k => $g($k)])->all() + ['add' => $g('add_selected')]];
    @endphp
    <div id="gf" class="mx-auto max-w-3xl space-y-6" data-config="{{ json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) }}">
        <h1 class="text-2xl font-extrabold">{{ $g('title') }}</h1>
        <form id="gf-form" class="grid gap-3 sm:grid-cols-2" novalidate>
            @csrf
            <label class="text-sm">{{ $g('fields.budget') }} ({{ current_tenant()->currency_symbol }})<input type="number" name="budget" min="1" step="any" required class="{{ $inp }}"></label>
            <label class="text-sm">{{ $g('fields.occasion') }}<select name="occasion" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($occasions as $o)<option value="{{ $o }}">{{ $g('occasions.'.$o) }}</option>@endforeach</select></label>
            <label class="text-sm">{{ $g('fields.recipient') }}<select name="recipient" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($recipients as $o)<option value="{{ $o }}">{{ $g('recipients.'.$o) }}</option>@endforeach</select></label>
            <label class="text-sm">{{ $g('fields.category_id') }}<select name="category_id" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
            <label class="text-sm sm:col-span-2">{{ $g('fields.restrictions') }}<input name="restrictions" maxlength="200" class="{{ $inp }}"></label>
            <div class="sm:col-span-2"><button type="submit" class="btn btn-primary !px-8">{{ $g('find') }}</button></div>
        </form>
        <div id="gf-out" class="space-y-4"></div>
    </div>
</x-layouts.storefront>
