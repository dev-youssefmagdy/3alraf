@php
    $t = fn ($k, $r = []) => __('marketing.suggestions.'.$k, $r);
    $S = \App\Enums\Marketing\SuggestionStatus::class;
    $st = $s->status;
    $p = (array) $s->payload;
    $sm = fn ($to) => \App\Services\Marketing\Suggestions\SuggestionStateMachine::can($st, $to);
    $editable = in_array($st, $S::PANEL_EDITABLE, true);
    $isBlog = $s->type === \App\Enums\Marketing\SuggestionTypeKey::BLOG;
    $isTheme = $s->type === \App\Enums\Marketing\SuggestionTypeKey::SEASONAL_THEME;
    $loc = fn ($arr) => is_array($arr) ? (($arr[app()->getLocale()] ?? null) ?: collect($arr)->filter()->first()) : $arr;
@endphp
<x-layouts.marketer :title="$s->title">
        <x-slot:header>
        <x-ui.page-header :title="'#'.$s->id.' '.$s->title" :breadcrumbs="[[__('marketing.nav.suggestions_'.$s->type->page()), route('marketer.suggestions.'.$s->type->page())]]">
            <div class="flex flex-wrap items-center gap-2 max-md:w-full" data-actions>
                @if ($can['edit'] && $editable)
                    <a href="{{ route('marketer.suggestions.edit', $s->id) }}" class="btn btn-secondary btn-md" data-action="edit">{{ $t('btn.edit') }}</a>
                @endif
                @if ($can['edit'] && in_array($st, [$S::PENDING, $S::EDITED], true))
                    <button type="button" class="btn btn-secondary btn-md" data-action="review" data-sugg-post="{{ route('marketer.suggestions.review', $s->id) }}">{{ $t('btn.review') }}</button>
                @endif
                @if ($can['approve'] && $sm($S::APPROVED) && $s->submitted_at && ! $check['errors'])
                    <button type="button" class="btn btn-primary btn-md" data-action="approve" data-sugg-post="{{ route('marketer.suggestions.approve', $s->id) }}" data-confirm="{{ $t('confirm.approve') }}" data-note="{{ $t('note_placeholder') }}">{{ $t('btn.approve') }}</button>
                @endif
                @if ($can['reject'] && $sm($S::REJECTED))
                    <button type="button" class="btn btn-danger btn-md" data-action="reject" data-sugg-post="{{ route('marketer.suggestions.reject', $s->id) }}" data-confirm="{{ $t('confirm.reject') }}" data-note="{{ $t('note_placeholder') }}">{{ $t('btn.reject') }}</button>
                @endif
                @if ($can['edit'] && $stale && $sm($S::EDITED))
                    <button type="button" class="btn btn-secondary btn-md" data-action="resnapshot" data-sugg-post="{{ route('marketer.suggestions.resnapshot', $s->id) }}" data-confirm="{{ $t('confirm.resnapshot') }}">{{ $t('btn.resnapshot') }}</button>
                @endif
                @if ($can['apply'] && $st === $S::APPLIED && $handler->supportsRollback())
                    <button type="button" class="btn btn-danger btn-md" data-action="rollback" data-sugg-post="{{ route('marketer.suggestions.rollback', $s->id) }}" data-confirm="{{ $t('confirm.rollback') }}">{{ $t('btn.rollback') }}</button>
                @endif
                @if ($can['archive'] && $sm($S::ARCHIVED))
                    <button type="button" class="btn btn-secondary btn-md" data-action="archive" data-sugg-post="{{ route('marketer.suggestions.archive', $s->id) }}" data-confirm="{{ $t('confirm.archive') }}">{{ $t('btn.archive') }}</button>
                @endif
                @if ($isTheme)
                    <a href="{{ route('marketer.suggestions.preview', $s->id) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-md" data-action="preview">{{ $t('btn.preview_store') }}</a>
                @endif
            </div>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-suggestion="{{ $s->id }}" data-status="{{ $st->value }}">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$st->badge()" dot data-status-badge>{{ $st->label() }}</x-ui.badge>
            @if ($s->rolled_back_at)<x-ui.badge>{{ $t('rolled_back') }}</x-ui.badge>@endif
            <x-ui.badge>{{ $s->type->label() }}</x-ui.badge>
            <x-ui.badge>{{ $t('cols.priority') }}: {{ $s->priority->label() }}</x-ui.badge>
            <x-ui.badge>{{ $s->source->label() }}</x-ui.badge>
            <x-ui.badge>{{ $t('cols.version') }} {{ $s->version }}</x-ui.badge>
            @if ($s->target_entity_type)<x-ui.badge>{{ $t('targets.'.$s->target_entity_type) }}{{ $s->target_entity_id ? ' #'.$s->target_entity_id : '' }}</x-ui.badge>@endif
            @if ($s->expires_at)<span class="text-xs text-panel-text-muted">{{ $t('fields.expires_at') }}: <span dir="ltr">{{ $s->expires_at->format('Y-m-d') }}</span></span>@endif
        </div>

        @unless ($s->submitted_at)<x-ui.alert tone="info">{{ $t('not_submitted') }}</x-ui.alert>@endunless
        @unless ($handler->applicable())<x-ui.alert tone="info" data-p6>{{ $t('p6_note') }}</x-ui.alert>@endunless
        @if ($stale)<x-ui.alert tone="warning" data-stale>{{ $t('stale') }}</x-ui.alert>@endif
        @if ($s->last_error && $st === $S::FAILED)<x-ui.alert tone="danger" data-last-error>{{ $s->last_error }}</x-ui.alert>@endif
        @if ($check['errors'] && ! in_array($st, [$S::APPLIED, $S::ARCHIVED, $S::REJECTED, $S::EXPIRED], true))
            <x-ui.alert tone="danger" data-check-errors><div><b>{{ $t('errors_now') }}:</b>
                <ul class="ms-5 list-disc">@foreach ($check['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div></x-ui.alert>
        @endif
        @if ($check['warnings'])
            <x-ui.alert tone="warning" data-warnings><div><b>{{ $t('warnings_title') }}:</b>
                <ul class="ms-5 list-disc">@foreach ($check['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div></x-ui.alert>
        @endif
        @if ($approval)
            <x-ui.alert tone="success" data-approval><span>{{ $t('approved_version', ['v' => $approval->version, 'at' => $approval->expires_at?->format('Y-m-d H:i')]) }}</span></x-ui.alert>
        @endif

        @if ($can['apply'] && in_array($st, [$S::APPROVED, $S::FAILED], true) && $approval && $handler->applicable())
            <x-ui.card :title="$t('btn.apply')">
                <form id="apply-form" class="flex flex-wrap items-center gap-4" data-apply-form>
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    @foreach ($handler->applyOptions() as $name => $opt)
                        <x-ui.checkbox :name="'options['.$name.']'" :label="$t('apply_options.'.$opt['label'])" :bool="false" value="1" />
                    @endforeach
                    <button type="button" class="btn btn-primary" data-action="apply" data-form="#apply-form" data-sugg-post="{{ route('marketer.suggestions.apply', $s->id) }}" data-confirm="{{ $t('confirm.apply') }}">{{ $t('btn.apply') }}</button>
                </form>
            </x-ui.card>
        @endif

        @if ($isBlog && $st === $S::APPLIED && $s->result_entity_id && $can['apply'] && auth('marketer')->user()->can('ai_blog.approve'))
            @php $post = \App\Models\BlogPost::query()->find($s->result_entity_id); @endphp
            @if ($post)
                <x-ui.card :title="$t('blog.post')">
                    <p class="mb-3 text-sm">{{ $t('blog.post') }} #{{ $post->id }} · <x-ui.badge :tone="$post->is_active ? 'success' : 'neutral'" data-post-state>{{ ! $post->is_active ? $t('blog.draft') : ($post->published_at && $post->published_at->isFuture() ? $t('blog.scheduled').' '.$post->published_at->format('Y-m-d H:i') : $t('blog.published')) }}</x-ui.badge></p>
                    <form id="publish-form" class="flex flex-wrap items-end gap-3">
                        <button type="button" class="btn btn-primary" data-action="publish" data-sugg-post="{{ route('marketer.suggestions.publish', $s->id) }}" data-confirm="{{ $t('confirm.publish') }}">{{ $t('btn.publish') }}</button>
                        <div><label class="form-label" for="publish_at">{{ $t('publish_at') }}</label><input id="publish_at" type="datetime-local" name="publish_at" class="form-control" dir="ltr"></div>
                        <button type="button" class="btn btn-secondary" data-action="schedule" data-form="#publish-form" data-sugg-post="{{ route('marketer.suggestions.publish', $s->id) }}">{{ $t('btn.schedule') }}</button>
                    </form>
                </x-ui.card>
            @endif
        @endif

        @if ($s->result_entity_type)
            {{-- result opens inside this panel only (marketing tools live here too); product edit stays an admin task, so no link --}}
            @php
                $resultRoutes = ['blog_post' => 'marketer.blog-posts.edit', 'bundle' => 'marketer.bundles.edit', 'banner' => 'marketer.banners.edit', 'flash_sale' => 'marketer.flash-sales.edit', 'discount_code' => 'marketer.discount-codes.edit', 'social_media_asset' => 'marketer.media.show'];
            @endphp
            <p class="text-sm" data-result>{{ $t('result_link') }}: {{ $t('targets.'.$s->result_entity_type) }} #{{ $s->result_entity_id }}
                @if (isset($resultRoutes[$s->result_entity_type]) && Route::has($resultRoutes[$s->result_entity_type]))
                    · <a class="text-panel-accent-text hover:underline" href="{{ route($resultRoutes[$s->result_entity_type], $s->result_entity_id) }}">{{ $t('open') }}</a>
                @endif
            </p>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <x-ui.card :title="$t('reasoning')" class="lg:col-span-1">
                @if ($s->description)<p class="mb-2 whitespace-pre-line break-words text-sm text-panel-text">{{ $s->description }}</p>@endif
                <p class="whitespace-pre-line break-words text-sm text-panel-text" data-reasoning>{{ $s->reasoning ?: '—' }}</p>
                @if ($s->source_meta)
                    <details class="mt-3 text-sm"><summary class="cursor-pointer text-panel-text-muted">{{ $t('source_meta') }}</summary>@include('marketer.suggestions._value', ['value' => $s->source_meta])</details>
                @endif
                @if ($s->source_data_snapshot)
                    <details class="mt-2 text-sm"><summary class="cursor-pointer text-panel-text-muted">{{ $t('source_data') }}</summary>@include('marketer.suggestions._value', ['value' => $s->source_data_snapshot])</details>
                @endif
            </x-ui.card>
            <x-ui.card :title="$t('compare')" class="lg:col-span-2">
                <x-ui.table data-compare-wrap>
                    <thead><tr><th class="w-1/5"></th><th class="w-2/5">{{ $t('current') }}</th><th class="w-2/5">{{ $t('proposed') }}</th></tr></thead>
                    <tbody data-compare>
                    @forelse ($compare as $row)
                        <tr class="align-top" data-field="{{ $row['field'] }}">
                            <th class="!font-medium !text-panel-text-muted md:!py-3">{{ $row['label'] }}</th>
                            <td data-label="{{ $t('current') }}" class="max-md:!flex-col max-md:!items-start max-md:!text-start">@include('marketer.suggestions._value', ['value' => $row['current'], 'html' => $row['html'] ?? false, 'color' => $row['color'] ?? false, 'images' => $row['images'] ?? false])</td>
                            @if ($row['proposed'] === null && $row['current'] !== null)
                                <td data-label="{{ $t('proposed') }}" class="text-xs text-panel-text-subtle">{{ $t('unchanged') }}</td>
                            @else
                                <td data-label="{{ $t('proposed') }}" class="max-md:!flex-col max-md:!items-start max-md:!text-start {{ ($row['current'] ?? null) != ($row['proposed'] ?? null) ? 'bg-panel-success-bg/60' : '' }}" data-changed="{{ ($row['current'] ?? null) != ($row['proposed'] ?? null) ? 1 : 0 }}">@include('marketer.suggestions._value', ['value' => $row['proposed'], 'html' => $row['html'] ?? false, 'color' => $row['color'] ?? false, 'images' => $row['images'] ?? false])</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-panel-text-muted">—</td></tr>
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        <x-ui.card :title="$t('preview')">
            @include('marketer.suggestions._preview')
        </x-ui.card>

        <details class="rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs">
            <summary class="cursor-pointer font-semibold text-panel-text">{{ $t('payload') }}</summary>
            <div class="mt-2">@include('marketer.suggestions._value', ['value' => $p])</div>
        </details>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card :title="$t('history')">
                <ul class="space-y-2 text-sm" data-revisions>
                    @foreach ($revisions as $r)
                        <li class="border-b border-panel-border pb-2 last:border-0">
                            <b>v{{ $r->version }}</b> · {{ $r->editorName() }} · <span dir="ltr" class="text-xs text-panel-text-muted">{{ $r->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($r->note)<span class="text-panel-text-muted">— {{ $r->note }}</span>@endif
                            @if ($r->diff)<div class="mt-1 text-xs text-panel-text-muted">{{ collect($r->diff)->pluck('field')->implode('، ') }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
            <x-ui.card :title="$t('approvals')">
                <ul class="space-y-2 text-sm" data-approvals>
                    @forelse ($approvals as $a)
                        <li class="border-b border-panel-border pb-2 last:border-0">
                            <x-ui.badge :tone="$a->decision === 'approved' ? 'success' : 'danger'" size="sm">{{ $t('statuses.'.$a->decision) }}</x-ui.badge> v{{ $a->version }} · {{ $a->admin?->name }}
                            · <span dir="ltr" class="text-xs text-panel-text-muted">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($a->note)<div class="text-panel-text-muted">{{ $a->note }}</div>@endif
                            @if ($a->invalidated_at)<div class="text-xs text-panel-warning-fg">{{ $t('cols.invalidated') }}: {{ $a->invalidated_reason }} ({{ $a->invalidated_at->format('Y-m-d H:i') }})</div>@endif
                        </li>
                    @empty
                        <li class="text-panel-text-muted">—</li>
                    @endforelse
                </ul>
            </x-ui.card>
            <x-ui.card :title="$t('executions')">
                <ul class="space-y-2 text-sm" data-executions>
                    @forelse ($executions as $e)
                        <li class="border-b border-panel-border pb-2 last:border-0">
                            <x-ui.badge :tone="['succeeded' => 'success', 'failed' => 'danger', 'running' => 'warning'][$e->status->value]" size="sm">{{ $e->status->label() }}</x-ui.badge>
                            {{ $t('exec_kinds.'.$e->kind) }} · v{{ $e->version }} · {{ $e->executor?->name }} · <span dir="ltr" class="text-xs text-panel-text-muted">{{ $e->started_at?->format('Y-m-d H:i:s') }}</span>
                            @if ($e->error)<div class="break-words text-panel-danger-fg">{{ \Illuminate\Support\Str::limit($e->error, 400) }}</div>@endif
                            @if ($e->before || $e->after)
                                <details class="mt-1"><summary class="cursor-pointer text-xs text-panel-text-muted">before / after</summary>
                                    <div class="grid gap-2 md:grid-cols-2">@include('marketer.suggestions._value', ['value' => $e->before])@include('marketer.suggestions._value', ['value' => $e->after])</div></details>
                            @endif
                        </li>
                    @empty
                        <li class="text-panel-text-muted">—</li>
                    @endforelse
                </ul>
            </x-ui.card>
            <x-ui.card :title="$t('activity')">
                <ul class="space-y-1 text-sm" data-activity>
                    @foreach ($activity as $a)
                        <li><span dir="ltr" class="text-xs text-panel-text-muted">{{ $a->created_at?->format('Y-m-d H:i') }}</span> · <b>{{ $t('actions.'.$a->action) }}</b> · {{ $a->actorLabel() }}</li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
    @include('marketer.suggestions._script')
</x-layouts.marketer>
