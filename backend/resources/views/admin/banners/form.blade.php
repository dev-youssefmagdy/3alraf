@php use App\Enums\BannerPlacement; @endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-3xl">
    <x-select name="placement" :label="__('ops.fields.placement')" :options="BannerPlacement::options()" :value="$model->placement?->value ?? $model->placement" />
    <x-translatable-tabs name="title" :label="__('ops.fields.title')" :languages="$languages" :values="$model->getTranslations('title')" />
    <div class="space-y-3">
        <div class="form-label">{{ __('ops.image_per_locale') }}</div>
        @foreach ($languages as $lang)
            <div data-field class="rounded-lg border border-slate-200 p-3">
                <label class="form-label" for="f-img-{{ $lang->code }}">{{ $lang->native_name }} ({{ $lang->code }})</label>
                @if (($path = ($model->images[$lang->code] ?? null)))
                    <img src="{{ media_url($path) }}" class="mb-2 h-20 rounded border object-contain" alt="">
                    <x-checkbox :name="'remove_images['.$lang->code.']'" :label="__('ops.remove_image')" :bool="false" />
                @endif
                <input id="f-img-{{ $lang->code }}" type="file" name="images[{{ $lang->code }}]" accept="image/*" class="form-control">
            </div>
        @endforeach
    </div>
    <x-input name="link_url" :label="__('ops.fields.link_url')" :value="$model->link_url" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="starts_at" type="datetime-local" :label="__('ops.fields.starts_at')" :value="$model->starts_at?->format('Y-m-d\TH:i')" />
        <x-input name="ends_at" type="datetime-local" :label="__('ops.fields.ends_at')" :value="$model->ends_at?->format('Y-m-d\TH:i')" />
        <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
    </div>
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
