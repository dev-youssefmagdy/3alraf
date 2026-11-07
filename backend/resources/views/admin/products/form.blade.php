@php
    $editing = $product->exists;
    $selectedCats = collect(old('category_ids', $editing ? $product->categories->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all();
    $variantRows = $editing ? $product->variants->map(fn ($pv) => [
        'id' => $pv->id, 'values' => $pv->values->pluck('id')->all(), 'label' => $pv->label(), 'sku' => $pv->sku, 'weight' => $pv->weight,
        'is_active' => $pv->is_active, 'barcode' => $pv->barcode, 'hash' => $pv->combination_hash, 'image' => $pv->primaryImageUrl(),
    ])->all() : [];
    $attrJson = $attributes->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'values' => $a->values->map(fn ($v) => ['id' => $v->id, 'name' => $v->value, 'color' => $v->color_hex])->all()])->values();
    $usedValueIds = collect($variantRows)->pluck('values')->flatten()->unique()->values();
@endphp
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$product->name : __('admin.add_new')">
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
                @if ($editing && $product->price !== null)
                    @can('catalog.edit')<a href="{{ route('admin.catalog.edit', $product) }}" class="mt-3 inline-block text-sm text-brand-600 hover:underline">{{ __('admin.list_in_store') }} &rarr;</a>@endcan
                @endif
            </x-card>

            <x-card :title="__('admin.gift')">
                @php($giftR = (array) old('gift_recipients', $product->gift_recipients ?? []))
                @php($giftO = (array) old('gift_occasions', $product->gift_occasions ?? []))
                <div class="space-y-3">
                    <x-checkbox name="gift_suitable" :label="__('growth_hub.gift.tag_suitable')" :checked="$product->gift_suitable" />
                    <div data-field>
                        <div class="form-label">{{ __('growth_hub.gift.tag_recipients') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach (\App\Services\Growth\GiftFinderService::RECIPIENTS as $o)<x-checkbox name="gift_recipients[]" :value="$o" :bool="false" :label="__('growth_hub.gift.recipients.'.$o)" :checked="in_array($o, $giftR, true)" />@endforeach</div>
                    </div>
                    <div data-field>
                        <div class="form-label">{{ __('growth_hub.gift.tag_occasions') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach (\App\Services\Growth\GiftFinderService::OCCASIONS as $o)<x-checkbox name="gift_occasions[]" :value="$o" :bool="false" :label="__('growth_hub.gift.occasions.'.$o)" :checked="in_array($o, $giftO, true)" />@endforeach</div>
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
                            <th class="text-start">{{ __('admin.fields.barcode') }}</th><th class="text-start">{{ __('admin.fields.image') }}</th><th class="text-start">{{ __('admin.fields.is_active') }}</th><th></th>
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
</x-layouts.admin>
