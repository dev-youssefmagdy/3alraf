@php
    $editing = $product->exists;
    $selectedCats = collect(old('category_ids', $editing ? $product->categories->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all();
    $variantRows = $editing ? $product->variants->map(fn ($pv) => [
        'id' => $pv->id, 'values' => $pv->values->pluck('id')->all(), 'label' => $pv->label(), 'sku' => $pv->sku, 'weight' => $pv->weight,
        'is_active' => $pv->is_active, 'price' => $pv->price, 'sale_price' => $pv->sale_price, 'cost' => $pv->cost, 'min_stock_alert_qty' => $pv->min_stock_alert_qty, 'barcode' => $pv->barcode, 'hash' => $pv->combination_hash, 'image' => $pv->primaryImageUrl(),
    ])->all() : [];
    $attrJson = $attributes->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'values' => $a->values->map(fn ($v) => ['id' => $v->id, 'name' => $v->value, 'color' => $v->color_hex])->all()])->values();
    $usedValueIds = collect($variantRows)->pluck('values')->flatten()->unique()->values();
@endphp
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$product->name : __('admin.add_new')">
    @if ($editing && auth('admin')->user()->can('ai_media.view'))
        @php($socialTab = ! empty($library))
        <nav class="mb-4 flex flex-wrap gap-2 border-b border-slate-200" aria-label="{{ __('marketing.media.tab') }}" data-product-tabs>
            <a href="{{ route('admin.products.edit', $product) }}" class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold {{ $socialTab ? 'border-transparent text-slate-500' : 'border-brand-600 text-brand-700' }}" @unless ($socialTab) aria-current="page" @endunless>{{ __('marketing.media.tab_details') }}</a>
            <a href="{{ route('admin.products.edit', [$product, 'tab' => 'social']) }}" class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold {{ $socialTab ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500' }}" @if ($socialTab) aria-current="page" @endif data-social-tab>{{ __('marketing.media.tab') }}</a>
        </nav>
    @endif
    @if (! empty($library))
        @include('marketer.media._library', $library + ['hidden' => ['tab' => 'social'], 'stay' => true])
    @else
    <form data-ajax-form @if ($editing) data-method="PUT" @endif
        data-validate-url="{{ route('admin.products.validate', $editing ? [$product] : []) }}"
        action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-3" novalidate>
        @csrf
        <div class="space-y-5 lg:col-span-2">
            <x-card :title="__('admin.general')" class="space-y-4">
                <div class="space-y-4">
                    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$product->getTranslations('name')" />
                    <x-translatable-tabs name="description" type="textarea" :rows="6" :label="__('admin.fields.description')" :languages="$languages" :values="$product->getTranslations('description')" />
                </div>
            </x-card>

            <x-card :title="__('admin.pricing')">
                <p class="mb-3 text-xs text-slate-500">{{ __('admin.pricing_hint') }}</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input name="price" type="number" step="0.01" min="0" :label="__('admin.fields.price')" :value="$product->price" />
                    <x-input name="sale_price" type="number" step="0.01" min="0" :label="__('admin.fields.sale_price')" :value="$product->sale_price" />
                    <x-input name="cost" type="number" step="0.01" min="0" :label="__('admin.fields.cost')" :value="$product->cost" />
                    <x-input name="min_stock_alert_qty" type="number" step="1" min="0" :label="__('stock_requests.min_alert')" :value="$product->min_stock_alert_qty" :hint="__('stock_requests.min_alert_hint')" />
                </div>
            </x-card>

            <x-card :title="__('admin.gift')">
                @php($giftR = (array) old('gift_recipients', $product->gift_recipients ?? []))
                @php($giftO = (array) old('gift_occasions', $product->gift_occasions ?? []))
                <div class="space-y-3">
                    <x-checkbox name="gift_suitable" :label="__('gift_finder.tag_suitable')" :checked="$product->gift_suitable" />
                    <div data-field>
                        <div class="form-label">{{ __('gift_finder.tag_recipients') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach (\App\Services\GiftFinder\GiftFinderService::RECIPIENTS as $o)<x-checkbox name="gift_recipients[]" :value="$o" :bool="false" :label="__('gift_finder.recipients.'.$o)" :checked="in_array($o, $giftR, true)" />@endforeach</div>
                    </div>
                    <div data-field>
                        <div class="form-label">{{ __('gift_finder.tag_occasions') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach (\App\Services\GiftFinder\GiftFinderService::OCCASIONS as $o)<x-checkbox name="gift_occasions[]" :value="$o" :bool="false" :label="__('gift_finder.occasions.'.$o)" :checked="in_array($o, $giftO, true)" />@endforeach</div>
                    </div>
                </div>
            </x-card>

            <x-card :title="__('admin.images')">
                @if ($editing && $product->images->isNotEmpty())
                    <div class="mb-3 flex flex-wrap gap-3">
                        @foreach ($product->images as $img)
                            <label class="relative block text-center text-xs">
                                <img src="{{ $img->thumb_url }}" class="size-20 rounded border object-cover" alt="">
                                <span class="mt-1 flex items-center justify-center gap-1"><input type="checkbox" name="remove_images[]" value="{{ $img->id }}"> {{ __('admin.remove') }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <div data-field>
                    <input type="file" name="images[]" multiple accept="image/*" class="form-control">
                    <p class="mt-1 text-xs text-slate-500">{{ __('admin.images_hint') }}</p>
                    @unless ($editing)<p class="mt-1 text-xs text-slate-500">{{ __('admin.product_media.after_save') }}</p>@endunless
                </div>
            </x-card>

            <x-card :title="__('admin.variants')" data-variant-section id="variant-section">
                <div id="variant-generator" data-attributes='@json($attrJson)' data-used='@json($usedValueIds)' class="space-y-4">
                    <p class="text-sm text-slate-500">{{ __('admin.variant_generator_hint') }}</p>
                    <div data-attr-pickers class="space-y-3"></div>
                    <button type="button" data-generate class="btn btn-secondary">{{ __('admin.generate_variants') }}</button>
                </div>
                <div data-repeater class="mt-5 overflow-x-auto" id="variant-repeater">
                    <table class="w-full text-sm">
                        <thead><tr class="text-start text-xs uppercase text-slate-500">
                            <th class="text-start">{{ __('admin.variant') }}</th><th class="text-start">{{ __('admin.fields.sku') }}</th><th class="text-start">{{ __('admin.fields.weight') }}</th>
                            <th class="text-start">{{ __('admin.fields.barcode') }}</th><th class="text-start">{{ __('admin.fields.image') }}</th><th class="text-start">{{ __('admin.fields.price') }}</th><th class="text-start">{{ __('admin.fields.sale_price') }}</th><th class="text-start">{{ __('admin.fields.cost') }}</th><th class="text-start">{{ __('stock_requests.min_alert') }}</th><th class="text-start">{{ __('admin.fields.is_active') }}</th><th></th>
                        </tr></thead>
                        <tbody data-repeater-list>
                            @foreach ($variantRows as $i => $v)
                                @include('admin.products._variant-row', ['i' => $i, 'v' => $v])
                            @endforeach
                        </tbody>
                    </table>
                    <template data-repeater-template>
                        <table><tbody>@include('admin.products._variant-row', ['i' => '__INDEX__', 'v' => []])</tbody></table>
                    </template>
                    <p data-empty class="py-3 text-sm text-slate-400">{{ __('admin.no_variants_yet') }}</p>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ __('admin.variant_override_hint') }}</p>
                <div data-field data-variants-field></div>
            </x-card>

            <x-card :title="__('admin.seo')">
                <div class="space-y-4">
                    <x-translatable-tabs name="meta_title" :label="__('admin.fields.meta_title')" :languages="$languages" :values="$product->getTranslations('meta_title')" />
                    <x-translatable-tabs name="meta_description" type="textarea" :label="__('admin.fields.meta_description')" :languages="$languages" :values="$product->getTranslations('meta_description')" />
                    <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$product->slug" :hint="__('admin.slug_hint')" />
                </div>
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card :title="__('admin.organization')">
                <div class="space-y-4">
                    <x-select name="type" :label="__('admin.fields.type')" :options="$types" :value="$product->type?->value" required />
                    <x-select name="brand_id" :label="__('admin.brand')" :options="$brands" :value="$product->brand_id" :placeholder="__('admin.no_brand')" />
                    <div data-field>
                        <div class="form-label">{{ __('admin.categories') }}<span class="text-red-500">*</span></div>
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                            @foreach ($categoryTree as $c)
                                <div style="padding-inline-start: {{ $c->depth * 1.25 }}rem">
                                    <x-checkbox name="category_ids[]" :value="$c->id" :bool="false" :label="$c->name" :checked="in_array($c->id, $selectedCats)" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($tags)
                        @php($selectedTags = collect(old('tag_ids', $editing ? $product->tags->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all())
                        <div data-field>
                            <div class="form-label">{{ __('admin.tags') }}</div>
                            <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                                @foreach ($tags as $id => $label)<x-checkbox name="tag_ids[]" :value="$id" :bool="false" :label="$label" :checked="in_array($id, $selectedTags)" />@endforeach
                            </div>
                        </div>
                    @endif
                    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$product->is_active" />
                </div>
            </x-card>

            <x-card :title="__('admin.identification')">
                <div class="space-y-4">
                    <x-input name="sku" :label="__('admin.fields.sku')" :value="$product->sku" required />
                    <div data-simple-section>
                        <x-input name="barcode" :label="__('admin.fields.barcode')" :value="$product->barcode" maxlength="13" :hint="__('admin.barcode_hint')" />
                        @if ($product->barcode)
                            <img src="{{ route('admin.barcode', $product->barcode) }}?h=50" alt="{{ $product->barcode }}" class="mt-2 h-12">
                        @endif
                    </div>
                    <x-input name="weight" type="number" step="0.001" min="0" :label="__('admin.fields.weight')" :value="$product->weight" />
                </div>
            </x-card>

            <x-form-actions :cancel="route('admin.products.index')" />
        </div>
    </form>
    @if ($editing)
        @include('admin.products._media', ['product' => $product, 'media' => $media])
    @endif
    @if ($editing && $product->price !== null)
        @php($variants = $product->variants->filter(fn ($v) => ! $v->trashed() && $v->is_active))
        @php($pq = $qrCodes->get(\App\Models\Product::class.':'.$product->id))
        <x-card class="mt-5 max-w-4xl">
            <h3 class="mb-1 font-semibold">{{ __('admin.qr_code') }}</h3>
            <p class="mb-3 text-xs text-slate-500">{{ __('admin.qr_hint') }}</p>
            <div class="flex flex-wrap gap-6">
                <div class="text-center">
                    <img src="{{ $pq?->file_url ?? route('admin.products.qr', $product) }}" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                    <div class="mt-1 text-xs text-slate-500">{{ __('admin.qr_product') }}</div>
                    <a href="{{ route('admin.products.qr', $product) }}" class="btn-sm btn-secondary mt-2" download>{{ __('admin.qr_download') }}</a>
                    @can('products.edit')<button type="button" class="btn-sm btn-secondary mt-2" data-post-action="{{ route('admin.products.qr.regenerate', $product) }}" data-confirm="{{ __('admin.qr_regenerate_confirm') }}">{{ __('admin.qr_regenerate') }}</button>@endcan
                </div>
                @foreach ($variants as $tv)
                    @php($vq = $qrCodes->get(\App\Models\ProductVariant::class.':'.$tv->id))
                    <div class="text-center">
                        <img src="{{ $vq?->file_url ?? route('admin.products.qr', [$product, 'variant' => $tv->id]) }}" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                        <div class="mt-1 text-xs text-slate-500">{{ $tv->label() }}</div>
                        <a href="{{ route('admin.products.qr', [$product, 'variant' => $tv->id]) }}" class="btn-sm btn-secondary mt-2" download>{{ __('admin.qr_download') }}</a>
                        @can('products.edit')<button type="button" class="btn-sm btn-secondary mt-2" data-post-action="{{ route('admin.products.qr.regenerate', [$product, 'variant' => $tv->id]) }}" data-confirm="{{ __('admin.qr_regenerate_confirm') }}">{{ __('admin.qr_regenerate') }}</button>@endcan
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
    @endif
</x-layouts.admin>
