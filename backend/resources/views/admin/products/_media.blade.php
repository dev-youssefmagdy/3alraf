{{-- Product edit: public gallery videos and the shared image + video order (P8). Re-rendered by ProductVideoController after every change. Outside the product <form>, so uploading never submits it. --}}
@php($maxMb = \App\Services\ProductVideoService::maxMb())
<div data-product-media data-order-url="{{ route('admin.products.media.order', $product) }}" data-max-bytes="{{ \App\Services\ProductVideoService::maxKb() * 1024 }}" data-too-large="{{ __('admin.product_media.too_large', ['mb' => $maxMb]) }}" class="mt-5 max-w-4xl">
    <x-card :title="__('admin.product_media.title')">
        <p class="mb-3 text-xs text-slate-500">{{ __('admin.product_media.hint') }}</p>
        @if ($media->isNotEmpty())
            <ol class="mb-4 flex flex-wrap gap-3" data-media-list>
                @foreach ($media as $m)
                    <li data-media-key="{{ $m['key'] }}" data-media-type="{{ $m['type'] }}" class="w-24 text-center text-xs">
                        <div class="relative size-24 overflow-hidden rounded border border-slate-200 bg-slate-800">
                            @if ($m['thumb'])<img src="{{ $m['thumb'] }}" class="size-full object-cover" alt="">@endif
                            @if ($m['type'] === 'video')<span class="absolute inset-0 grid place-items-center"><span class="grid size-8 place-items-center rounded-full bg-black/60 text-white"><svg viewBox="0 0 24 24" class="size-4 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span></span>@endif
                        </div>
                        <div class="mt-1 flex items-center justify-center gap-1">
                            <button type="button" class="btn-sm btn-secondary" data-media-move="-1" aria-label="{{ __('admin.product_media.move_earlier') }}" @disabled($loop->first)><span class="inline-block rtl:-scale-x-100">&#8249;</span></button>
                            <button type="button" class="btn-sm btn-secondary" data-media-move="1" aria-label="{{ __('admin.product_media.move_later') }}" @disabled($loop->last)><span class="inline-block rtl:-scale-x-100">&#8250;</span></button>
                        </div>
                        @if ($m['type'] === 'video')<button type="button" class="btn-sm btn-danger mt-1" data-media-delete="{{ route('admin.products.videos.destroy', [$product, $m['id']]) }}">{{ __('admin.remove') }}</button>@endif
                    </li>
                @endforeach
            </ol>
        @endif
        <form data-product-video-form action="{{ route('admin.products.videos.store', $product) }}" method="POST" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2" novalidate>
            @csrf
            <div data-field>
                <label class="form-label" for="product-video-file">{{ __('admin.product_media.video') }}</label>
                <input id="product-video-file" type="file" name="video" accept="video/mp4,video/webm,.mp4,.webm" class="form-control">
                <p class="mt-1 text-xs text-slate-500">{{ __('admin.product_media.video_hint', ['mb' => $maxMb]) }}</p>
            </div>
            <div data-field>
                <label class="form-label" for="product-video-poster">{{ __('admin.product_media.poster') }}</label>
                <input id="product-video-poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="form-control">
                <p class="mt-1 text-xs text-slate-500">{{ __('admin.product_media.poster_hint') }}</p>
            </div>
            <div class="flex items-center gap-3 sm:col-span-2">
                <button type="submit" class="btn btn-primary">{{ __('admin.product_media.upload') }}</button>
                <progress data-video-progress class="hidden h-2 w-48" max="100" value="0"></progress>
            </div>
        </form>
    </x-card>
</div>
