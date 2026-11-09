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
    $resultRoutes = ['blog_post' => 'admin.blog-posts.edit', 'bundle' => 'admin.bundles.edit', 'banner' => 'admin.banners.edit', 'flash_sale' => 'admin.flash-sales.edit', 'discount_code' => 'admin.discount-codes.edit', 'product' => 'admin.products.edit', 'social_media_asset' => 'marketer.media.show'];
@endphp
<x-layouts.marketer :title="$s->title">
    <div class="space-y-4" data-suggestion="{{ $s->id }}" data-status="{{ $st->value }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('marketer.suggestions.'.$s->type->page()) }}" class="text-sm text-brand-600 hover:underline">← {{ __('marketing.nav.suggestions_'.$s->type->page()) }}</a>
                <h1 class="mt-1 break-words text-xl font-bold text-slate-800">#{{ $s->id }} {{ $s->title }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge {{ $st->badge() }}" data-status-badge>{{ $st->label() }}</span>
                    @if ($s->rolled_back_at)<span class="badge badge-muted">{{ $t('rolled_back') }}</span>@endif
                    <span class="badge badge-muted">{{ $s->type->label() }}</span>
                    <span class="badge badge-muted">{{ $t('cols.priority') }}: {{ $s->priority->label() }}</span>
                    <span class="badge badge-muted">{{ $s->source->label() }}</span>
                    <span class="badge badge-muted">{{ $t('cols.version') }} {{ $s->version }}</span>
                    @if ($s->target_entity_type)<span class="badge badge-muted">{{ $t('targets.'.$s->target_entity_type) }}{{ $s->target_entity_id ? ' #'.$s->target_entity_id : '' }}</span>@endif
                    @if ($s->expires_at)<span class="text-xs text-slate-500">{{ $t('fields.expires_at') }}: <span dir="ltr">{{ $s->expires_at->format('Y-m-d') }}</span></span>@endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2" data-actions>
                @if ($can['edit'] && $editable)
                    <a href="{{ route('marketer.suggestions.edit', $s->id) }}" class="btn btn-secondary" data-action="edit">{{ $t('btn.edit') }}</a>
                @endif
                @if ($can['edit'] && in_array($st, [$S::PENDING, $S::EDITED], true))
                    <button type="button" class="btn btn-secondary" data-action="review" data-sugg-post="{{ route('marketer.suggestions.review', $s->id) }}">{{ $t('btn.review') }}</button>
                @endif
                @if ($can['approve'] && $sm($S::APPROVED) && $s->submitted_at && ! $check['errors'])
                    <button type="button" class="btn btn-primary" data-action="approve" data-sugg-post="{{ route('marketer.suggestions.approve', $s->id) }}" data-confirm="{{ $t('confirm.approve') }}" data-note="{{ $t('note_placeholder') }}">{{ $t('btn.approve') }}</button>
                @endif
                @if ($can['reject'] && $sm($S::REJECTED))
                    <button type="button" class="btn btn-danger" data-action="reject" data-sugg-post="{{ route('marketer.suggestions.reject', $s->id) }}" data-confirm="{{ $t('confirm.reject') }}" data-note="{{ $t('note_placeholder') }}">{{ $t('btn.reject') }}</button>
                @endif
                @if ($can['edit'] && $stale && $sm($S::EDITED))
                    <button type="button" class="btn btn-secondary" data-action="resnapshot" data-sugg-post="{{ route('marketer.suggestions.resnapshot', $s->id) }}" data-confirm="{{ $t('confirm.resnapshot') }}">{{ $t('btn.resnapshot') }}</button>
                @endif
                @if ($can['apply'] && $st === $S::APPLIED && $handler->supportsRollback())
                    <button type="button" class="btn btn-danger" data-action="rollback" data-sugg-post="{{ route('marketer.suggestions.rollback', $s->id) }}" data-confirm="{{ $t('confirm.rollback') }}">{{ $t('btn.rollback') }}</button>
                @endif
                @if ($can['archive'] && $sm($S::ARCHIVED))
                    <button type="button" class="btn btn-secondary" data-action="archive" data-sugg-post="{{ route('marketer.suggestions.archive', $s->id) }}" data-confirm="{{ $t('confirm.archive') }}">{{ $t('btn.archive') }}</button>
                @endif
                @if ($isTheme)
                    <a href="{{ route('marketer.suggestions.preview', $s->id) }}" target="_blank" rel="noopener" class="btn btn-secondary" data-action="preview">{{ $t('btn.preview_store') }}</a>
                @endif
            </div>
        </div>

        @unless ($s->submitted_at)<p class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" role="status">{{ $t('not_submitted') }}</p>@endunless
        @unless ($handler->applicable())<p class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" role="status" data-p6>{{ $t('p6_note') }}</p>@endunless
        @if ($stale)<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert" data-stale>{{ $t('stale') }}</p>@endif
        @if ($s->last_error && $st === $S::FAILED)<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-last-error>{{ $s->last_error }}</p>@endif
        @if ($check['errors'] && ! in_array($st, [$S::APPLIED, $S::ARCHIVED, $S::REJECTED, $S::EXPIRED], true))
            <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-check-errors><b>{{ $t('errors_now') }}:</b>
                <ul class="ms-5 list-disc">@foreach ($check['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @if ($check['warnings'])
            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-warnings><b>{{ $t('warnings_title') }}:</b>
                <ul class="ms-5 list-disc">@foreach ($check['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
        @endif
        @if ($approval)
            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-approval>{{ $t('approved_version', ['v' => $approval->version, 'at' => $approval->expires_at?->format('Y-m-d H:i')]) }}</p>
        @endif

        @if ($can['apply'] && in_array($st, [$S::APPROVED, $S::FAILED], true) && $approval && $handler->applicable())
            <x-card :title="$t('btn.apply')">
                <form id="apply-form" class="flex flex-wrap items-center gap-4" data-apply-form>
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    @foreach ($handler->applyOptions() as $name => $opt)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="options[{{ $name }}]" value="1"> {{ $t('apply_options.'.$opt['label']) }}</label>
                    @endforeach
                    <button type="button" class="btn btn-primary" data-action="apply" data-form="#apply-form" data-sugg-post="{{ route('marketer.suggestions.apply', $s->id) }}" data-confirm="{{ $t('confirm.apply') }}">{{ $t('btn.apply') }}</button>
                </form>
            </x-card>
        @endif

        @if ($isBlog && $st === $S::APPLIED && $s->result_entity_id && $can['apply'] && auth('admin')->user()->can('ai_blog.approve'))
            @php $post = \App\Models\BlogPost::query()->find($s->result_entity_id); @endphp
            @if ($post)
                <x-card :title="$t('blog.post')">
                    <p class="mb-3 text-sm">{{ $t('blog.post') }} #{{ $post->id }} · <span class="badge {{ $post->is_active ? 'badge-success' : 'badge-muted' }}" data-post-state>{{ ! $post->is_active ? $t('blog.draft') : ($post->published_at && $post->published_at->isFuture() ? $t('blog.scheduled').' '.$post->published_at->format('Y-m-d H:i') : $t('blog.published')) }}</span></p>
                    <form id="publish-form" class="flex flex-wrap items-end gap-3">
                        <button type="button" class="btn btn-primary" data-action="publish" data-sugg-post="{{ route('marketer.suggestions.publish', $s->id) }}" data-confirm="{{ $t('confirm.publish') }}">{{ $t('btn.publish') }}</button>
                        <div><label class="form-label" for="publish_at">{{ $t('publish_at') }}</label><input id="publish_at" type="datetime-local" name="publish_at" class="form-control" dir="ltr"></div>
                        <button type="button" class="btn btn-secondary" data-action="schedule" data-form="#publish-form" data-sugg-post="{{ route('marketer.suggestions.publish', $s->id) }}">{{ $t('btn.schedule') }}</button>
                    </form>
                </x-card>
            @endif
        @endif

        @if ($s->result_entity_type)
            <p class="text-sm" data-result>{{ $t('result_link') }}: {{ $t('targets.'.$s->result_entity_type) }} #{{ $s->result_entity_id }}
                @if (isset($resultRoutes[$s->result_entity_type]) && Route::has($resultRoutes[$s->result_entity_type]))
                    · <a class="text-brand-700 hover:underline" href="{{ route($resultRoutes[$s->result_entity_type], $s->result_entity_id) }}" data-admin-link>{{ $t('open') }}</a>
                @endif
            </p>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <x-card :title="$t('reasoning')" class="lg:col-span-1">
                @if ($s->description)<p class="mb-2 whitespace-pre-line break-words text-sm text-slate-700">{{ $s->description }}</p>@endif
                <p class="whitespace-pre-line break-words text-sm text-slate-700" data-reasoning>{{ $s->reasoning ?: '—' }}</p>
                @if ($s->source_meta)
                    <details class="mt-3 text-sm"><summary class="cursor-pointer text-slate-500">{{ $t('source_meta') }}</summary>@include('marketer.suggestions._value', ['value' => $s->source_meta])</details>
                @endif
                @if ($s->source_data_snapshot)
                    <details class="mt-2 text-sm"><summary class="cursor-pointer text-slate-500">{{ $t('source_data') }}</summary>@include('marketer.suggestions._value', ['value' => $s->source_data_snapshot])</details>
                @endif
            </x-card>
            <x-card :title="$t('compare')" class="lg:col-span-2">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" data-compare>
                        <thead><tr class="text-slate-500"><th class="w-1/5 py-1 text-start"></th><th class="w-2/5 text-start">{{ $t('current') }}</th><th class="w-2/5 text-start">{{ $t('proposed') }}</th></tr></thead>
                        <tbody>
                        @forelse ($compare as $row)
                            <tr class="border-t border-slate-100 align-top" data-field="{{ $row['field'] }}">
                                <th class="py-2 pe-2 text-start font-medium text-slate-600">{{ $row['label'] }}</th>
                                <td class="py-2 pe-2">@include('marketer.suggestions._value', ['value' => $row['current'], 'html' => $row['html'] ?? false, 'color' => $row['color'] ?? false])</td>
                                @if ($row['proposed'] === null && $row['current'] !== null)
                                    <td class="py-2 text-xs text-slate-400">{{ $t('unchanged') }}</td>
                                @else
                                    <td class="py-2 {{ ($row['current'] ?? null) != ($row['proposed'] ?? null) ? 'bg-emerald-50/60' : '' }}" data-changed="{{ ($row['current'] ?? null) != ($row['proposed'] ?? null) ? 1 : 0 }}">@include('marketer.suggestions._value', ['value' => $row['proposed'], 'html' => $row['html'] ?? false, 'color' => $row['color'] ?? false])</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-slate-500">—</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <x-card :title="$t('preview')">
            @include('marketer.suggestions._preview')
        </x-card>

        <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <summary class="cursor-pointer font-semibold text-slate-700">{{ $t('payload') }}</summary>
            <div class="mt-2">@include('marketer.suggestions._value', ['value' => $p])</div>
        </details>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card :title="$t('history')">
                <ul class="space-y-2 text-sm" data-revisions>
                    @foreach ($revisions as $r)
                        <li class="border-b border-slate-100 pb-2 last:border-0">
                            <b>v{{ $r->version }}</b> · {{ $r->editorName() }} · <span dir="ltr" class="text-xs text-slate-500">{{ $r->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($r->note)<span class="text-slate-500">— {{ $r->note }}</span>@endif
                            @if ($r->diff)<div class="mt-1 text-xs text-slate-600">{{ collect($r->diff)->pluck('field')->implode('، ') }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
            <x-card :title="$t('approvals')">
                <ul class="space-y-2 text-sm" data-approvals>
                    @forelse ($approvals as $a)
                        <li class="border-b border-slate-100 pb-2 last:border-0">
                            <span class="badge {{ $a->decision === 'approved' ? 'badge-success' : 'badge-danger' }}">{{ $t('statuses.'.$a->decision) }}</span> v{{ $a->version }} · {{ $a->admin?->name }}
                            · <span dir="ltr" class="text-xs text-slate-500">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($a->note)<div class="text-slate-600">{{ $a->note }}</div>@endif
                            @if ($a->invalidated_at)<div class="text-xs text-amber-700">{{ $t('cols.invalidated') }}: {{ $a->invalidated_reason }} ({{ $a->invalidated_at->format('Y-m-d H:i') }})</div>@endif
                        </li>
                    @empty
                        <li class="text-slate-500">—</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card :title="$t('executions')">
                <ul class="space-y-2 text-sm" data-executions>
                    @forelse ($executions as $e)
                        <li class="border-b border-slate-100 pb-2 last:border-0">
                            <span class="badge {{ ['succeeded' => 'badge-success', 'failed' => 'badge-danger', 'running' => 'badge-warn'][$e->status->value] }}">{{ $e->status->label() }}</span>
                            {{ $t('exec_kinds.'.$e->kind) }} · v{{ $e->version }} · {{ $e->executor?->name }} · <span dir="ltr" class="text-xs text-slate-500">{{ $e->started_at?->format('Y-m-d H:i:s') }}</span>
                            @if ($e->error)<div class="break-words text-red-700">{{ \Illuminate\Support\Str::limit($e->error, 400) }}</div>@endif
                            @if ($e->before || $e->after)
                                <details class="mt-1"><summary class="cursor-pointer text-xs text-slate-500">before / after</summary>
                                    <div class="grid gap-2 md:grid-cols-2">@include('marketer.suggestions._value', ['value' => $e->before])@include('marketer.suggestions._value', ['value' => $e->after])</div></details>
                            @endif
                        </li>
                    @empty
                        <li class="text-slate-500">—</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card :title="$t('activity')">
                <ul class="space-y-1 text-sm" data-activity>
                    @foreach ($activity as $a)
                        <li><span dir="ltr" class="text-xs text-slate-500">{{ $a->created_at?->format('Y-m-d H:i') }}</span> · <b>{{ $t('actions.'.$a->action) }}</b> · {{ $a->actorLabel() }}</li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
    @include('marketer.suggestions._script')
</x-layouts.marketer>
