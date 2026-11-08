{{-- Shared private library UI (MCP.md §21.3): /marketer/media and the admin product tab "محتوى السوشيال ميديا". Files only through protected routes. --}}
@php
    $t = fn ($k, $r = []) => __('marketing.media.'.$k, $r);
    $hidden = $hidden ?? [];
    $stay = $stay ?? false;
    $me = auth('admin')->user();
    $platforms = \App\DTOs\Marketing\SocialMediaAssetData::PLATFORMS;
    $formats = \App\DTOs\Marketing\SocialMediaAssetData::FORMATS;
    $ratios = \App\DTOs\Marketing\SocialMediaAssetData::RATIOS;
    $viewUrl = fn ($v) => request()->fullUrlWithQuery(['view' => $v, 'page' => null]);
    $reset = url()->current().($hidden ? '?'.http_build_query($hidden) : '');
@endphp
<div class="space-y-4" data-media-library>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="note" data-internal-notice>{{ $t('internal_notice') }}</p>

    <div class="flex flex-wrap gap-2 text-xs">
        @foreach (\App\Enums\Marketing\MediaAssetStatus::cases() as $st)
            <span class="badge {{ $st->badge() }}">{{ $st->label() }}: {{ $counts[$st->value] ?? 0 }}</span>
        @endforeach
    </div>

    @can('ai_media.upload')
        <details class="rounded-xl border border-slate-200 bg-white shadow-sm" data-upload-panel>
            <summary class="cursor-pointer select-none px-5 py-3 text-base font-semibold text-slate-800">{{ $t('upload_title') }}</summary>
            <form data-ajax-form action="{{ route('marketer.media.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 border-t border-slate-100 p-5 sm:grid-cols-2" novalidate data-upload-form>
                @csrf
                <p class="text-xs text-slate-500 sm:col-span-2">{{ $t('upload_hint', ['img' => round(config('marketing.media.image_max_kb') / 1024), 'vid' => round(config('marketing.media.video_max_kb') / 1024)]) }}</p>
                @if ($stay)<input type="hidden" name="stay" value="1">@endif
                @if ($product)
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                @else
                    <x-select name="product_id" :label="$t('fields.product')" :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->sku])->all()" :value="$filters['product_id'] ?? null" :placeholder="'—'" required search class="sm:col-span-2" />
                @endif
                <x-select name="kind" :label="$t('fields.kind')" :options="collect(\App\Enums\Marketing\MediaAssetKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])->all()" value="image" />
                <div data-field>
                    <label for="f-file" class="form-label">{{ $t('fields.file') }}</label>
                    <input id="f-file" type="file" name="file" class="form-control" accept="{{ implode(',', array_merge(config('marketing.media.image_mimes'), config('marketing.media.video_mimes'))) }}">
                </div>
                <x-input name="title" :label="$t('fields.title')" maxlength="200" />
                <x-input name="campaign" :label="$t('fields.campaign')" maxlength="120" />
                <x-select name="format" :label="$t('fields.format')" :options="collect($formats)->mapWithKeys(fn ($f) => [$f => $t('formats.'.$f)])->all()" placeholder="—" />
                <x-select name="aspect_ratio" :label="$t('fields.aspect_ratio')" :options="array_combine($ratios, $ratios)" placeholder="—" />
                <x-input name="tags" :label="$t('fields.tags')" :hint="$t('tags_hint')" />
                <x-input name="canva_url" :label="$t('fields.canva_url')" dir="ltr" placeholder="https://www.canva.com/design/…" />
                <div class="sm:col-span-2" data-field>
                    <span class="form-label">{{ $t('fields.platforms') }}</span>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach ($platforms as $pf)<x-checkbox name="platforms[]" :value="$pf" :bool="false" :label="$t('platforms.'.$pf)" />@endforeach</div>
                </div>
                @foreach (\App\Support\Marketing\StoreText::locales() as $loc)
                    <x-textarea name="caption[{{ $loc }}]" :label="$t('fields.caption').' ('.$loc.')'" rows="2" />
                @endforeach
                <div class="sm:col-span-2"><x-button type="submit" data-upload-submit>{{ $t('btn.upload') }}</x-button></div>
            </form>
        </details>
    @endcan

    <form method="GET" action="{{ url()->current() }}" class="grid gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-3 lg:grid-cols-6" data-library-filters>
        @foreach ($hidden as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="search" name="q" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ $t('filters.search') }}" aria-label="{{ $t('filters.search') }}">
        @unless ($product)
            <select name="product_id" class="form-control" data-search aria-label="{{ $t('fields.product') }}">
                <option value="">{{ $t('filters.all_products') }}</option>
                @foreach ($products as $p)<option value="{{ $p->id }}" @selected(($filters['product_id'] ?? null) == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        @endunless
        <select name="kind" class="form-control" aria-label="{{ $t('fields.kind') }}">
            <option value="">{{ $t('filters.all_kinds') }}</option>
            @foreach (\App\Enums\Marketing\MediaAssetKind::cases() as $k)<option value="{{ $k->value }}" @selected(($filters['kind'] ?? null) === $k->value)>{{ $k->label() }}</option>@endforeach
        </select>
        <select name="platform" class="form-control" aria-label="{{ $t('fields.platforms') }}">
            <option value="">{{ $t('filters.all_platforms') }}</option>
            @foreach ($platforms as $pf)<option value="{{ $pf }}" @selected(($filters['platform'] ?? null) === $pf)>{{ $t('platforms.'.$pf) }}</option>@endforeach
        </select>
        <select name="campaign" class="form-control" aria-label="{{ $t('fields.campaign') }}">
            <option value="">{{ $t('filters.all_campaigns') }}</option>
            @foreach ($campaigns as $c)<option value="{{ $c }}" @selected(($filters['campaign'] ?? null) === $c)>{{ $c }}</option>@endforeach
        </select>
        <select name="status" class="form-control" aria-label="{{ $t('fields.status') }}">
            <option value="">{{ $t('filters.all_statuses') }}</option>
            @foreach (\App\Enums\Marketing\MediaAssetStatus::cases() as $st)<option value="{{ $st->value }}" @selected(($filters['status'] ?? null) === $st->value)>{{ $st->label() }}</option>@endforeach
        </select>
        <input type="text" name="tag" value="{{ $filters['tag'] ?? '' }}" class="form-control" placeholder="{{ $t('filters.tag') }}" aria-label="{{ $t('filters.tag') }}">
        <div class="flex flex-wrap items-center gap-2 sm:col-span-3 lg:col-span-6">
            <x-button type="submit">{{ $t('filters.apply') }}</x-button>
            <a href="{{ $reset }}" class="btn btn-secondary">{{ $t('filters.reset') }}</a>
            <span class="ms-auto text-sm text-slate-500">{{ $t('items', ['n' => $items->total()]) }}</span>
            <a href="{{ $viewUrl('grid') }}" class="btn-sm {{ $view === 'grid' ? 'btn-primary' : 'btn-secondary' }}" data-view-grid>{{ $t('filters.grid') }}</a>
            <a href="{{ $viewUrl('list') }}" class="btn-sm {{ $view === 'list' ? 'btn-primary' : 'btn-secondary' }}" data-view-list>{{ $t('filters.list') }}</a>
        </div>
    </form>

    @if ($items->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500" data-library-empty>{{ $t('empty') }}</p>
    @elseif ($view === 'grid')
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4" data-library-grid>
            @foreach ($items as $a)
                <a href="{{ route('marketer.media.show', $a->id) }}" class="group block min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm hover:border-brand-500" data-asset="{{ $a->id }}">
                    <div class="flex aspect-square items-center justify-center bg-slate-100">
                        @if ($a->isImage())
                            <img src="{{ route('marketer.media.thumb', $a->id) }}" alt="{{ $a->title }}" loading="lazy" class="size-full object-cover">
                        @elseif ($a->isVideo())
                            {{-- no <video> in the grid: a page of tiles must not stream every clip (no poster: the server has no ffmpeg) --}}
                            <svg class="size-10 text-slate-400" fill="currentColor" viewBox="0 0 24 24" role="img" aria-label="{{ $a->kind->label() }}"><path d="M8 5v14l11-7z"/></svg>
                        @else
                            <span class="px-2 text-center text-xs text-slate-500">{{ $t('no_file') }}</span>
                        @endif
                    </div>
                    <div class="space-y-1 p-2">
                        <p class="truncate text-sm font-semibold text-slate-800">{{ $a->title }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $a->product?->name }}</p>
                        <div class="flex flex-wrap gap-1"><span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span><span class="badge badge-muted">{{ $a->kind->label() }}</span><span class="badge badge-muted">v{{ $a->version }}</span></div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm" data-library-list>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                    <th class="px-3 py-2 text-start">{{ $t('fields.title') }}</th><th class="px-3 py-2 text-start">{{ $t('fields.product') }}</th><th class="px-3 py-2 text-start">{{ $t('fields.kind') }}</th>
                    <th class="px-3 py-2 text-start">{{ $t('fields.platforms') }}</th><th class="px-3 py-2 text-start">{{ $t('fields.campaign') }}</th><th class="px-3 py-2 text-start">{{ $t('fields.status') }}</th>
                    <th class="px-3 py-2 text-start">{{ $t('fields.version') }}</th><th class="px-3 py-2 text-start">{{ $t('fields.created_at') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($items as $a)
                        <tr class="border-t border-slate-100" data-asset="{{ $a->id }}">
                            <td class="px-3 py-2"><a class="font-medium text-brand-700 hover:underline" href="{{ route('marketer.media.show', $a->id) }}">{{ $a->title }}</a></td>
                            <td class="px-3 py-2">{{ $a->product?->name }}</td><td class="px-3 py-2">{{ $a->kind->label() }}</td>
                            <td class="px-3 py-2">{{ collect($a->platforms)->map(fn ($x) => $t('platforms.'.$x))->implode('، ') }}</td><td class="px-3 py-2">{{ $a->campaign }}</td>
                            <td class="px-3 py-2"><span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span></td><td class="px-3 py-2">{{ $a->version }}</td>
                            <td class="whitespace-nowrap px-3 py-2" dir="ltr">{{ $a->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <div>{{ $items->links() }}</div>
</div>
