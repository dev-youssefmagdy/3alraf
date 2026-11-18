{{--
    Drag-and-drop image field; behaviour in resources/js/lib/image-dropzone.js. The real file input stays inside, so the form submits as usual.
    Gallery: :multiple="true" :max="12" :existing="[['id' => 1, 'url' => '...']]" remove-name="remove_images[]" (existing images are ticked for removal).
    Single (compact tile, for table rows): :preview="$url" shows the current image; a new file replaces it on save.
--}}
@props(['name', 'multiple' => false, 'max' => null, 'existing' => [], 'removeName' => null, 'preview' => null, 'hint' => null])
@php
    $strings = 'data-too-many="'.e(__('admin.image_dropzone.too_many', ['max' => $max])).'" data-not-image="'.e(__('admin.image_dropzone.not_image')).'"';
    $target = 'border-slate-300 bg-slate-50 transition hover:border-panel hover:bg-panel-soft has-[:focus-visible]:border-panel has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-panel/30 has-[.is-invalid]:border-red-400 has-[.is-invalid]:bg-red-50 group-data-[over]/dz:border-panel group-data-[over]/dz:bg-panel-soft';
    $roundBtn = 'size-6 place-items-center rounded-full bg-white text-slate-700 shadow ring-1 ring-slate-200 transition hover:bg-red-50 hover:text-red-700';
    $xIcon = '<svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
@endphp
@if ($multiple)
    <div data-dropzone data-field @if ($max) data-max="{{ $max }}" @endif {!! $strings !!} {{ $attributes->merge(['class' => 'group/dz space-y-3']) }}>
        <label data-dz-target class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed px-6 py-8 text-center relative {{ $target }}">
            <input type="file" name="{{ $name }}" multiple accept="image/*" data-no-compress class="sr-only">
            <span class="grid size-12 place-items-center rounded-full bg-white text-panel-text shadow-sm ring-1 ring-slate-200">
                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V5m0 0L7.5 9.5M12 5l4.5 4.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
            </span>
            <span class="text-sm font-semibold text-slate-900">{{ __('admin.image_dropzone.drop') }} <span class="text-panel-text underline underline-offset-2">{{ __('admin.image_dropzone.browse') }}</span></span>
            @if ($hint)<span class="text-xs text-slate-500">{{ $hint }}</span>@endif
            @if ($max)<span class="text-xs text-slate-500">{{ __('admin.image_dropzone.max_hint', ['max' => $max]) }}</span>@endif
        </label>
        <ul data-dz-list class="grid grid-cols-3 gap-3 empty:hidden sm:grid-cols-4 lg:grid-cols-6">@foreach ($existing as $img)<li>
                <label class="group relative block aspect-square cursor-pointer overflow-hidden rounded-xl border border-slate-200 bg-slate-100 has-[:checked]:border-red-300">
                    <img src="{{ $img['url'] }}" class="size-full object-cover transition group-has-[:checked]:opacity-30 group-has-[:checked]:grayscale" alt="">
                    <input type="checkbox" name="{{ $removeName }}" value="{{ $img['id'] }}" class="peer sr-only" aria-label="{{ __('admin.remove') }}">
                    <span class="absolute end-1.5 top-1.5 peer-checked:hidden peer-focus-visible:ring-2 peer-focus-visible:ring-panel/40 grid {{ $roundBtn }}" title="{{ __('admin.remove') }}">{!! $xIcon !!}</span>
                    <span class="absolute inset-x-1.5 bottom-1.5 hidden rounded-lg bg-red-600 px-1 py-0.5 text-center text-[11px] font-semibold text-white peer-checked:block">{{ __('admin.image_dropzone.will_remove') }}</span>
                    <span class="absolute end-1.5 top-1.5 hidden rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 shadow ring-1 ring-slate-200 peer-checked:block peer-focus-visible:ring-2 peer-focus-visible:ring-panel/40">{{ __('admin.image_dropzone.undo') }}</span>
                </label>
            </li>@endforeach</ul>
        <template data-dz-item>
            <li data-dz-new class="relative aspect-square overflow-hidden rounded-xl border border-panel/40 bg-slate-100">
                <img data-dz-img class="size-full object-cover" alt="">
                <span class="absolute start-1.5 top-1.5 rounded-full bg-panel px-2 py-0.5 text-[11px] font-semibold text-white">{{ __('admin.image_dropzone.new') }}</span>
                <button type="button" data-dz-remove class="absolute end-1.5 top-1.5 grid {{ $roundBtn }}" aria-label="{{ __('admin.remove') }}" title="{{ __('admin.remove') }}">{!! $xIcon !!}</button>
            </li>
        </template>
    </div>
@else
    <div data-dropzone data-field {!! $strings !!} @if ($preview) data-filled @endif {{ $attributes->merge(['class' => 'group/dz relative size-20']) }}>
        <label data-dz-target class="relative grid size-20 cursor-pointer place-items-center overflow-hidden rounded-xl border-2 border-dashed group-data-[filled]/dz:border-solid group-data-[filled]/dz:border-slate-200 {{ $target }}" title="{{ __('admin.image_dropzone.single') }}">
            <input type="file" name="{{ $name }}" accept="image/*" data-no-compress class="sr-only" aria-label="{{ __('admin.image_dropzone.single') }}">
            <img data-dz-img @if ($preview) src="{{ $preview }}" data-dz-original="{{ $preview }}" @endif class="absolute inset-0 hidden size-full object-cover group-data-[filled]/dz:block" alt="">
            <span class="flex flex-col items-center gap-0.5 px-1 text-center text-[10px] font-semibold leading-tight text-slate-500 group-data-[filled]/dz:hidden">
                <svg viewBox="0 0 24 24" class="size-5 text-panel-text" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('admin.image_dropzone.single') }}
            </span>
            <span class="absolute bottom-1 end-1 hidden size-5 place-items-center rounded-full bg-white text-slate-700 shadow ring-1 ring-slate-200 group-data-[filled]/dz:grid group-data-[dirty]/dz:!hidden">
                <svg viewBox="0 0 24 24" class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/></svg>
            </span>
        </label>
        <button type="button" data-dz-remove class="absolute -end-1.5 -top-1.5 hidden group-data-[dirty]/dz:grid {{ $roundBtn }}" aria-label="{{ __('admin.remove') }}" title="{{ __('admin.remove') }}">{!! $xIcon !!}</button>
    </div>
@endif
