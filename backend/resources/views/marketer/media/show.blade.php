@php
    $t = fn ($k, $r = []) => __('marketing.media.'.$k, $r);
    $S = \App\Enums\Marketing\MediaAssetStatus::class;
    $st = $a->status;
    $formats = \App\DTOs\Marketing\SocialMediaAssetData::FORMATS;
    $ratios = \App\DTOs\Marketing\SocialMediaAssetData::RATIOS;
    $me = auth('marketer')->user();
    $canEdit = $me->can('ai_media.edit') && $st !== $S::ARCHIVED;
    $canApprove = $me->can('ai_media.approve');
    $size = fn ($b) => $b === null ? '—' : ($b >= 1048576 ? round($b / 1048576, 1).' MB' : round($b / 1024).' KB');
@endphp
<x-layouts.marketer :title="$a->title">
    <x-slot:header>
        <x-ui.page-header :title="$a->title" :breadcrumbs="[[__('marketing.media.title'), route('marketer.media.index', ['product_id' => $a->product_id])]]">
            <div class="flex flex-wrap items-center gap-2 max-md:w-full" data-actions>
                @if ($a->hasFile())<a class="btn btn-secondary btn-md" href="{{ route('marketer.media.download', $a->id) }}" data-action="download">{{ $t('btn.download') }}</a>@endif
                @if ($canEdit && in_array($st, [$S::DRAFT, $S::REJECTED], true))
                    <button type="button" class="btn btn-secondary btn-md" data-action="submit" data-post-action="{{ route('marketer.media.submit', $a->id) }}">{{ $t('btn.submit') }}</button>
                @endif
                @if ($canApprove && in_array($st, [$S::DRAFT, $S::PENDING_REVIEW, $S::REJECTED], true))
                    <button type="button" class="btn btn-primary btn-md" data-action="approve" data-post-action="{{ route('marketer.media.approve', $a->id) }}" data-confirm="{{ $t('confirm.approve') }}">{{ $t('btn.approve') }}</button>
                @endif
                @if ($me->can('ai_media.edit') && $st !== $S::ARCHIVED)
                    <button type="button" class="btn btn-secondary btn-md" data-action="archive" data-post-action="{{ route('marketer.media.archive', $a->id) }}" data-confirm="{{ $t('confirm.archive') }}">{{ $t('btn.archive') }}</button>
                @endif
                @if ($me->can('meta_ads.draft') && Route::has('marketer.meta-ads.create'))
                    <a class="btn btn-secondary btn-md" href="{{ route('marketer.meta-ads.create', ['product_id' => $a->product_id]) }}">{{ $t('btn.meta_draft') }}</a>
                @endif
                @can('ai_media.delete')
                    <button type="button" class="btn btn-danger btn-md" data-action="delete" data-delete="{{ route('marketer.media.destroy', $a->id) }}">{{ $t('btn.delete') }}</button>
                @endcan
            </div>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-media-asset="{{ $a->id }}" data-status="{{ $st->value }}">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$st->badge()" dot data-status-badge>{{ $st->label() }}</x-ui.badge>
            <x-ui.badge>{{ $a->kind->label() }}</x-ui.badge>
            <x-ui.badge>{{ $t('fields.version') }} {{ $a->version }}</x-ui.badge>
            <x-ui.badge>{{ $t('sources.'.$a->source) }}</x-ui.badge>
            @if ($a->suggestion_id && Route::has('marketer.suggestions.show'))<a class="text-xs text-panel-accent-text hover:underline" href="{{ route('marketer.suggestions.show', $a->suggestion_id) }}">{{ $t('from_suggestion', ['id' => $a->suggestion_id]) }}</a>@endif
        </div>
        <x-ui.alert tone="warning" data-internal-notice>{{ $t('internal_notice') }}</x-ui.alert>
        @if ($a->review_note)<x-ui.alert tone="info"><b>{{ $t('fields.review_note') }}:</b> {{ $a->review_note }}</x-ui.alert>@endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card :title="$t('btn.preview')">
                <div>
                    @if ($a->isImage())
                        <img src="{{ route('marketer.media.file', $a->id) }}" alt="{{ $a->title }}" class="mx-auto max-h-[28rem] max-w-full rounded-lg" data-preview>
                    @elseif ($a->isVideo())
                        <video src="{{ route('marketer.media.file', $a->id) }}" controls playsinline preload="metadata" class="mx-auto max-h-[28rem] max-w-full rounded-lg" data-preview></video>
                    @else
                        <p class="py-10 text-center text-sm text-panel-text-muted" data-no-file>{{ $t('no_file') }}</p>
                    @endif
                    @if ($a->brief)<p class="mt-3 whitespace-pre-line break-words rounded-lg bg-panel-surface-sunken p-3 text-sm text-panel-text">{{ $a->brief }}</p>@endif
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-panel-text-muted">{{ $t('fields.product') }}</dt><dd class="min-w-0 break-words"><a class="text-panel-accent-text hover:underline" href="{{ route('marketer.media.index', ['product_id' => $a->product_id]) }}">{{ $a->product?->name }}</a></dd>
                        @if ($a->hasFile())
                            <dt class="text-panel-text-muted">{{ $t('fields.original_name') }}</dt><dd class="min-w-0 break-all" dir="ltr">{{ $a->original_name }}</dd>
                            <dt class="text-panel-text-muted">{{ $t('fields.mime') }}</dt><dd dir="ltr">{{ $a->mime }}</dd>
                            <dt class="text-panel-text-muted">{{ $t('fields.size') }}</dt><dd dir="ltr">{{ $size($a->size_bytes) }}</dd>
                            @if ($a->width)<dt class="text-panel-text-muted">{{ $t('fields.dimensions') }}</dt><dd dir="ltr">{{ $a->width }}×{{ $a->height }}</dd>@endif
                            @if ($a->duration_seconds)<dt class="text-panel-text-muted">{{ $t('fields.duration_seconds') }}</dt><dd>{{ $a->duration_seconds }}</dd>@endif
                        @endif
                        <dt class="text-panel-text-muted">{{ $t('fields.created_at') }}</dt><dd dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</dd>
                        @if ($a->creator)<dt class="text-panel-text-muted">{{ $t('fields.uploaded_by') }}</dt><dd>{{ $a->creator->name }}</dd>@endif
                        @if ($a->reviewer)<dt class="text-panel-text-muted">{{ $t('fields.reviewed_by') }}</dt><dd>{{ $a->reviewer->name }} <span dir="ltr">{{ $a->reviewed_at?->format('Y-m-d H:i') }}</span></dd>@endif
                        @if ($a->canva_url)<dt class="text-panel-text-muted">{{ $t('fields.canva_url') }}</dt><dd class="min-w-0 break-all" dir="ltr"><a class="text-panel-accent-text hover:underline" href="{{ $a->canva_url }}" target="_blank" rel="noopener noreferrer">{{ $a->canva_url }}</a></dd>@endif
                        @if ($a->generation_meta)<dt class="text-panel-text-muted">{{ $t('fields.generation') }}</dt><dd class="min-w-0 break-all text-xs" dir="ltr">{{ json_encode($a->generation_meta, JSON_UNESCAPED_UNICODE) }}</dd>@endif
                    </dl>
                    @if ($canApprove && in_array($st, [$S::PENDING_REVIEW, $S::APPROVED, $S::DRAFT], true))
                        <form data-ajax-form action="{{ route('marketer.media.reject', $a->id) }}" method="POST" class="mt-4 flex flex-wrap items-center gap-2" novalidate data-reject-form>
                            @csrf
                            <input type="text" name="note" maxlength="1000" class="form-control min-w-0 flex-1" placeholder="{{ $t('note_placeholder') }}" aria-label="{{ $t('fields.review_note') }}">
                            <x-ui.button type="submit" variant="danger" data-action="reject">{{ $t('btn.reject') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="$t('details')">
                @if ($canEdit)
                    <form data-ajax-form data-method="PUT" action="{{ route('marketer.media.update', $a->id) }}" method="POST" class="grid gap-3 sm:grid-cols-2" novalidate data-edit-form>
                        @csrf
                        <x-ui.input name="title" :label="$t('fields.title')" :value="$a->title" maxlength="200" class="sm:col-span-2" />
                        <x-ui.select name="format" :label="$t('fields.format')" :options="collect($formats)->mapWithKeys(fn ($f) => [$f => $t('formats.'.$f)])->all()" :value="$a->format" placeholder="—" />
                        <x-ui.select name="aspect_ratio" :label="$t('fields.aspect_ratio')" :options="array_combine($ratios, $ratios)" :value="$a->aspect_ratio" placeholder="—" />
                        <x-ui.input name="campaign" :label="$t('fields.campaign')" :value="$a->campaign" maxlength="120" />
                        <x-ui.input name="purpose" :label="$t('fields.purpose')" :value="$a->purpose" maxlength="500" />
                        <x-ui.input name="tags" :label="$t('fields.tags')" :value="implode(', ', (array) $a->tags)" :hint="$t('tags_hint')" />
                        <x-ui.input name="hashtags" :label="$t('fields.hashtags')" :value="implode(' ', (array) $a->hashtags)" />
                        <x-ui.input name="cta" :label="$t('fields.cta')" :value="$a->cta" maxlength="200" />
                        <x-ui.input name="canva_url" :label="$t('fields.canva_url')" :value="$a->canva_url" dir="ltr" />
                        @if ($a->isVideo())<x-ui.input name="duration_seconds" type="number" min="1" max="3600" :label="$t('fields.duration_seconds')" :value="$a->duration_seconds" />@endif
                        <x-ui.input name="suggested_publish_at" type="datetime-local" :label="$t('fields.suggested_publish_at')" :value="$a->suggested_publish_at?->format('Y-m-d\TH:i')" />
                        <div class="sm:col-span-2" data-field>
                            <span class="form-label">{{ $t('fields.platforms') }}</span>
                            <input type="hidden" name="platforms" value="">
                            <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach (\App\DTOs\Marketing\SocialMediaAssetData::PLATFORMS as $pf)<x-ui.checkbox name="platforms[]" :value="$pf" :bool="false" :label="$t('platforms.'.$pf)" :checked="in_array($pf, (array) $a->platforms, true)" />@endforeach</div>
                        </div>
                        @foreach (\App\Support\Marketing\StoreText::locales() as $loc)
                            <x-ui.textarea name="caption[{{ $loc }}]" :label="$t('fields.caption').' ('.$loc.')'" rows="3" :value="$a->caption[$loc] ?? ''" class="sm:col-span-2" />
                        @endforeach
                        <x-ui.textarea name="brief" :label="$t('fields.brief')" rows="3" :value="$a->brief" class="sm:col-span-2" />
                        <div class="sm:col-span-2"><x-ui.button type="submit" data-action="save">{{ $t('btn.save') }}</x-ui.button></div>
                    </form>
                @else
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-panel-text-muted">{{ $t('fields.platforms') }}</dt><dd>{{ collect($a->platforms)->map(fn ($x) => $t('platforms.'.$x))->implode('، ') ?: '—' }}</dd>
                        <dt class="text-panel-text-muted">{{ $t('fields.campaign') }}</dt><dd class="min-w-0 break-words">{{ $a->campaign ?: '—' }}</dd>
                        <dt class="text-panel-text-muted">{{ $t('fields.tags') }}</dt><dd class="min-w-0 break-words">{{ implode(', ', (array) $a->tags) ?: '—' }}</dd>
                        <dt class="text-panel-text-muted">{{ $t('fields.caption') }}</dt><dd class="min-w-0 break-words">{{ $a->captionText() ?: '—' }}</dd>
                    </dl>
                @endif
            </x-ui.card>
        </div>

        @if ($st !== $S::ARCHIVED && $me->can('ai_media.upload'))
            <x-ui.card :title="$t('new_version_title')">
                <form data-ajax-form action="{{ route('marketer.media.version', $a->id) }}" method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-3" novalidate data-version-form>
                    @csrf
                    <div data-field>
                        <label for="f-version-file" class="form-label">{{ $t('fields.file') }}</label>
                        <input id="f-version-file" type="file" name="file" class="form-control" accept="{{ implode(',', array_merge(config('marketing.media.image_mimes'), config('marketing.media.video_mimes'))) }}">
                    </div>
                    <x-ui.input name="note" :label="$t('fields.note')" maxlength="500" />
                    <div class="flex items-end"><x-ui.button type="submit" data-action="new-version">{{ $t('btn.new_version') }}</x-ui.button></div>
                    <p class="text-xs text-panel-text-muted sm:col-span-3">{{ $t('new_version_hint') }}</p>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card :title="$t('versions')">
            <div data-versions>
                @if ($a->versions->isEmpty())
                    <x-ui.empty-state kind="nodata" :title="$t('no_file')" message="" class="!py-6" />
                @else
                    <x-ui.table>
                        <thead><tr><th>{{ $t('fields.version') }}</th><th>{{ $t('fields.original_name') }}</th><th>{{ $t('fields.size') }}</th><th>{{ $t('fields.note') }}</th><th>{{ $t('fields.uploaded_by') }}</th><th>{{ $t('fields.created_at') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($a->versions as $v)
                                <tr>
                                    <td data-label="">v{{ $v->version }}</td><td data-label="{{ $t('fields.original_name') }}" dir="ltr">{{ $v->original_name }}</td><td data-label="{{ $t('fields.size') }}" class="whitespace-nowrap" dir="ltr">{{ $size($v->size_bytes) }}</td>
                                    <td data-label="{{ $t('fields.note') }}">{{ $v->note }}</td><td data-label="{{ $t('fields.uploaded_by') }}">{{ $v->uploader?->name }}</td><td data-label="{{ $t('fields.created_at') }}" class="whitespace-nowrap" dir="ltr">{{ $v->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="ui-table-actions"><a class="btn-sm btn-secondary" href="{{ route('marketer.media.download', [$a->id, 'v' => $v->version]) }}">{{ $t('btn.download') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </div>
        </x-ui.card>
    </div>
</x-layouts.marketer>
